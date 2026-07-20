<?php

declare(strict_types=1);

namespace App\ApplicationEntry;

final readonly class ApplicationEntryDecision
{
    private function __construct(
        public bool $allowed,
        public ApplicationEntryReason $reason,
        public ?string $authorizedOrganizationId,
    ) {}

    public static function allowed(?string $authorizedOrganizationId = null): self
    {
        return new self(true, ApplicationEntryReason::Allowed, $authorizedOrganizationId);
    }

    public static function denied(ApplicationEntryReason $reason): self
    {
        return new self(false, $reason, null);
    }
}
