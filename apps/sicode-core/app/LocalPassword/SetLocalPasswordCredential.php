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
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Contracts\Validation\Factory as ValidatorFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SetLocalPasswordCredential
{
    public function __construct(
        private readonly Hasher $hasher,
        private readonly ValidatorFactory $validator,
        private readonly LocalPasswordPolicy $passwordPolicy,
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    /**
     * @throws ValidationException
     */
    public function __invoke(
        User $user,
        #[\SensitiveParameter] string $plainPassword,
        CoreAuditActorType $actorType,
        ?string $actorId,
        ?string $reason = null,
        ?string $correlationId = null,
    ): LocalPasswordCredential {
        $this->validator->make(
            ['password' => $plainPassword],
            ['password' => $this->passwordPolicy->rules()],
        )->validate();

        return DB::transaction(function () use ($user, $plainPassword, $actorType, $actorId, $reason, $correlationId): LocalPasswordCredential {
            User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            $credential = LocalPasswordCredential::query()
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            $action = CoreAuditAction::LocalPasswordCredentialChanged;

            if (! $credential instanceof LocalPasswordCredential) {
                $credential = new LocalPasswordCredential;
                $credential->user()->associate($user);
                $action = CoreAuditAction::LocalPasswordCredentialCreated;
            }

            $credential->forceFill([
                'password_hash' => $this->hasher->make($plainPassword),
                'status' => LocalPasswordCredentialStatus::Active->value,
                'password_changed_at' => now(),
                'invalidated_at' => null,
            ]);
            $credential->save();

            ($this->recordAuditEvent)(new CoreAuditRecord(
                occurredAt: now(),
                actorType: $actorType,
                actorId: $actorId,
                action: $action,
                subjectType: CoreAuditSubjectType::LocalPasswordCredential,
                subjectId: $credential->id,
                reason: $reason,
                correlationId: $correlationId,
            ));

            return $credential;
        });
    }
}
