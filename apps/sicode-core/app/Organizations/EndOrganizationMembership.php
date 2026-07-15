<?php

declare(strict_types=1);

namespace App\Organizations;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class EndOrganizationMembership
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        OrganizationMembership $membership,
        CarbonInterface $endedAt,
        CoreAuditActorType $actorType,
        ?string $actorId,
        string $reason,
        ?string $correlationId = null,
    ): OrganizationMembership {
        $reason = trim($reason);

        if ($reason === '') {
            throw new InvalidArgumentException('Ending an organization membership requires a reason.');
        }

        return DB::transaction(function () use ($membership, $endedAt, $actorType, $actorId, $reason, $correlationId): OrganizationMembership {
            /** @var OrganizationMembership $locked */
            $locked = OrganizationMembership::query()
                ->whereKey($membership->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === OrganizationMembershipStatus::Ended->value) {
                return $locked;
            }

            if ($endedAt->lt($locked->started_at)) {
                throw new InvalidArgumentException('Membership end date cannot be before its start date.');
            }

            $previousStatus = $locked->status;

            $locked->forceFill([
                'status' => OrganizationMembershipStatus::Ended->value,
                'ended_at' => $endedAt,
            ]);
            $locked->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::OrganizationMembershipEnded,
                subjectType: CoreAuditSubjectType::OrganizationMembership,
                subjectId: $locked->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'from_status' => $previousStatus,
                    'to_status' => OrganizationMembershipStatus::Ended->value,
                    'user_id' => $locked->user_id,
                    'organization_id' => $locked->organization_id,
                ],
            ));

            return $locked;
        });
    }
}
