<?php

declare(strict_types=1);

namespace App\ApplicationAccesses;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\ApplicationAccess;
use App\Models\ApplicationAccessStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RevokeApplicationAccess
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        ApplicationAccess $access,
        CarbonInterface $revokedAt,
        CoreAuditActorType $actorType,
        ?string $actorId,
        string $reason,
        ?string $correlationId = null,
    ): ApplicationAccess {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Revoking application access requires a reason.');
        }

        return DB::transaction(function () use ($access, $revokedAt, $actorType, $actorId, $reason, $correlationId): ApplicationAccess {
            /** @var ApplicationAccess $locked */
            $locked = ApplicationAccess::query()
                ->whereKey($access->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($revokedAt->lt($locked->starts_at)) {
                throw new InvalidArgumentException('Access revoke date cannot be before its start date.');
            }

            if ($locked->status === ApplicationAccessStatus::Revoked->value) {
                return $locked;
            }

            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => ApplicationAccessStatus::Revoked->value,
                'ends_at' => $revokedAt,
            ]);
            $locked->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::ApplicationAccessRevoked,
                subjectType: CoreAuditSubjectType::ApplicationAccess,
                subjectId: $locked->id,
                applicationId: $locked->application_id,
                contextId: $locked->context_id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'user_id' => $locked->user_id,
                    'application_id' => $locked->application_id,
                    'context_id' => $locked->context_id,
                    'from_status' => $previousStatus,
                    'to_status' => ApplicationAccessStatus::Revoked->value,
                ],
            ));

            return $locked;
        });
    }
}
