<?php

namespace App\CoreIntegration;

use App\Models\User;

final class LegacyCompanyAccessResolver
{
    public function canOperateForCompany(User $user, string $companyId): bool
    {
        if ($user->company_id === $companyId) {
            return true;
        }

        if ($user->Companies()->whereKey($companyId)->exists()) {
            return true;
        }

        return $user->Employee()
            ->whereHas('Contract', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->exists();
    }
}
