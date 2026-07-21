<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

final readonly class LegacySpAccessProvisioningResult
{
    public function __construct(
        public LegacyProvisioningActionResult $organization,
        public ?LegacyProvisioningActionResult $user,
        public string $overall,
    ) {}
}
