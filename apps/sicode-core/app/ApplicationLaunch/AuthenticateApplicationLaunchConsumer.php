<?php

declare(strict_types=1);

namespace App\ApplicationLaunch;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\ApplicationClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

final class AuthenticateApplicationLaunchConsumer
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        string $clientIdentifier,
        #[\SensitiveParameter] string $clientSecret,
        CarbonInterface $at,
    ): ApplicationClient {
        /** @var ApplicationClient|null $client */
        $client = ApplicationClient::query()
            ->where('client_identifier', $clientIdentifier)
            ->where('status', 'active')
            ->first();

        $configuredSecret = config('core_launch.client_secrets.'.$clientIdentifier);

        if (! $client instanceof ApplicationClient || ! is_string($configuredSecret) || $configuredSecret === '') {
            $this->auditRejected($client, 'INVALID_CLIENT', $at);

            throw new ApplicationLaunchConsumerAuthenticationFailed('Launch consumer authentication failed.');
        }

        if (! hash_equals($configuredSecret, $clientSecret)) {
            $this->auditRejected($client, 'INVALID_CLIENT_SECRET', $at);

            throw new ApplicationLaunchConsumerAuthenticationFailed('Launch consumer authentication failed.');
        }

        return $client;
    }

    private function auditRejected(?ApplicationClient $client, string $reason, CarbonInterface $at): void
    {
        $correlationId = (string) Str::uuid();

        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: $at,
            actorType: $client instanceof ApplicationClient ? CoreAuditActorType::ApplicationClient : CoreAuditActorType::System,
            actorId: $client?->id,
            action: CoreAuditAction::ApplicationLaunchExchangeRejected,
            subjectType: CoreAuditSubjectType::ApplicationLaunchAttempt,
            subjectId: $correlationId,
            applicationId: $client?->application_id,
            contextId: $client?->context_id,
            reason: $reason,
            correlationId: $correlationId,
        ));
    }
}
