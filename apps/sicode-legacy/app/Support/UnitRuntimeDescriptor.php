<?php

namespace App\Support;

interface UnitRuntimeDescriptor
{
    public function unit(): SicodeUnit;

    public function coreContext(): string;
}
