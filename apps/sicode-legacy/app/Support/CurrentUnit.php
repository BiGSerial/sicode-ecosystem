<?php

namespace App\Support;

final class CurrentUnit
{
    public function __construct(private readonly SicodeUnit $unit)
    {
    }

    public function value(): SicodeUnit
    {
        return $this->unit;
    }

    public function is(SicodeUnit $unit): bool
    {
        return $this->unit === $unit;
    }
}
