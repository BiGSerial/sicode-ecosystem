<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

final readonly class OrganizationProvisioningRequest
{
    public function __construct(
        public string $coreOrganizationId,
        public string $name,
        public string $status,
        public string $idempotencyKey,
        public string $issuer,
        public string $contractVersion,
    ) {}

    /**
     * @return array<string, string>
     */
    public function toPayload(string $clientIdentifier, string $clientSecret): array
    {
        return [
            'client_identifier' => $clientIdentifier,
            'client_secret' => $clientSecret,
            'contract_version' => $this->contractVersion,
            'idempotency_key' => $this->idempotencyKey,
            'core_issuer' => $this->issuer,
            'core_organization_id' => $this->coreOrganizationId,
            'name' => $this->name,
            'status' => $this->status,
        ];
    }
}
