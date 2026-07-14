<?php

declare(strict_types=1);

namespace App\CoreAudit;

use App\Models\CoreAuditEvent;
use InvalidArgumentException;
use JsonException;

final class RecordCoreAuditEvent
{
    private const int MAX_REASON_BYTES = 500;

    private const int MAX_DETAILS_BYTES = 8192;

    private const array SENSITIVE_DETAIL_KEY_FRAGMENTS = [
        'authorization',
        'client_secret',
        'cookie',
        'password',
        'secret',
        'token',
    ];

    public function __invoke(CoreAuditRecord $record): CoreAuditEvent
    {
        $this->validateActor($record);
        $this->validateReason($record);
        $this->validateDetails($record);

        return CoreAuditEvent::create([
            'occurred_at' => $record->occurredAt,
            'actor_type' => $record->actorType->value,
            'actor_id' => $record->actorId,
            'action' => $record->action->value,
            'subject_type' => $record->subjectType->value,
            'subject_id' => $record->subjectId,
            'application_id' => $record->applicationId,
            'context_id' => $record->contextId,
            'reason' => $record->reason,
            'correlation_id' => $record->correlationId,
            'details' => $record->details,
        ]);
    }

    private function validateActor(CoreAuditRecord $record): void
    {
        if ($record->actorType === CoreAuditActorType::System && $record->actorId !== null) {
            throw new InvalidArgumentException('SYSTEM audit actor must not have actor_id.');
        }

        if ($record->actorType !== CoreAuditActorType::System && $record->actorId === null) {
            throw new InvalidArgumentException('Identifiable audit actor requires actor_id.');
        }
    }

    private function validateReason(CoreAuditRecord $record): void
    {
        if ($record->reason !== null && strlen($record->reason) > self::MAX_REASON_BYTES) {
            throw new InvalidArgumentException('Audit reason exceeds the allowed size.');
        }
    }

    private function validateDetails(CoreAuditRecord $record): void
    {
        if ($record->details === null) {
            return;
        }

        if (array_is_list($record->details)) {
            throw new InvalidArgumentException('Audit details root must be a JSON object.');
        }

        $this->assertNoSensitiveDetailKeys($record->details);

        try {
            $encoded = json_encode($record->details, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new InvalidArgumentException('Audit details must be JSON serializable.', previous: $exception);
        }

        if (strlen($encoded) > self::MAX_DETAILS_BYTES) {
            throw new InvalidArgumentException('Audit details exceeds the allowed size.');
        }
    }

    /**
     * @param  array<array-key, mixed>  $details
     */
    private function assertNoSensitiveDetailKeys(array $details): void
    {
        foreach ($details as $key => $value) {
            if (is_string($key) && $this->isSensitiveDetailKey($key)) {
                throw new InvalidArgumentException('Audit details contains a sensitive key.');
            }

            if (is_array($value)) {
                $this->assertNoSensitiveDetailKeys($value);
            }
        }
    }

    private function isSensitiveDetailKey(string $key): bool
    {
        $normalizedKey = strtolower(str_replace('-', '_', $key));

        foreach (self::SENSITIVE_DETAIL_KEY_FRAGMENTS as $fragment) {
            if (str_contains($normalizedKey, $fragment)) {
                return true;
            }
        }

        return false;
    }
}
