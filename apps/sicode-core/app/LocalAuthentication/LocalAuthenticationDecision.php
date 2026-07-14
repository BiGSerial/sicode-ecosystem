<?php

declare(strict_types=1);

namespace App\LocalAuthentication;

use App\Models\User;
use InvalidArgumentException;

final readonly class LocalAuthenticationDecision
{
    private function __construct(
        public bool $authenticated,
        public LocalAuthenticationReason $reason,
        public ?User $user,
        public bool $requiresPasswordRehash,
    ) {}

    public static function authenticated(User $user, bool $requiresPasswordRehash): self
    {
        return new self(
            authenticated: true,
            reason: LocalAuthenticationReason::Authenticated,
            user: $user,
            requiresPasswordRehash: $requiresPasswordRehash,
        );
    }

    public static function denied(LocalAuthenticationReason $reason): self
    {
        if ($reason === LocalAuthenticationReason::Authenticated) {
            throw new InvalidArgumentException('Authenticated decisions require a User.');
        }

        return new self(
            authenticated: false,
            reason: $reason,
            user: null,
            requiresPasswordRehash: false,
        );
    }
}
