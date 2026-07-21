<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\Models\Organization;
use App\Models\User;

final class LegacyProvisioningIdempotencyKeys
{
    public function organization(Organization $organization): string
    {
        return 'organization:'.$organization->getKey().':provision:sp:v1';
    }

    public function user(User $user, Organization $organization): string
    {
        return 'user:'.$user->getKey().':organization:'.$organization->getKey().':provision:sp:v1';
    }

    public function hash(string $key): string
    {
        return hash('sha256', $key);
    }
}
