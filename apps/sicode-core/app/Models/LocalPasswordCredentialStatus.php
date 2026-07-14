<?php

declare(strict_types=1);

namespace App\Models;

enum LocalPasswordCredentialStatus: string
{
    case Active = 'active';
    case Disabled = 'disabled';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
