<?php

declare(strict_types=1);

namespace App\ApplicationAccesses;

use App\Contracts\ProcessExpiredContracts;
use Carbon\CarbonInterface;

final class ProcessExpiredAccesses
{
    public function __construct(
        private readonly ProcessExpiredContracts $processExpiredContracts = new ProcessExpiredContracts,
        private readonly ProcessExpiredApplicationAccesses $processExpiredApplicationAccesses = new ProcessExpiredApplicationAccesses,
    ) {}

    public function __invoke(
        CarbonInterface $referenceAt,
        bool $dryRun = false,
        ?string $reason = 'Temporal access expiration processing.',
    ): ProcessExpiredAccessesResult {
        $referenceAtImmutable = $referenceAt->toImmutable();

        $contractsResult = ($this->processExpiredContracts)(
            referenceAt: $referenceAtImmutable,
            dryRun: $dryRun,
            reason: $reason,
        );

        $accessesResult = ($this->processExpiredApplicationAccesses)(
            referenceAt: $referenceAtImmutable,
            dryRun: $dryRun,
            reason: $reason,
        );

        return new ProcessExpiredAccessesResult(
            contracts: $contractsResult,
            accesses: $accessesResult,
            dryRun: $dryRun,
            referenceAt: $referenceAtImmutable,
        );
    }
}
