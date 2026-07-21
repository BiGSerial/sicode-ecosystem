<?php

namespace App\CoreProvisioning;

use RuntimeException;

class ProvisioningException extends RuntimeException
{
    public function __construct(
        string $message = 'Provisioning request rejected.',
        public readonly string $reason = 'REJECTED',
        public readonly string $result = ProvisioningOutcome::RESULT_REJECTED,
    ) {
        parent::__construct($message);
    }
}
