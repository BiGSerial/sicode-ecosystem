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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ChangeContractStatus
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        Contract $contract,
        ContractStatus $targetStatus,
        CoreAuditActorType $actorType,
        ?string $actorId,
        string $reason,
        ?string $correlationId = null,
    ): Contract {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Contract status changes require a reason.');
        }

        if ($targetStatus === ContractStatus::Draft || $targetStatus === ContractStatus::Ended) {
            throw new InvalidArgumentException('Use contract creation or ending flows for draft and ended states.');
        }

        return DB::transaction(function () use ($contract, $targetStatus, $actorType, $actorId, $reason, $correlationId): Contract {
            /** @var Contract $locked */
            $locked = Contract::query()
                ->whereKey($contract->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = ContractStatus::from($locked->status);

            if ($currentStatus === ContractStatus::Ended) {
                throw new InvalidArgumentException('Ended contracts cannot change status.');
            }

            if ($currentStatus === $targetStatus) {
                return $locked;
            }

            if ($targetStatus === ContractStatus::Active) {
                $action = $currentStatus === ContractStatus::Draft
                    ? CoreAuditAction::ContractActivated
                    : CoreAuditAction::ContractReactivated;
            } else {
                $action = CoreAuditAction::ContractSuspended;
            }

            $locked->forceFill(['status' => $targetStatus->value]);
            $locked->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: $action,
                subjectType: CoreAuditSubjectType::Contract,
                subjectId: $locked->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'organization_id' => $locked->organization_id,
                    'from_status' => $currentStatus->value,
                    'to_status' => $targetStatus->value,
                ],
            ));

            return $locked;
        });
    }
}
