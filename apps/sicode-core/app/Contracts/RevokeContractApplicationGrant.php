<?php

declare(strict_types=1);

namespace App\Contracts;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\ContractApplicationGrant;
use App\Models\ContractApplicationGrantStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RevokeContractApplicationGrant
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        ContractApplicationGrant $grant,
        CarbonInterface $revokedAt,
        CoreAuditActorType $actorType,
        ?string $actorId,
        string $reason,
        ?string $correlationId = null,
    ): ContractApplicationGrant {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Revoking a contract application grant requires a reason.');
        }

        return DB::transaction(function () use ($grant, $revokedAt, $actorType, $actorId, $reason, $correlationId): ContractApplicationGrant {
            /** @var ContractApplicationGrant $locked */
            $locked = ContractApplicationGrant::query()
                ->whereKey($grant->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($revokedAt->lt($locked->starts_at)) {
                throw new InvalidArgumentException('Grant revoke date cannot be before its start date.');
            }

            if ($locked->status === ContractApplicationGrantStatus::Revoked->value) {
                return $locked;
            }

            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => ContractApplicationGrantStatus::Revoked->value,
                'ends_at' => $revokedAt,
            ]);
            $locked->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::ContractApplicationGrantRevoked,
                subjectType: CoreAuditSubjectType::ContractApplicationGrant,
                subjectId: $locked->id,
                applicationId: $locked->application_id,
                contextId: $locked->context_id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'contract_id' => $locked->contract_id,
                    'application_id' => $locked->application_id,
                    'context_id' => $locked->context_id,
                    'from_status' => $previousStatus,
                    'to_status' => ContractApplicationGrantStatus::Revoked->value,
                ],
            ));

            return $locked;
        });
    }
}
