<?php

namespace App\Support;

final class EsUnitRuntimeDescriptor implements UnitRuntimeDescriptor
{
    public function unit(): SicodeUnit
    {
        return SicodeUnit::ES;
    }

    public function coreContext(): string
    {
        return (string) config('sicode.units.es.core_context');
    }
}
