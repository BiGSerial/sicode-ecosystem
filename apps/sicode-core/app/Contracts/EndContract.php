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
use InvalidArgumentException;

final class EndContract
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        Contract $contract,
        CarbonInterface $endedAt,
        CoreAuditActorType $actorType,
        ?string $actorId,
        string $reason,
        ?string $correlationId = null,
    ): Contract {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Ending a contract requires a reason.');
        }

        return DB::transaction(function () use ($contract, $endedAt, $actorType, $actorId, $reason, $correlationId): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($endedAt->lt($locked->starts_at)) {
                throw new InvalidArgumentException('Contract end date cannot be before its start date.');
            }

            if ($locked->status === ContractStatus::Ended->value) {
                return $locked;
            }

            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => ContractStatus::Ended->value,
                'ends_at' => $endedAt,
            ]);
            $locked->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::ContractEnded,
                subjectType: CoreAuditSubjectType::Contract,
                subjectId: $locked->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'organization_id' => $locked->organization_id,
                    'from_status' => $previousStatus,
                    'to_status' => ContractStatus::Ended->value,
                ],
            ));

            return $locked;
        });
    }
}
