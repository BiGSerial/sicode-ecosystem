<?php

declare(strict_types=1);

namespace App\ApplicationAccesses;

use App\Contracts\ProcessExpiredContractsResult;
use Carbon\CarbonImmutable;

final readonly class ProcessExpiredAccessesResult
{
    public function __construct(
        public ProcessExpiredContractsResult $contracts,
        public ProcessExpiredApplicationAccessesResult $accesses,
        public bool $dryRun,
        public CarbonImmutable $referenceAt,
    ) {}
}
