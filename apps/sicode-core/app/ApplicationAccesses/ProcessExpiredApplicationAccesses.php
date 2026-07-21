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

final class ProcessExpiredApplicationAccesses
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordCoreAuditEvent = new RecordCoreAuditEvent,
    ) {}

    public function __invoke(
        CarbonInterface $referenceAt,
        bool $dryRun = false,
        ?string $reason = 'Application access period expired.',
    ): ProcessExpiredApplicationAccessesResult {
        $referenceAtImmutable = $referenceAt->toImmutable();

        $eligibleQuery = ApplicationAccess::query()
            ->where('status', ApplicationAccessStatus::Active->value)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $referenceAtImmutable);

        $eligibleCount = (clone $eligibleQuery)->count();

        if ($dryRun) {
            return new ProcessExpiredApplicationAccessesResult(
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

        foreach ($eligibleIds as $accessId) {
            $processed = DB::transaction(function () use ($accessId, $referenceAtImmutable, $reason): bool {
                /** @var ApplicationAccess|null $access */
                $access = ApplicationAccess::query()
                    ->lockForUpdate()
                    ->find($accessId);

                if (! $access instanceof ApplicationAccess) {
                    return false;
                }

                if ($access->status !== ApplicationAccessStatus::Active->value || $access->ends_at === null || $access->ends_at > $referenceAtImmutable) {
                    return false;
                }

                $previousStatus = $access->status;
                $access->status = ApplicationAccessStatus::Expired->value;
                $access->save();

                ($this->recordCoreAuditEvent)(new CoreAuditRecord(
                    occurredAt: $referenceAtImmutable,
                    actorType: CoreAuditActorType::System,
                    actorId: null,
                    action: CoreAuditAction::ApplicationAccessExpired,
                    subjectType: CoreAuditSubjectType::ApplicationAccess,
                    subjectId: $access->id,
                    applicationId: $access->application_id,
                    contextId: $access->context_id,
                    reason: $reason,
                    correlationId: null,
                    details: [
                        'user_id' => $access->user_id,
                        'application_id' => $access->application_id,
                        'context_id' => $access->context_id,
                        'previous_status' => $previousStatus,
                        'new_status' => ApplicationAccessStatus::Expired->value,
                        'starts_at' => $access->starts_at?->toIso8601String(),
                        'ends_at' => $access->ends_at->toIso8601String(),
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

        return new ProcessExpiredApplicationAccessesResult(
            eligibleCount: $eligibleCount,
            processedCount: $processedCount,
            ignoredCount: $ignoredCount,
            dryRun: false,
            referenceAt: $referenceAtImmutable,
        );
    }
}
