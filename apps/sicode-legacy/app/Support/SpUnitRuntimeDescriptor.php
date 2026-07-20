<?php

namespace App\Support;

final class SpUnitRuntimeDescriptor implements UnitRuntimeDescriptor
{
    public function unit(): SicodeUnit
    {
        return SicodeUnit::SP;
    }

    public function coreContext(): string
    {
        return (string) config('sicode.units.sp.core_context');
    }
}
