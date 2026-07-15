<?php

declare(strict_types=1);

namespace App\Models;

enum ContractApplicationGrantStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
}
