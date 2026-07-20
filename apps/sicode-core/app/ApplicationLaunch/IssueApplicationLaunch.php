<?php

declare(strict_types=1);

namespace App\ApplicationLaunch;

use App\ApplicationEntry\EvaluateApplicationEntry;
use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\Application;
use App\Models\ApplicationContext;
use App\Models\ApplicationLaunch;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class IssueApplicationLaunch
{
    public function __construct(
        private readonly EvaluateApplicationEntry $evaluateApplicationEntry,
        private readonly ResolveApplicationLaunchClient $resolveApplicationLaunchClient,
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        CarbonInterface $at,
    ): ApplicationLaunchRedirect {
        $correlationId = (string) Str::uuid();
        $decision = ($this->evaluateApplicationEntry)($user, $application, $context, $at);

        if (! $decision->allowed) {
            $this->auditRejected(
                user: $user,
                application: $application,
                context: $context,
                reason: $decision->reason->value,
                correlationId: $correlationId,
                at: $at,
            );

            throw new ApplicationLaunchRejected('Application launch denied.');
        }

        $client = ($this->resolveApplicationLaunchClient)($application, $context);
        $callbackUrl = $client === null ? null : $this->resolveApplicationLaunchClient->firstRedirectUri($client);

        if ($client === null || $callbackUrl === null) {
            $this->auditRejected(
                user: $user,
                application: $application,
                context: $context,
                reason: 'LAUNCH_CLIENT_NOT_CONFIGURED',
                correlationId: $correlationId,
                at: $at,
            );

            throw new ApplicationLaunchRejected('Application launch is not configured.');
        }

        $code = bin2hex(random_bytes(32));
        $state = bin2hex(random_bytes(32));
        $ttlSeconds = max(60, (int) config('core_launch.ttl_seconds', 300));

        return DB::transaction(function () use ($user, $application, $context, $at, $client, $callbackUrl, $code, $state, $ttlSeconds, $correlationId): ApplicationLaunchRedirect {
            /** @var User $lockedUser */
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();
            /** @var Application $lockedApplication */
            $lockedApplication = Application::query()->whereKey($application->id)->lockForUpdate()->firstOrFail();
            $lockedContext = null;

            if ($context instanceof ApplicationContext) {
                /** @var ApplicationContext $lockedContext */
                $lockedContext = ApplicationContext::query()->whereKey($context->id)->lockForUpdate()->firstOrFail();
            }

            $decision = ($this->evaluateApplicationEntry)($lockedUser, $lockedApplication, $lockedContext, $at);

            if (! $decision->allowed) {
                $this->recordRejectedAudit(
                    user: $lockedUser,
                    application: $lockedApplication,
                    context: $lockedContext,
                    reason: $decision->reason->value,
                    correlationId: $correlationId,
                    at: $at,
                );

                throw new ApplicationLaunchRejected('Application launch denied.');
            }

            $expiresAt = CarbonImmutable::instance($at)->addSeconds($ttlSeconds);

            $launch = new ApplicationLaunch([
                'token_hash' => $this->hashPublicValue($code),
                'state_hash' => $this->hashPublicValue($state),
                'callback_url' => $callbackUrl,
                'authorized_organization_id' => $decision->authorizedOrganizationId,
                'issued_at' => $at,
                'expires_at' => $expiresAt,
            ]);
            $launch->user()->associate($lockedUser);
            $launch->application()->associate($lockedApplication);
            $launch->context()->associate($lockedContext);
            $launch->client()->associate($client);
            $launch->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: $at,
                actorType: CoreAuditActorType::User,
                actorId: $lockedUser->id,
                action: CoreAuditAction::ApplicationLaunchIssued,
                subjectType: CoreAuditSubjectType::ApplicationLaunch,
                subjectId: $launch->id,
                applicationId: $lockedApplication->id,
                contextId: $lockedContext?->id,
                correlationId: $correlationId,
                details: [
                    'client_id' => $client->id,
                    'callback_host' => (string) parse_url($callbackUrl, PHP_URL_HOST),
                    'code_fingerprint' => $this->fingerprint($code),
                    'expires_at' => $expiresAt->toJSON(),
                ],
            ));

            return new ApplicationLaunchRedirect(
                launchId: $launch->id,
                callbackUrl: $callbackUrl,
                code: $code,
                state: $state,
            );
        });
    }

    private function auditRejected(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        string $reason,
        string $correlationId,
        CarbonInterface $at,
    ): void {
        DB::transaction(function () use ($user, $application, $context, $reason, $correlationId, $at): void {
            $this->recordRejectedAudit($user, $application, $context, $reason, $correlationId, $at);
        });
    }

    private function recordRejectedAudit(
        User $user,
        Application $application,
        ?ApplicationContext $context,
        string $reason,
        string $correlationId,
        CarbonInterface $at,
    ): void {
        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: $at,
            actorType: CoreAuditActorType::User,
            actorId: $user->id,
            action: CoreAuditAction::ApplicationLaunchRejected,
            subjectType: CoreAuditSubjectType::ApplicationLaunchAttempt,
            subjectId: $correlationId,
            applicationId: $application->id,
            contextId: $context?->id,
            reason: $reason,
            correlationId: $correlationId,
        ));
    }

    private function hashPublicValue(string $value): string
    {
        return hash('sha256', $value);
    }

    private function fingerprint(string $value): string
    {
        return hash('sha256', 'audit-fingerprint:'.$value);
    }
}
