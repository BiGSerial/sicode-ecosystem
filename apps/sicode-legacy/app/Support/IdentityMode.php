<?php

namespace App\Support;

enum IdentityMode: string
{
    case RECONCILIATION = 'reconciliation';
    case PROVISIONING = 'provisioning';

    public static function fromRuntimeConfig(mixed $value): self
    {
        if (! is_string($value) || trim($value) === '') {
            throw new InvalidIdentityMode('SICODE identity mode is required.');
        }

        $mode = self::tryFrom(strtolower(trim($value)));

        if (! $mode instanceof self) {
            throw new InvalidIdentityMode('SICODE identity mode is invalid.');
        }

        return $mode;
    }
}
