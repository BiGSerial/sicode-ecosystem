<?php

namespace App\CoreProvisioning;

use Illuminate\Support\Facades\DB;

final class ProvisioningLock
{
    /**
     * @template T
     * @param callable(): T $callback
     * @return T
     */
    public function withLock(string $lockKey, callable $callback): mixed
    {
        $timeoutSeconds = max(1, (int) config('core_provisioning.lock_timeout_seconds', 5));

        $acquired = DB::selectOne('SELECT GET_LOCK(?, ?) AS acquired', [$lockKey, $timeoutSeconds]);

        if (! $this->isLockAcquired($acquired)) {
            throw new ProvisioningRejected('LOCK_TIMEOUT');
        }

        try {
            return $callback();
        } finally {
            DB::selectOne('SELECT RELEASE_LOCK(?)', [$lockKey]);
        }
    }

    private function isLockAcquired(mixed $result): bool
    {
        if (! is_object($result)) {
            return false;
        }

        $value = $result->acquired ?? null;

        return $value === 1 || $value === '1';
    }
}
