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
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ChangeApplicationAccessStatus
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        ApplicationAccess $access,
        ApplicationAccessStatus $targetStatus,
        CoreAuditActorType $actorType,
        ?string $actorId,
        string $reason,
        ?string $correlationId = null,
    ): ApplicationAccess {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Application access status changes require a reason.');
        }

        if ($targetStatus === ApplicationAccessStatus::Revoked) {
            throw new InvalidArgumentException('Use application access revocation flow for revoked state.');
        }

        return DB::transaction(function () use ($access, $targetStatus, $actorType, $actorId, $reason, $correlationId): ApplicationAccess {
            /** @var ApplicationAccess $locked */
            $locked = ApplicationAccess::query()
                ->whereKey($access->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = ApplicationAccessStatus::from($locked->status);

            if ($currentStatus === ApplicationAccessStatus::Revoked) {
                throw new InvalidArgumentException('Revoked application access cannot change status.');
            }

            if ($currentStatus === $targetStatus) {
                return $locked;
            }

            if ($targetStatus === ApplicationAccessStatus::Active && $this->otherActiveEquivalentExists($locked)) {
                throw new InvalidArgumentException('An active equivalent application access already exists for this user.');
            }

            $locked->forceFill(['status' => $targetStatus->value]);
            $locked->save();

            $action = $targetStatus === ApplicationAccessStatus::Active
                ? CoreAuditAction::ApplicationAccessReactivated
                : CoreAuditAction::ApplicationAccessSuspended;

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: $action,
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
                    'from_status' => $currentStatus->value,
                    'to_status' => $targetStatus->value,
                ],
            ));

            return $locked;
        });
    }

    private function otherActiveEquivalentExists(ApplicationAccess $access): bool
    {
        return ApplicationAccess::query()
            ->whereKeyNot($access->id)
            ->where('user_id', $access->user_id)
            ->where('application_id', $access->application_id)
            ->when(
                $access->context_id !== null,
                fn ($query) => $query->where('context_id', $access->context_id),
                fn ($query) => $query->whereNull('context_id'),
            )
            ->where('status', ApplicationAccessStatus::Active->value)
            ->exists();
    }
}
