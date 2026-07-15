<?php

declare(strict_types=1);

namespace App\ApplicationAccesses;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Application;
use App\Models\ApplicationAccess;
use App\Models\ApplicationAccessStatus;
use App\Models\ApplicationContext;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class GrantApplicationAccess
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
        private readonly ResolveEffectiveApplicationAccess $resolveEffectiveApplicationAccess,
    ) {}

    public function __invoke(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $startsAt,
        ?CarbonInterface $endsAt,
        CoreAuditActorType $actorType,
        ?string $actorId,
        ?string $reason = null,
        ?string $correlationId = null,
    ): ApplicationAccess {
        if ($endsAt !== null && $endsAt->lt($startsAt)) {
            throw new InvalidArgumentException('Access end date cannot be before its start date.');
        }

        return DB::transaction(function () use ($user, $application, $context, $startsAt, $endsAt, $actorType, $actorId, $reason, $correlationId): ApplicationAccess {
            /** @var User $lockedUser */
            $lockedUser = User::query()
                ->whereKey($user->id)
                ->lockForUpdate()
                ->firstOrFail();

            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()
                ->whereKey($application->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedApplication->status !== 'active') {
                throw new InvalidArgumentException('Only active applications can receive individual access grants.');
            }

            $lockedContext = null;
            if ($context instanceof ApplicationContext) {
                /** @var ApplicationContext $lockedContext */
                $lockedContext = ApplicationContext::query()
                    ->whereKey($context->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if ($lockedContext->application_id !== $lockedApplication->id) {
                    throw new InvalidArgumentException('Access context must belong to the granted application.');
                }

                if ($lockedContext->status !== 'active') {
                    throw new InvalidArgumentException('Only active application contexts can receive individual access grants.');
                }
            }

            if ($this->activeAccessExists($lockedUser, $lockedApplication, $lockedContext)) {
                throw new InvalidArgumentException('An active equivalent application access already exists for this user.');
            }

            $access = new ApplicationAccess([
                'status' => ApplicationAccessStatus::Active->value,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
            $access->user()->associate($lockedUser);
            $access->application()->associate($lockedApplication);
            $access->context()->associate($lockedContext);
            $access->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::ApplicationAccessGranted,
                subjectType: CoreAuditSubjectType::ApplicationAccess,
                subjectId: $access->id,
                applicationId: $lockedApplication->id,
                contextId: $lockedContext?->id,
                reason: $reason,
                correlationId: $correlationId,
                details: [
                    'user_id' => $lockedUser->id,
                    'application_id' => $lockedApplication->id,
                    'context_id' => $lockedContext?->id,
                ],
            ));

            return $access;
        });
    }

    private function activeAccessExists(User $user, Application $application, ?ApplicationContext $context): bool
    {
        return $this->resolveEffectiveApplicationAccess
            ->queryEquivalent($user, $application, $context)
            ->where('status', ApplicationAccessStatus::Active->value)
            ->exists();
    }
}
