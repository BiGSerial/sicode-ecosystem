<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

final readonly class UserProvisioningRequest
{
    public function __construct(
        public string $coreSubject,
        public string $coreOrganizationId,
        public string $name,
        public ?string $email,
        public string $status,
        public string $idempotencyKey,
        public string $issuer,
        public string $contractVersion,
    ) {}

    /**
     * @return array<string, string|null>
     */
    public function toPayload(string $clientIdentifier, string $clientSecret): array
    {
        return [
            'client_identifier' => $clientIdentifier,
            'client_secret' => $clientSecret,
            'contract_version' => $this->contractVersion,
            'idempotency_key' => $this->idempotencyKey,
            'core_issuer' => $this->issuer,
            'core_subject' => $this->coreSubject,
            'core_organization_id' => $this->coreOrganizationId,
            'name' => $this->name,
            'email' => $this->email,
            'status' => $this->status,
        ];
    }
}
