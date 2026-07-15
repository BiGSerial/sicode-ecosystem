<?php

declare(strict_types=1);

namespace App\LocalAuthentication;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

final class StartLocalSession
{
    public function __construct(
        private readonly AuthenticateLocalUser $authenticateLocalUser,
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        string $identifier,
        #[\SensitiveParameter] string $plainPassword,
        Session $session,
    ): LocalAuthenticationDecision {
        $correlationId = (string) Str::uuid();
        $decision = ($this->authenticateLocalUser)($identifier, $plainPassword);

        if (! $decision->authenticated || $decision->user === null) {
            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: CoreAuditActorType::System,
                actorId: null,
                action: CoreAuditAction::LocalAuthenticationRejected,
                subjectType: CoreAuditSubjectType::LocalAuthenticationAttempt,
                subjectId: $correlationId,
                correlationId: $correlationId,
                details: [
                    'reason' => $decision->reason->value,
                ],
            ));

            return $decision;
        }

        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: now(),
            actorType: CoreAuditActorType::User,
            actorId: $decision->user->id,
            action: CoreAuditAction::LocalAuthenticationSucceeded,
            subjectType: CoreAuditSubjectType::User,
            subjectId: $decision->user->id,
            correlationId: $correlationId,
            details: [
                'rehash_required' => $decision->requiresPasswordRehash,
            ],
        ));

        $session->regenerate();
        $session->put(LocalSession::USER_ID_KEY, $decision->user->id);

        return $decision;
    }
}
