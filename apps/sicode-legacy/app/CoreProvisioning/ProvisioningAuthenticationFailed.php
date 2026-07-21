<?php

namespace App\CoreProvisioning;

final class ProvisioningAuthenticationFailed extends ProvisioningException
{
    public function __construct(string $reason = 'INVALID_CLIENT')
    {
        parent::__construct('Provisioning request rejected.', $reason, ProvisioningOutcome::RESULT_REJECTED);
    }
}
