<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\Models\LegacyProvisioningOperation;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\OrganizationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProvisionUserToLegacySp
{
    public function __construct(
        private readonly LegacySpProvisioningClient $client,
        private readonly LegacyProvisioningIdempotencyKeys $idempotencyKeys,
        private readonly LegacyProvisioningOperationRecorder $operationRecorder,
        private readonly LegacyProvisioningAudit $audit,
        private readonly ProvisionOrganizationToLegacySp $provisionOrganization,
    ) {}

    public function __invoke(User $user, Organization $organization, bool $ensureOrganization = true): LegacySpAccessProvisioningResult
    {
        $configuration = LegacyProvisioningConfiguration::sp();
        $configuration->assertUsable();

        $organizationResult = $ensureOrganization
            ? ($this->provisionOrganization)($organization)
            : new LegacyProvisioningActionResult(
                entityType: 'organization',
                entityId: (string) $organization->getKey(),
                organizationId: null,
                outcome: LegacyProvisioningOutcome::AlreadyProvisioned,
                attempts: 0,
            );

        if (! $organizationResult->isSuccessful()) {
            return new LegacySpAccessProvisioningResult($organizationResult, null, 'failed');
        }

        $idempotencyKey = $this->idempotencyKeys->user($user, $organization);
        $now = CarbonImmutable::now();
        $correlationId = (string) Str::uuid();
        $operation = $this->operationRecorder->requested('user', (string) $user->getKey(), (string) $organization->getKey(), $idempotencyKey, $now);
        $this->audit->requested('user', (string) $user->getKey(), (string) $organization->getKey(), $correlationId, $now);

        $localValidation = $this->localValidationError($user, $organization, $now);
        if ($localValidation instanceof LegacyProvisioningErrorCategory) {
            $userResult = new LegacyProvisioningActionResult(
                entityType: 'user',
                entityId: (string) $user->getKey(),
                organizationId: (string) $organization->getKey(),
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: 0,
                errorCategory: $localValidation,
            );

            $this->finish($operation, $userResult, $correlationId);

            return new LegacySpAccessProvisioningResult($organizationResult, $userResult, 'partially_provisioned');
        }

        try {
            $httpResult = $this->client->provisionUser(new UserProvisioningRequest(
                coreSubject: (string) $user->getKey(),
                coreOrganizationId: (string) $organization->getKey(),
                name: (string) $user->display_name,
                email: $user->primary_email !== null ? (string) $user->primary_email : null,
                status: 'active',
                idempotencyKey: $idempotencyKey,
                issuer: $configuration->issuer,
                contractVersion: $configuration->contractVersion,
            ));
        } catch (InvalidArgumentException) {
            $httpResult = new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: 1,
                errorCategory: LegacyProvisioningErrorCategory::InvalidResponse,
            );
        }

        $userResult = new LegacyProvisioningActionResult(
            entityType: 'user',
            entityId: (string) $user->getKey(),
            organizationId: (string) $organization->getKey(),
            outcome: $httpResult->outcome,
            attempts: $httpResult->attempts,
            errorCategory: $httpResult->errorCategory,
            remoteLocalId: $httpResult->remoteLocalId,
        );

        $this->finish($operation, $userResult, $correlationId);

        if ($userResult->isSuccessful()) {
            return new LegacySpAccessProvisioningResult($organizationResult, $userResult, 'provisioned');
        }

        $overall = 'partially_provisioned';
        $this->audit->partial($organizationResult, $userResult, (string) Str::uuid(), CarbonImmutable::now());

        return new LegacySpAccessProvisioningResult($organizationResult, $userResult, $overall);
    }

    private function localValidationError(User $user, Organization $organization, CarbonImmutable $now): ?LegacyProvisioningErrorCategory
    {
        if ($user->status !== 'active' || $organization->status !== OrganizationStatus::Active->value) {
            return LegacyProvisioningErrorCategory::LocalValidationFailed;
        }

        if ((string) $user->getKey() === '' || (string) $organization->getKey() === '') {
            return LegacyProvisioningErrorCategory::LocalValidationFailed;
        }

        $hasMembership = OrganizationMembership::query()
            ->where('user_id', $user->getKey())
            ->where('organization_id', $organization->getKey())
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->where('started_at', '<=', $now)
            ->where(function ($query) use ($now): void {
                $query->whereNull('ended_at')->orWhere('ended_at', '>=', $now);
            })
            ->exists();

        return $hasMembership ? null : LegacyProvisioningErrorCategory::LocalValidationFailed;
    }

    private function finish(LegacyProvisioningOperation $operation, LegacyProvisioningActionResult $result, string $correlationId): void
    {
        $now = CarbonImmutable::now();

        $this->operationRecorder->completed($operation, $result->outcome, $result->attempts, $result->errorCategory, $result->remoteLocalId, $now);
        $this->audit->completed($result, $correlationId, $now);
    }
}
