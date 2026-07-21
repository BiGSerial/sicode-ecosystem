<?php

declare(strict_types=1);

namespace App\LegacyProvisioning;

use App\Models\LegacyProvisioningOperation;
use App\Models\Organization;
use App\Models\OrganizationStatus;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class ProvisionOrganizationToLegacySp
{
    public function __construct(
        private readonly LegacySpProvisioningClient $client,
        private readonly LegacyProvisioningIdempotencyKeys $idempotencyKeys,
        private readonly LegacyProvisioningOperationRecorder $operationRecorder,
        private readonly LegacyProvisioningAudit $audit,
    ) {}

    public function __invoke(Organization $organization): LegacyProvisioningActionResult
    {
        $configuration = LegacyProvisioningConfiguration::sp();
        $configuration->assertUsable();

        $idempotencyKey = $this->idempotencyKeys->organization($organization);
        $now = CarbonImmutable::now();
        $correlationId = (string) Str::uuid();
        $operation = $this->operationRecorder->requested('organization', (string) $organization->getKey(), null, $idempotencyKey, $now);
        $this->audit->requested('organization', (string) $organization->getKey(), null, $correlationId, $now);

        if ($organization->status !== OrganizationStatus::Active->value) {
            $result = new LegacyProvisioningActionResult(
                entityType: 'organization',
                entityId: (string) $organization->getKey(),
                organizationId: null,
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: 0,
                errorCategory: LegacyProvisioningErrorCategory::LocalValidationFailed,
            );

            $this->finish($operation, $result, $correlationId);

            return $result;
        }

        try {
            $httpResult = $this->client->provisionOrganization(new OrganizationProvisioningRequest(
                coreOrganizationId: (string) $organization->getKey(),
                name: (string) $organization->name,
                status: 'active',
                idempotencyKey: $idempotencyKey,
                issuer: $configuration->issuer,
                contractVersion: $configuration->contractVersion,
            ));
        } catch (InvalidArgumentException) {
            $httpResult = new LegacyProvisioningHttpResult(
                outcome: LegacyProvisioningOutcome::Rejected,
                attempts: 1,
                errorCategory: LegacyProvisioningErrorCategory::InvalidResponse,
            );
        }

        $result = new LegacyProvisioningActionResult(
            entityType: 'organization',
            entityId: (string) $organization->getKey(),
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
