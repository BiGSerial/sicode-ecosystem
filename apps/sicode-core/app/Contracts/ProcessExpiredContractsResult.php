<?php

declare(strict_types=1);

namespace App\Contracts;

use Carbon\CarbonImmutable;

final readonly class ProcessExpiredContractsResult
{
    public function __construct(
        public int $eligibleCount,
        public int $processedCount,
        public int $ignoredCount,
        public bool $dryRun,
        public CarbonImmutable $referenceAt,
    ) {}
}
