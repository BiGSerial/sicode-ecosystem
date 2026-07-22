<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\Models\LegacyProvisioningOperation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class SuspendUserInLegacySp
{
    public function __construct(
        private readonly LegacySpProvisioningClient $client,
        private readonly LegacyProvisioningOperationRecorder $operationRecorder,
        private readonly LegacyProvisioningAudit $audit,
    ) {}

    public function __invoke(User $user): LegacyProvisioningActionResult
    {
        $configuration = LegacyProvisioningConfiguration::sp();
        $configuration->assertUsable();

        $idempotencyKey = 'user:'.$user->getKey().':suspend:sp:v1';
        $now = CarbonImmutable::now();
        $correlationId = (string) Str::uuid();
        $operation = $this->operationRecorder->requested('user', (string) $user->getKey(), null, $idempotencyKey, $now);
        $this->audit->requested('user', (string) $user->getKey(), null, $correlationId, $now);

        try {
            $httpResult = $this->client->suspendUser(
                coreSubject: (string) $user->getKey(),
                idempotencyKey: $idempotencyKey,
            );
        } catch (InvalidArgumentException) {
            $httpResult = new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: 1,
                errorCategory: LegacyProvisioningErrorCategory::InvalidResponse,
            );
        }

        $result = new LegacyProvisioningActionResult(
            entityType: 'user',
            entityId: (string) $user->getKey(),
            organizationId: null,
            outcome: $httpResult->outcome,
            attempts: $httpResult->attempts,
            errorCategory: $httpResult->errorCategory,
            remoteLocalId: $httpResult->remoteLocalId,
        );

        $this->finish($operation, $result, $correlationId);

        return $result;
    }

    private function finish(LegacyProvisioningOperation $operation, LegacyProvisioningActionResult $result, string $correlationId): void
    {
        $now = CarbonImmutable::now();

        $this->operationRecorder->completed($operation, $result->outcome, $result->attempts, $result->errorCategory, $result->remoteLocalId, $now);
        $this->audit->completed($result, $correlationId, $now);
    }
}
