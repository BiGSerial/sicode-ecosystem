<?php

declare(strict_types=1);

namespace App\LocalPassword;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use App\Models\LocalPasswordCredential;
use App\Models\LocalPasswordCredentialStatus;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class DisableLocalPasswordCredential
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function __invoke(
        User $user,
        string $reason,
        CoreAuditActorType $actorType,
        ?string $actorId,
        ?string $correlationId = null,
    ): LocalPasswordCredential {
        if (trim($reason) === '') {
            throw new InvalidArgumentException('Disabling a local password credential requires a reason.');
        }

        return DB::transaction(function () use ($user, $reason, $actorType, $actorId, $correlationId): LocalPasswordCredential {
            $credential = LocalPasswordCredential::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->firstOrFail();

            $credential->forceFill([
                'status' => LocalPasswordCredentialStatus::Disabled->value,
                'invalidated_at' => now(),
            ]);
            $credential->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: CoreAuditAction::LocalPasswordCredentialDisabled,
                subjectType: CoreAuditSubjectType::LocalPasswordCredential,
                subjectId: $credential->id,
                reason: $reason,
                correlationId: $correlationId,
            ));

            return $credential;
        });
    }
}
