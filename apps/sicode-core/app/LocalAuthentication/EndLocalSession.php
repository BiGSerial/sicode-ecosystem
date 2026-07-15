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

final class EndLocalSession
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(Session $session): void
    {
        $userId = $session->get(LocalSession::USER_ID_KEY);

        if (is_string($userId) && Str::isUuid($userId)) {
            $correlationId = (string) Str::uuid();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: CoreAuditActorType::User,
                actorId: $userId,
                action: CoreAuditAction::LocalSessionEnded,
                subjectType: CoreAuditSubjectType::User,
                subjectId: $userId,
                correlationId: $correlationId,
            ));
        }

        $session->invalidate();
        $session->regenerateToken();
    }
}
