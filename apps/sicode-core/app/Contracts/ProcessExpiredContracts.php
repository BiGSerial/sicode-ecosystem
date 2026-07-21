<?php

declare(strict_types=1);

namespace App\Contracts;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Contract;
use App\Models\ContractStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final class ProcessExpiredContracts
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordCoreAuditEvent = new RecordCoreAuditEvent,
    ) {}

    public function __invoke(
        CarbonInterface $referenceAt,
        bool $dryRun = false,
        ?string $reason = 'Contract period expired.',
    ): ProcessExpiredContractsResult {
        $referenceAtImmutable = $referenceAt->toImmutable();

        $eligibleQuery = Contract::query()
            ->where('status', ContractStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $referenceAtImmutable);

        $eligibleCount = (clone $eligibleQuery)->count();

        if ($dryRun) {
            return new ProcessExpiredContractsResult(
                eligibleCount: $eligibleCount,
                processedCount: 0,
                ignoredCount: 0,
                dryRun: true,
                referenceAt: $referenceAtImmutable,
            );
        }

        $processedCount = 0;
        $ignoredCount = 0;

        /** @var list<string> $eligibleIds */
        $eligibleIds = (clone $eligibleQuery)->pluck('id')->all();

        foreach ($eligibleIds as $contractId) {
            $processed = DB::transaction(function () use ($contractId, $referenceAtImmutable, $reason): bool {
                /** @var Contract|null $contract */
                $contract = Contract::query()
                    ->lockForUpdate()
                    ->find($contractId);

                if (! $contract instanceof Contract) {
                    return false;
                }

                if ($contract->status !== ContractStatus::Active->value || $contract->ends_at === null || $contract->ends_at > $referenceAtImmutable) {
                    return false;
                }

                $previousStatus = $contract->status;
                $contract->status = ContractStatus::Expired->value;
                $contract->save();

                ($this->recordCoreAuditEvent)(new CoreAuditRecord(
                    occurredAt: $referenceAtImmutable,
                    actorType: CoreAuditActorType::System,
                    actorId: null,
                    action: CoreAuditAction::ContractExpired,
                    subjectType: CoreAuditSubjectType::Contract,
                    subjectId: $contract->id,
                    applicationId: null,
                    contextId: null,
                    reason: $reason,
                    correlationId: null,
                    details: [
                        'organization_id' => $contract->organization_id,
                        'previous_status' => $previousStatus,
                        'new_status' => ContractStatus::Expired->value,
                        'starts_at' => $contract->starts_at?->toIso8601String(),
                        'ends_at' => $contract->ends_at->toIso8601String(),
                        'effective_at' => $referenceAtImmutable->toIso8601String(),
                    ],
                ));

                return true;
            });

            if ($processed) {
                $processedCount++;
            } else {
                $ignoredCount++;
            }
        }

        return new ProcessExpiredContractsResult(
            eligibleCount: $eligibleCount,
            processedCount: $processedCount,
            ignoredCount: $ignoredCount,
            dryRun: false,
            referenceAt: $referenceAtImmutable,
        );
    }
}
