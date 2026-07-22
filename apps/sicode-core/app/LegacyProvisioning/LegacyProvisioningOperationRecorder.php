<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\Models\LegacyProvisioningOperation;
use Carbon\CarbonInterface;

final class LegacyProvisioningOperationRecorder
{
    public function __construct(
        private readonly LegacyProvisioningIdempotencyKeys $idempotencyKeys,
    ) {}

    public function requested(
        string $entityType,
        string $entityId,
        ?string $organizationId,
        string $idempotencyKey,
        CarbonInterface $now,
    ): LegacyProvisioningOperation {
        return LegacyProvisioningOperation::updateOrCreate(
            ['idempotency_key_hash' => $this->idempotencyKeys->hash($idempotencyKey)],
            [
                'target_application' => 'sicode-legacy',
                'target_context' => 'sp',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'organization_id' => $organizationId,
                'requested_at' => $now,
                'completed_at' => null,
                'outcome' => null,
                'last_error_category' => null,
                'remote_local_id' => null,
            ],
        );
    }

    public function completed(
        LegacyProvisioningOperation $operation,
        LegacyProvisioningOutcome $outcome,
        int $attempts,
        ?LegacyProvisioningErrorCategory $errorCategory,
        ?string $remoteLocalId,
        CarbonInterface $now,
    ): void {
        $dbOutcome = match ($outcome) {
            LegacyProvisioningOutcome::Suspended, LegacyProvisioningOutcome::Reactivated => 'updated',
            LegacyProvisioningOutcome::AlreadySuspended, LegacyProvisioningOutcome::AlreadyActive => 'already_provisioned',
            default => $outcome->value,
        };

        $operation->forceFill([
            'completed_at' => $now,
            'outcome' => $dbOutcome,
            'attempt_count' => $attempts,
            'last_error_category' => $errorCategory?->value,
            'remote_local_id' => $remoteLocalId,
        ])->save();
    }
}
