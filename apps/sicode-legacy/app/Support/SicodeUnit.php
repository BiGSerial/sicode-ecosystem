<?php

namespace App\Support;

enum SicodeUnit: string
{
    case ES = 'es';
    case SP = 'sp';

    public static function fromRuntimeConfig(mixed $value): self
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidSicodeUnit('SICODE unit is required.');
        }

        $unit = self::tryFrom(strtolower(trim($value)));

        if (! $unit instanceof self) {
            throw new InvalidSicodeUnit('SICODE unit is invalid.');
        }

        return $unit;
    }
}
