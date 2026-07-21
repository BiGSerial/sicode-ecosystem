<?php

namespace App\CoreProvisioning;

use App\Support\CurrentUnit;
use App\Support\IdentityMode;
use App\Support\SicodeUnit;

final class EnsureProvisioningRuntime
{
    public function __construct(
        private readonly CurrentUnit $currentUnit,
        private readonly IdentityMode $identityMode,
    ) {
    }

    public function assertEnabled(): void
    {
        if (! $this->currentUnit->is(SicodeUnit::SP)) {
            throw new ProvisioningRejected('UNIT_REJECTED');
        }

        if ($this->identityMode !== IdentityMode::PROVISIONING) {
            throw new ProvisioningRejected('IDENTITY_MODE_REJECTED');
        }
    }
}
