<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\Contract;

final readonly class EffectiveContractDecision
{
    private function __construct(
        public bool $resolved,
        public bool $ambiguous,
        public ?Contract $contract,
    ) {}

    public static function none(): self
    {
        return new self(resolved: false, ambiguous: false, contract: null);
    }

    public static function ambiguous(): self
    {
        return new self(resolved: false, ambiguous: true, contract: null);
    }

    public static function resolved(Contract $contract): self
    {
        return new self(resolved: true, ambiguous: false, contract: $contract);
    }
}
