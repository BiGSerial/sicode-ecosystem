<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\Models\Organization;
use App\Models\User;

final class ProvisionLegacySpAccess
{
    public function __construct(
        private readonly ProvisionUserToLegacySp $provisionUser,
    ) {}

    public function __invoke(User $user, Organization $organization): LegacySpAccessProvisioningResult
    {
        return ($this->provisionUser)($user, $organization, ensureOrganization: true);
    }
}
