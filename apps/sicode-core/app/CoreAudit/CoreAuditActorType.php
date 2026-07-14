<?php

declare(strict_types=1);

namespace App\CoreAudit;

enum CoreAuditActorType: string
{
    case User = 'USER';
    case ApplicationClient = 'APPLICATION_CLIENT';
    case System = 'SYSTEM';
    case LegacyBridge = 'LEGACY_BRIDGE';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
