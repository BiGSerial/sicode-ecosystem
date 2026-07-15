<?php

declare(strict_types=1);

namespace App\ApplicationLaunch;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\ApplicationClient;
use App\Models\ApplicationLaunch;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class ExchangeApplicationLaunch
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        ApplicationClient $client,
        string $code,
        string $state,
        CarbonInterface $at,
    ): ApplicationLaunchExchangeResult {
        $correlationId = (string) Str::uuid();
        $codeHash = $this->hashPublicValue($code);
        $stateHash = $this->hashPublicValue($state);
        $rejected = false;

        $result = DB::transaction(function () use ($client, $codeHash, $stateHash, $state, $at, $correlationId, &$rejected): ?ApplicationLaunchExchangeResult {
            /** @var ApplicationLaunch|null $launch */
            $launch = ApplicationLaunch::query()
                ->where('token_hash', $codeHash)
                ->with(['application', 'context', 'user'])
                ->lockForUpdate()
                ->first();

            if (! $launch instanceof ApplicationLaunch) {
                $this->recordAttemptRejection(
                    client: $client,
                    reason: 'INVALID_LAUNCH_CODE',
                    correlationId: $correlationId,
                    at: $at,
                );

                $rejected = true;

                return null;
            }

            if ($launch->client_id !== $client->id || $launch->state_hash !== $stateHash) {
                $this->recordLaunchRejection($launch, $client, 'CLIENT_OR_STATE_MISMATCH', $correlationId, $at);

                $rejected = true;

                return null;
            }

            if ($launch->consumed_at !== null) {
                $this->recordLaunchRejection($launch, $client, 'LAUNCH_CODE_REPLAY', $correlationId, $at, replay: true);

                $rejected = true;

                return null;
            }

            $issuedAt = CarbonImmutable::parse((string) $launch->issued_at);
            $expiresAt = CarbonImmutable::parse((string) $launch->expires_at);

            if ($expiresAt->lessThanOrEqualTo($at)) {
                $this->recordLaunchRejection($launch, $client, 'LAUNCH_CODE_EXPIRED', $correlationId, $at);

                $rejected = true;

                return null;
            }

            $launch->forceFill([
                'consumed_at' => $at,
                'consumed_by_client_id' => $client->id,
            ])->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: $at,
                actorType: CoreAuditActorType::ApplicationClient,
                actorId: $client->id,
                action: CoreAuditAction::ApplicationLaunchExchanged,
                subjectType: CoreAuditSubjectType::ApplicationLaunch,
                subjectId: $launch->id,
                applicationId: $launch->application_id,
                contextId: $launch->context_id,
                correlationId: $correlationId,
                details: [
                    'client_id' => $client->id,
                ],
            ));

            return new ApplicationLaunchExchangeResult(
                issuer: (string) config('core_launch.issuer', 'sicode-core'),
                coreSubject: $launch->user_id,
                application: $launch->application->code,
                context: $launch->context?->code,
                launchId: $launch->id,
                issuedAt: $issuedAt,
                expiresAt: $expiresAt,
                state: $state,
            );
        });

        if ($rejected || ! $result instanceof ApplicationLaunchExchangeResult) {
            throw new ApplicationLaunchExchangeRejected('Launch exchange rejected.');
        }

        return $result;
    }

    private function recordAttemptRejection(
        ApplicationClient $client,
        string $reason,
        string $correlationId,
        CarbonInterface $at,
    ): void {
        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: $at,
            actorType: CoreAuditActorType::ApplicationClient,
            actorId: $client->id,
            action: CoreAuditAction::ApplicationLaunchExchangeRejected,
            subjectType: CoreAuditSubjectType::ApplicationLaunchAttempt,
            subjectId: $correlationId,
            applicationId: $client->application_id,
            contextId: $client->context_id,
            reason: $reason,
            correlationId: $correlationId,
        ));
    }

    private function recordLaunchRejection(
        ApplicationLaunch $launch,
        ApplicationClient $client,
        string $reason,
        string $correlationId,
        CarbonInterface $at,
        bool $replay = false,
    ): void {
        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: $at,
            actorType: CoreAuditActorType::ApplicationClient,
            actorId: $client->id,
            action: $replay ? CoreAuditAction::ApplicationLaunchReplayRejected : CoreAuditAction::ApplicationLaunchExchangeRejected,
            subjectType: CoreAuditSubjectType::ApplicationLaunch,
            subjectId: $launch->id,
            applicationId: $launch->application_id,
            contextId: $launch->context_id,
            reason: $reason,
            correlationId: $correlationId,
            details: [
                'client_id' => $client->id,
            ],
        ));
    }

    private function hashPublicValue(string $value): string
    {
        return hash('sha256', $value);
    }
}
