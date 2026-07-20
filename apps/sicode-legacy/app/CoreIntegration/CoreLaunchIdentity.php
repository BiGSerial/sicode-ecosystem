<?php

namespace App\CoreIntegration;

use InvalidArgumentException;

final class CoreLaunchIdentity
{
    public function __construct(
        public readonly string $issuer,
        public readonly string $coreSubject,
        public readonly string $coreOrganizationId,
        public readonly string $application,
        public readonly string $context,
        public readonly string $launchId,
        public readonly string $issuedAt,
        public readonly string $expiresAt,
        public readonly string $state,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromExchangePayload(array $payload): self
    {
        foreach ([
            'iss',
            'core_subject',
            'core_organization_id',
            'application',
            'context',
            'launch_id',
            'issued_at',
            'expires_at',
            'state',
        ] as $key) {
            if (! isset($payload[$key]) || ! is_string($payload[$key]) || $payload[$key] === '') {
                throw new InvalidArgumentException('Invalid CORE launch payload.');
            }
        }

        return new self(
            issuer: $payload['iss'],
            coreSubject: $payload['core_subject'],
            coreOrganizationId: $payload['core_organization_id'],
            application: $payload['application'],
            context: $payload['context'],
            launchId: $payload['launch_id'],
            issuedAt: $payload['issued_at'],
            expiresAt: $payload['expires_at'],
            state: $payload['state'],
        );
    }
}
