<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;

final class LegacySpProvisioningClient
{
    public function provisionOrganization(OrganizationProvisioningRequest $request): LegacyProvisioningHttpResult
    {
        $configuration = LegacyProvisioningConfiguration::sp();
        $configuration->assertUsable();

        return $this->post(
            configuration: $configuration,
            path: '/api/core/provisioning/organizations',
            payload: $request->toPayload($configuration->clientIdentifier, $configuration->clientSecret),
            entityType: 'organization',
        );
    }

    public function provisionUser(UserProvisioningRequest $request): LegacyProvisioningHttpResult
    {
        $configuration = LegacyProvisioningConfiguration::sp();
        $configuration->assertUsable();

        return $this->post(
            configuration: $configuration,
            path: '/api/core/provisioning/users',
            payload: $request->toPayload($configuration->clientIdentifier, $configuration->clientSecret),
            entityType: 'user',
        );
    }

    /**
     * The current Legacy contract authenticates by payload fields. Keep this
     * class as the only place that builds the raw body so callers never log it.
     *
     * @param  array<string, mixed>  $payload
     */
    private function post(
        LegacyProvisioningConfiguration $configuration,
        string $path,
        array $payload,
        string $entityType,
    ): LegacyProvisioningHttpResult {
        $attempt = 0;
        $lastConnectionCategory = LegacyProvisioningErrorCategory::ConnectionFailed;

        while ($attempt < $configuration->maxAttempts) {
            $attempt++;

            try {
                $response = Http::asJson()
                    ->acceptJson()
                    ->connectTimeout($configuration->connectTimeoutSeconds)
                    ->timeout($configuration->timeoutSeconds)
                    ->post($configuration->endpoint($path), $payload);
            } catch (ConnectionException $exception) {
                $lastConnectionCategory = str_contains(strtolower($exception->getMessage()), 'timed out')
                    ? LegacyProvisioningErrorCategory::Timeout
                    : LegacyProvisioningErrorCategory::ConnectionFailed;

                if ($attempt >= $configuration->maxAttempts) {
                    return new LegacyProvisioningHttpResult(
                        outcome: LegacyProvisioningOutcome::Unavailable,
                        attempts: $attempt,
                        errorCategory: $lastConnectionCategory,
                    );
                }

                $this->pauseBeforeRetry($configuration, $attempt);

                continue;
            }

            if ($this->shouldRetry($response, $configuration) && $attempt < $configuration->maxAttempts) {
                $this->pauseBeforeRetry($configuration, $attempt, $response);

                continue;
            }

            return $this->translateResponse($response, $attempt, $configuration, $entityType);
        }

        return new LegacyProvisioningHttpResult(
            outcome: LegacyProvisioningOutcome::Unavailable,
            attempts: $attempt,
            errorCategory: $lastConnectionCategory,
        );
    }

    private function shouldRetry(Response $response, LegacyProvisioningConfiguration $configuration): bool
    {
        if (in_array($response->status(), [502, 503, 504], true)) {
            return true;
        }

        if ($response->status() !== 429) {
            return false;
        }

        $retryAfter = $this->retryAfterSeconds($response);

        return $retryAfter !== null && $retryAfter <= $configuration->maxRetryAfterSeconds;
    }

    private function translateResponse(
        Response $response,
        int $attempts,
        LegacyProvisioningConfiguration $configuration,
        string $entityType,
    ): LegacyProvisioningHttpResult {
        if (in_array($response->status(), [502, 503, 504, 429], true)) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Unavailable,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::HttpUnavailable,
                technicalData: ['http_status' => $response->status()],
            );
        }

        if (! $this->hasJsonContentType($response)) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::InvalidResponse,
                technicalData: ['http_status' => $response->status()],
            );
        }

        if (strlen($response->body()) > $configuration->maxResponseBytes) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::InvalidResponse,
                technicalData: ['http_status' => $response->status()],
            );
        }

        /** @var mixed $decoded */
        $decoded = $response->json();

        if (! is_array($decoded)) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::InvalidResponse,
                technicalData: ['http_status' => $response->status()],
            );
        }

        if ($response->status() === 401) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::AuthenticationRejected,
                technicalData: ['http_status' => 401],
            );
        }

        if ($response->status() === 409) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Conflict,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::Conflict,
                technicalData: ['http_status' => 409],
            );
        }

        if (in_array($response->status(), [400, 403, 422], true)) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::Rejected,
                technicalData: ['http_status' => $response->status()],
            );
        }

        if (! $response->successful()) {
            return new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Unavailable,
                attempts: $attempts,
                errorCategory: LegacyProvisioningErrorCategory::HttpUnavailable,
                technicalData: ['http_status' => $response->status()],
            );
        }

        return $this->successfulResult($decoded, $attempts, $entityType);
    }

    /**
     * @param  array<array-key, mixed>  $decoded
     */
    private function successfulResult(array $decoded, int $attempts, string $entityType): LegacyProvisioningHttpResult
    {
        $result = $decoded['result'] ?? null;
        if (! is_string($result)) {
            throw new InvalidArgumentException('Legacy provisioning response is missing result.');
        }

        $outcome = LegacyProvisioningOutcome::tryFrom($result);
        if (! $outcome instanceof LegacyProvisioningOutcome) {
            throw new InvalidArgumentException('Legacy provisioning response has an invalid result.');
        }

        $resource = $decoded[$entityType] ?? null;
        if (! is_array($resource)) {
            throw new InvalidArgumentException('Legacy provisioning response is missing resource data.');
        }

        $remoteLocalId = $entityType === 'organization'
            ? $this->optionalString($resource['company_id'] ?? null)
            : $this->optionalString($resource['user_id'] ?? null);

        return new LegacyProvisioningHttpResult(
            outcome: $outcome,
            attempts: $attempts,
            remoteLocalId: $remoteLocalId,
            coreOrganizationId: $this->optionalString($resource['core_organization_id'] ?? null),
            coreSubject: $this->optionalString($resource['core_subject'] ?? null),
            technicalData: ['http_status' => 200],
        );
    }

    private function hasJsonContentType(Response $response): bool
    {
        $contentType = strtolower($response->header('Content-Type'));

        return str_contains($contentType, 'application/json') || str_contains($contentType, '+json');
    }

    private function retryAfterSeconds(Response $response): ?int
    {
        $value = $response->header('Retry-After');

        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            return (int) $value;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    private function pauseBeforeRetry(LegacyProvisioningConfiguration $configuration, int $attempt, ?Response $response = null): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $retryAfter = $response instanceof Response ? $this->retryAfterSeconds($response) : null;
        $milliseconds = $retryAfter !== null
            ? $retryAfter * 1000
            : ($configuration->backoffMilliseconds * $attempt) + random_int(0, $configuration->jitterMilliseconds);

        if ($milliseconds > 0) {
            usleep($milliseconds * 1000);
        }
    }

    private function optionalString(mixed $value): ?string
    {
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
