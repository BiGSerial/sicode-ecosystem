<?php

declare(strict_types=1);

namespace App\LocalAuthentication;

use Illuminate\Contracts\Hashing\Hasher;

final class LocalAuthenticationDummyHash
{
    private const DUMMY_PASSWORD = 'sicode-core-local-authentication-dummy-password';

    /**
     * @var array<class-string, string>
     */
    private static array $hashes = [];

    public function __construct(
        private readonly Hasher $hasher,
    ) {}

    public function hash(): string
    {
        return self::$hashes[$this->hasher::class] ??= $this->hasher->make(self::DUMMY_PASSWORD);
    }
}
