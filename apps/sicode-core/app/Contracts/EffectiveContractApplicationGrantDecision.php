<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Models\ContractApplicationGrant;

final readonly class EffectiveContractApplicationGrantDecision
{
    private function __construct(
        public bool $contractAvailable,
        public bool $grantEffective,
        public ?ContractApplicationGrant $grant,
    ) {}

    public static function noContract(): self
    {
        return new self(contractAvailable: false, grantEffective: false, grant: null);
    }

    public static function noGrant(): self
    {
        return new self(contractAvailable: true, grantEffective: false, grant: null);
    }

    public static function granted(ContractApplicationGrant $grant): self
    {
        return new self(contractAvailable: true, grantEffective: true, grant: $grant);
    }
}
