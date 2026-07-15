<?php

declare(strict_types=1);

namespace App\Models;

enum ContractStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';
}
