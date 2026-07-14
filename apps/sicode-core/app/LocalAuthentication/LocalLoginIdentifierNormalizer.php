<?php

declare(strict_types=1);

namespace App\LocalAuthentication;

final class LocalLoginIdentifierNormalizer
{
    public function normalize(string $identifier): string
    {
        return strtolower(trim($identifier));
    }
}
