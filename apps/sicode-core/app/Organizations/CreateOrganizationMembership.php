<?php

declare(strict_types=1);

namespace App\Organizations;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\OrganizationMembershipStatus;
use App\Models\OrganizationStatus;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateOrganizationMembership
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        User $user,
        Organization $organization,
        CarbonInterface $startedAt,
        CoreAuditActorType $actorType,
        ?string $actorId,
        ?string $reason = null,
        ?string $correlationId = null,
    ): OrganizationMembership {
        return DB::transaction(function () use ($user, $organization, $startedAt, $actorType, $actorId, $reason, $correlationId): OrganizationMembership {
            /** @var User $lockedUser */
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Organization $lockedOrganization */
            $lockedOrganization = Organization::query()
                ->whereKey($organization->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedOrganization->status !== OrganizationStatus::Active->value) {
                throw new InvalidArgumentException('Only active organizations can receive active memberships.');
            }

            if ($this->activeMembershipExists($lockedUser, $lockedOrganization)) {
                throw new InvalidArgumentException('An active membership already exists for this user and organization.');
            }

            $membership = new OrganizationMembership([
                'status' => OrganizationMembershipStatus::Active->value,
                'started_at' => $startedAt,
                'ended_at' => null,
            ]);
            $membership->user()->associate($lockedUser);
            $membership->organization()->associate($lockedOrganization);
            $membership->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::OrganizationMembershipCreated,
                subjectType: CoreAuditSubjectType::OrganizationMembership,
                subjectId: $membership->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'user_id' => $lockedUser->id,
                    'organization_id' => $lockedOrganization->id,
                ],
            ));

            return $membership;
        });
    }

    private function activeMembershipExists(User $user, Organization $organization): bool
    {
        return OrganizationMembership::query()
            ->where('user_id', $user->id)
            ->where('organization_id', $organization->id)
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->exists();
    }
}
