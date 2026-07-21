<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

final readonly class LegacyProvisioningHttpResult
{
    /**
     * @param  array<string, mixed>  $technicalData
     */
    public function __construct(
        public LegacyProvisioningOutcome $outcome,
        public int $attempts,
        public ?LegacyProvisioningErrorCategory $errorCategory = null,
        public ?string $remoteLocalId = null,
        public ?string $coreOrganizationId = null,
        public ?string $coreSubject = null,
        public array $technicalData = [],
    ) {}
}
