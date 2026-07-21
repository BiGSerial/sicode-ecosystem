<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

final readonly class LegacyProvisioningActionResult
{
    public function __construct(
        public string $entityType,
        public string $entityId,
        public ?string $organizationId,
        public LegacyProvisioningOutcome $outcome,
        public int $attempts,
        public ?LegacyProvisioningErrorCategory $errorCategory = null,
        public ?string $remoteLocalId = null,
    ) {}

    public function isSuccessful(): bool
    {
        return $this->outcome->isSuccessful();
    }
}
