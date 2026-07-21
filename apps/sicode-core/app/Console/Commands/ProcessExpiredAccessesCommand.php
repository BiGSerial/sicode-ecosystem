<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\ApplicationAccesses\ProcessExpiredAccesses;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

final class ProcessExpiredAccessesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'core:process-expired-accesses {--dry-run} {--at=}';

    /**
     * @var string
     */
    protected $description = 'Process temporal expiration for contracts and application accesses.';

    public function handle(ProcessExpiredAccesses $processExpiredAccesses): int
    {
        $atOption = $this->option('at');
        $referenceAt = CarbonImmutable::now();

        if (is_string($atOption) && trim($atOption) !== '') {
            try {
                $referenceAt = CarbonImmutable::parse($atOption);
            } catch (\Throwable) {
                $this->error('Invalid --at timestamp provided.');

                return 1;
            }
        }

        $dryRun = (bool) $this->option('dry-run');

        $result = $processExpiredAccesses(
            referenceAt: $referenceAt,
            dryRun: $dryRun,
        );

        $this->line('dry_run='.($result->dryRun ? 'true' : 'false'));
        $this->line('reference_at='.$result->referenceAt->toIso8601String());
        $this->line('contracts_eligible='.$result->contracts->eligibleCount);
        $this->line('contracts_processed='.$result->contracts->processedCount);
        $this->line('contracts_ignored='.$result->contracts->ignoredCount);
        $this->line('accesses_eligible='.$result->accesses->eligibleCount);
        $this->line('accesses_processed='.$result->accesses->processedCount);
        $this->line('accesses_ignored='.$result->accesses->ignoredCount);

        return 0;
    }
}
