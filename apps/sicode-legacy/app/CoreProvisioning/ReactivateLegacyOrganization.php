<?php

namespace App\CoreProvisioning;

use App\Models\CoreOrganizationLink;
use Illuminate\Support\Facades\DB;

final class ReactivateLegacyOrganization
{
    public function __construct(
        private readonly EnsureProvisioningRuntime $runtime,
        private readonly ProvisioningLock $lock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(string $coreOrganizationId, array $payload, string $clientIdentifier, string $applicationContext): ProvisioningOutcome
    {
        $this->runtime->assertEnabled();

        $coreIssuer = (string) ($payload['core_issuer'] ?? 'sicode-core');
        $idempotencyKey = (string) ($payload['idempotency_key'] ?? 'idem-reactivate-org');

        $lockKey = implode(':', [
            'core-provisioning-org-reactivate',
            strtolower($applicationContext),
            $clientIdentifier,
            $coreOrganizationId,
            $idempotencyKey,
        ]);

        return $this->lock->withLock($lockKey, function () use ($coreIssuer, $coreOrganizationId, $applicationContext): ProvisioningOutcome {
            return DB::transaction(function () use ($coreIssuer, $coreOrganizationId, $applicationContext): ProvisioningOutcome {
                $link = CoreOrganizationLink::query()
                    ->where('core_issuer', $coreIssuer)
                    ->where('core_organization_id', $coreOrganizationId)
                    ->where('application_context', $applicationContext)
                    ->lockForUpdate()
                    ->first();

                if (! $link instanceof CoreOrganizationLink) {
                    throw new ProvisioningRejected('ORGANIZATION_LINK_REQUIRED');
                }

                if ($link->status === CoreOrganizationLink::STATUS_ACTIVE) {
                    return new ProvisioningOutcome(ProvisioningOutcome::RESULT_ALREADY_ACTIVE, 'organization', [
                        'core_organization_id' => $coreOrganizationId,
                        'company_id' => $link->company_id,
                    ]);
                }

                $link->forceFill(['status' => CoreOrganizationLink::STATUS_ACTIVE])->save();

                return new ProvisioningOutcome(ProvisioningOutcome::RESULT_REACTIVATED, 'organization', [
                    'core_organization_id' => $coreOrganizationId,
                    'company_id' => $link->company_id,
                ]);
            });
        });
    }
}
