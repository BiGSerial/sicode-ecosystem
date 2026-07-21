<?php

namespace App\CoreProvisioning;

use App\Models\Company;
use App\Models\CoreOrganizationLink;
use Illuminate\Support\Facades\DB;

final class ProvisionLegacyOrganization
{
    public function __construct(
        private readonly EnsureProvisioningRuntime $runtime,
        private readonly ProvisioningLock $lock,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function __invoke(array $payload, string $clientIdentifier, string $applicationContext): ProvisioningOutcome
    {
        $this->runtime->assertEnabled();

        if (($payload['status'] ?? null) !== 'active') {
            throw new ProvisioningRejected('UNSUPPORTED_ORGANIZATION_STATUS');
        }

        $coreIssuer = (string) $payload['core_issuer'];
        $coreOrganizationId = (string) $payload['core_organization_id'];
        $name = (string) $payload['name'];
        $idempotencyKey = (string) $payload['idempotency_key'];

        $lockKey = implode(':', [
            'core-provisioning-org',
            strtolower($applicationContext),
            $clientIdentifier,
            $coreOrganizationId,
            $idempotencyKey,
        ]);

        return $this->lock->withLock($lockKey, function () use ($coreIssuer, $coreOrganizationId, $applicationContext, $name): ProvisioningOutcome {
            return DB::transaction(function () use ($coreIssuer, $coreOrganizationId, $applicationContext, $name): ProvisioningOutcome {
                $links = CoreOrganizationLink::query()
                    ->where('core_issuer', $coreIssuer)
                    ->where('core_organization_id', $coreOrganizationId)
                    ->where('application_context', $applicationContext)
                    ->limit(2)
                    ->lockForUpdate()
                    ->get();

                if ($links->count() > 1) {
                    throw new ProvisioningConflict('DUPLICATE_ORGANIZATION_LINK');
                }

                $existing = $links->first();

                if ($existing instanceof CoreOrganizationLink) {
                    if ($existing->status !== CoreOrganizationLink::STATUS_ACTIVE) {
                        throw new ProvisioningConflict('ORGANIZATION_LINK_NOT_ACTIVE');
                    }

                    if (! $existing->company instanceof Company || $existing->company->trashed()) {
                        throw new ProvisioningConflict('COMPANY_UNAVAILABLE');
                    }

                    $result = ProvisioningOutcome::RESULT_ALREADY_PROVISIONED;

                    if ($existing->company->name !== $name) {
                        $existing->company->forceFill(['name' => $name])->save();
                        $result = ProvisioningOutcome::RESULT_UPDATED;
                    }

                    return new ProvisioningOutcome($result, 'organization', [
                        'core_organization_id' => $coreOrganizationId,
                        'company_id' => $existing->company_id,
                    ]);
                }

                $hasUnlinkedName = DB::table('companies')
                    ->where('name', $name)
                    ->whereNull('deleted_at')
                    ->whereNotExists(function ($query) use ($applicationContext) {
                        $query->selectRaw('1')
                            ->from('core_organization_links')
                            ->whereColumn('core_organization_links.company_id', 'companies.id')
                            ->where('core_organization_links.application_context', $applicationContext)
                            ->where('core_organization_links.status', CoreOrganizationLink::STATUS_ACTIVE);
                    })
                    ->exists();

                if ($hasUnlinkedName) {
                    throw new ProvisioningConflict('COMPANY_NAME_ALREADY_EXISTS_WITHOUT_LINK');
                }

                $company = Company::create([
                    'name' => $name,
                    'email' => $this->organizationPlaceholderEmail($coreOrganizationId),
                    'telephone' => null,
                ]);

                $link = CoreOrganizationLink::create([
                    'core_issuer' => $coreIssuer,
                    'core_organization_id' => $coreOrganizationId,
                    'application_context' => $applicationContext,
                    'company_id' => $company->id,
                    'status' => CoreOrganizationLink::STATUS_ACTIVE,
                    'linked_at' => now(),
                ]);

                return new ProvisioningOutcome(ProvisioningOutcome::RESULT_CREATED, 'organization', [
                    'core_organization_id' => $coreOrganizationId,
                    'company_id' => $link->company_id,
                ]);
            });
        });
    }

    private function organizationPlaceholderEmail(string $coreOrganizationId): string
    {
        $domain = (string) config('core_provisioning.placeholder_email_domain', 'provisioning.local');
        $compactUuid = str_replace('-', '', strtolower($coreOrganizationId));

        return 'core-org-'.$compactUuid.'@'.$domain;
    }
}
