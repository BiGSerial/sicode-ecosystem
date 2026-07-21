<?php

namespace App\CoreProvisioning;

final class ProvisioningConflict extends ProvisioningException
{
    public function __construct(string $reason = 'CONFLICT')
    {
        parent::__construct('Provisioning request rejected.', $reason, ProvisioningOutcome::RESULT_CONFLICT);
    }
}
