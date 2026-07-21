<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\CoreAudit\CoreAuditAction;
use App\CoreAudit\CoreAuditActorType;
use App\CoreAudit\CoreAuditRecord;
use App\CoreAudit\CoreAuditSubjectType;
use App\CoreAudit\RecordCoreAuditEvent;
use Carbon\CarbonInterface;

final class LegacyProvisioningAudit
{
    public function __construct(
        private readonly RecordCoreAuditEvent $recordAuditEvent,
    ) {}

    public function requested(string $entityType, string $subjectId, ?string $organizationId, string $correlationId, CarbonInterface $now): void
    {
        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: $now,
            actorType: CoreAuditActorType::System,
            actorId: null,
            action: CoreAuditAction::LegacyProvisioningRequested,
            subjectType: $this->subjectType($entityType),
            subjectId: $subjectId,
            reason: 'LEGACY_SP_PROVISIONING_REQUESTED',
            correlationId: $correlationId,
            details: $this->details($entityType, $organizationId, null, null),
        ));
    }

    public function completed(LegacyProvisioningActionResult $result, string $correlationId, CarbonInterface $now): void
    {
        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: $now,
            actorType: CoreAuditActorType::System,
            actorId: null,
            action: $result->outcome->auditActionFor($result->entityType),
            subjectType: $this->subjectType($result->entityType),
            subjectId: $result->entityId,
            reason: strtoupper($result->outcome->value),
            correlationId: $correlationId,
            details: $this->details($result->entityType, $result->organizationId, $result->attempts, $result->errorCategory),
        ));
    }

    public function partial(LegacyProvisioningActionResult $organization, LegacyProvisioningActionResult $user, string $correlationId, CarbonInterface $now): void
    {
        ($this->recordAuditEvent)(new CoreAuditRecord(
            occurredAt: $now,
            actorType: CoreAuditActorType::System,
            actorId: null,
            action: CoreAuditAction::LegacyProvisioningPartiallyCompleted,
            subjectType: CoreAuditSubjectType::User,
            subjectId: $user->entityId,
            reason: 'LEGACY_SP_PROVISIONING_PARTIAL',
            correlationId: $correlationId,
            details: [
                'target_application' => 'sicode-legacy',
                'target_context' => 'sp',
                'organization_id' => $organization->entityId,
                'organization_outcome' => $organization->outcome->value,
                'user_outcome' => $user->outcome->value,
                'user_error_category' => $user->errorCategory?->value,
            ],
        ));
    }

    private function subjectType(string $entityType): CoreAuditSubjectType
    {
        return $entityType === 'organization'
            ? CoreAuditSubjectType::Organization
            : CoreAuditSubjectType::User;
    }

    /**
     * @return array<string, mixed>
     */
    private function details(string $entityType, ?string $organizationId, ?int $attempts, ?LegacyProvisioningErrorCategory $errorCategory): array
    {
        return array_filter([
            'target_application' => 'sicode-legacy',
            'target_context' => 'sp',
            'entity_type' => $entityType,
            'organization_id' => $organizationId,
            'attempts' => $attempts,
            'error_category' => $errorCategory?->value,
        ], fn (mixed $value): bool => $value !== null);
    }
}
