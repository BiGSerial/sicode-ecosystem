<?php

namespace App\CoreProvisioning;

final class ProvisioningRejected extends ProvisioningException
{
    public function __construct(string $reason = 'REJECTED')
    {
        parent::__construct('Provisioning request rejected.', $reason, ProvisioningOutcome::RESULT_REJECTED);
    }
}
