<?php

declare(strict_types=1);

namespace App\Models;

enum ApplicationAccessStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Revoked = 'revoked';
    case Expired = 'expired';
}
