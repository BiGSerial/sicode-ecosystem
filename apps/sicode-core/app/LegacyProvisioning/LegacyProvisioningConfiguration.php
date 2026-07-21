<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use InvalidArgumentException;

final readonly class LegacyProvisioningConfiguration
{
    private function __construct(
        public bool $enabled,
        public string $baseUrl,
        public string $clientIdentifier,
        public string $clientSecret,
        public string $issuer,
        public string $contractVersion,
        public string $expectedContext,
        public float $connectTimeoutSeconds,
        public float $timeoutSeconds,
        public int $maxResponseBytes,
        public int $maxAttempts,
        public int $backoffMilliseconds,
        public int $jitterMilliseconds,
        public int $maxRetryAfterSeconds,
    ) {}

    public static function sp(): self
    {
        /** @var array<string, mixed> $config */
        $config = config('legacy_provisioning.sp', []);
        /** @var array<string, mixed> $retry */
        $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];

        return new self(
            enabled: (bool) ($config['enabled'] ?? false),
            baseUrl: trim((string) ($config['base_url'] ?? '')),
            clientIdentifier: trim((string) ($config['client_identifier'] ?? '')),
            clientSecret: (string) ($config['client_secret'] ?? ''),
            issuer: trim((string) ($config['issuer'] ?? '')),
            contractVersion: trim((string) ($config['contract_version'] ?? '')),
            expectedContext: strtolower(trim((string) ($config['expected_context'] ?? 'sp'))),
            connectTimeoutSeconds: (float) ($config['connect_timeout_seconds'] ?? 2.0),
            timeoutSeconds: (float) ($config['timeout_seconds'] ?? 8.0),
            maxResponseBytes: (int) ($config['max_response_bytes'] ?? 65536),
            maxAttempts: max(1, (int) ($retry['max_attempts'] ?? 3)),
            backoffMilliseconds: max(0, (int) ($retry['backoff_milliseconds'] ?? 150)),
            jitterMilliseconds: max(0, (int) ($retry['jitter_milliseconds'] ?? 50)),
            maxRetryAfterSeconds: max(0, (int) ($retry['max_retry_after_seconds'] ?? 5)),
        );
    }

    public function assertUsable(): void
    {
        if (! $this->enabled) {
            throw new InvalidArgumentException('Legacy SP provisioning is disabled.');
        }

        if ($this->baseUrl === '' || ! filter_var($this->baseUrl, FILTER_VALIDATE_URL)) {
            throw new InvalidArgumentException('Legacy SP provisioning base URL is invalid.');
        }

        $scheme = strtolower((string) parse_url($this->baseUrl, PHP_URL_SCHEME));
        if ($scheme !== 'https' && ! app()->environment(['local', 'testing'])) {
            throw new InvalidArgumentException('Legacy SP provisioning requires HTTPS outside local environments.');
        }

        if ($this->clientIdentifier === '') {
            throw new InvalidArgumentException('Legacy SP provisioning client identifier is missing.');
        }

        if ($this->clientSecret === '') {
            throw new InvalidArgumentException('Legacy SP provisioning client secret is missing.');
        }

        if ($this->issuer === '' || $this->contractVersion === '') {
            throw new InvalidArgumentException('Legacy SP provisioning issuer or contract version is missing.');
        }

        if ($this->expectedContext !== 'sp') {
            throw new InvalidArgumentException('Legacy provisioning target must be SP.');
        }

        if ($this->connectTimeoutSeconds <= 0 || $this->timeoutSeconds <= 0 || $this->maxResponseBytes <= 0) {
            throw new InvalidArgumentException('Legacy SP provisioning timeout or payload limit is invalid.');
        }
    }

    public function endpoint(string $path): string
    {
        return rtrim($this->baseUrl, '/').'/'.ltrim($path, '/');
    }
}
