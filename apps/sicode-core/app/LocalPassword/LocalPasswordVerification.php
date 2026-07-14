<?php

declare(strict_types=1);

namespace App\LocalPassword;

final readonly class LocalPasswordVerification
{
    private function __construct(
        public bool $verified,
        public LocalPasswordVerificationReason $reason,
        public bool $requiresRehash,
    ) {}

    public static function verified(bool $requiresRehash): self
    {
        return new self(true, LocalPasswordVerificationReason::Verified, $requiresRehash);
    }

    public static function denied(LocalPasswordVerificationReason $reason): self
    {
        return new self(false, $reason, false);
    }
}
