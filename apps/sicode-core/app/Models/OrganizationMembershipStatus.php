<?php

declare(strict_types=1);

namespace App\Models;

enum OrganizationMembershipStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';
}
