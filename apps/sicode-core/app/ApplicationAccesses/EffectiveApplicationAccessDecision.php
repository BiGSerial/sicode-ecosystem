<?php

declare(strict_types=1);

namespace App\ApplicationAccesses;

use App\Models\ApplicationAccess;

final readonly class EffectiveApplicationAccessDecision
{
    private function __construct(
        public bool $granted,
        public bool $effective,
        public ?ApplicationAccess $access,
    ) {}

    public static function notGranted(): self
    {
        return new self(granted: false, effective: false, access: null);
    }

    public static function notEffective(?ApplicationAccess $access = null): self
    {
        return new self(granted: true, effective: false, access: $access);
    }

    public static function effective(ApplicationAccess $access): self
    {
        return new self(granted: true, effective: true, access: $access);
    }
}
