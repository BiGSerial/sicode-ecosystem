<?php

declare(strict_types=1);

namespace App\Organizations;

use App\Models\OrganizationMembership;

final readonly class EffectiveOrganizationMembershipDecision
{
    private function __construct(
        public bool $resolved,
        public bool $ambiguous,
        public ?OrganizationMembership $membership,
    ) {}

    public static function none(): self
    {
        return new self(resolved: false, ambiguous: false, membership: null);
    }

    public static function ambiguous(): self
    {
        return new self(resolved: false, ambiguous: true, membership: null);
    }

    public static function resolved(OrganizationMembership $membership): self
    {
        return new self(resolved: true, ambiguous: false, membership: $membership);
    }
}
