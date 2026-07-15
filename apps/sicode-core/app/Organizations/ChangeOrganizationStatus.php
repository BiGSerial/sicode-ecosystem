<?php

declare(strict_types=1);

namespace App\Organizations;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationStatus;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ChangeOrganizationStatus
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        Organization $organization,
        OrganizationStatus $targetStatus,
        CoreAuditActorType $actorType,
        ?string $actorId,
        string $reason,
        ?string $correlationId = null,
    ): Organization {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Organization status changes require a reason.');
        }

        return DB::transaction(function () use ($organization, $targetStatus, $actorType, $actorId, $reason, $correlationId): Organization {
            /** @var Organization $locked */
            $locked = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentStatus = OrganizationStatus::from($locked->status);

            if ($currentStatus === $targetStatus) {
                return $locked;
            }

            $action = match ($targetStatus) {
                OrganizationStatus::Active => CoreAuditAction::OrganizationReactivated,
                OrganizationStatus::Suspended => CoreAuditAction::OrganizationSuspended,
                OrganizationStatus::Disabled => CoreAuditAction::OrganizationDisabled,
            };

            $locked->forceFill(['status' => $targetStatus->value]);
            $locked->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: $action,
                subjectType: CoreAuditSubjectType::Organization,
                subjectId: $locked->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'from_status' => $currentStatus->value,
                    'to_status' => $targetStatus->value,
                ],
            ));

            return $locked;
        });
    }
}
