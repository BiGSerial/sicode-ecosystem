<?php

declare(strict_types=1);

namespace App\ApplicationEntry;

final readonly class ApplicationEntryDecision
{
    private function __construct(
        public bool $allowed,
        public ApplicationEntryReason $reason,
    ) {}

    public static function allowed(): self
    {
        return new self(true, ApplicationEntryReason::Allowed);
    }

    public static function denied(ApplicationEntryReason $reason): self
    {
        return new self(false, $reason);
    }
}
