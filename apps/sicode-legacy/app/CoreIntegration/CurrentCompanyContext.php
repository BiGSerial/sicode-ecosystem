<?php

namespace App\CoreIntegration;

use App\Models\Company;
use App\Models\CoreOrganizationLink;
use App\Models\User;
use Illuminate\Session\Store;

final class CurrentCompanyContext
{
    private const COMPANY_ID = 'core_launch.current_company_id';
    private const ORGANIZATION_LINK_ID = 'core_launch.organization_link_id';
    private const CORE_ORGANIZATION_ID = 'core_launch.core_organization_id';
    private const APPLICATION_CONTEXT = 'core_launch.application_context';
    private const SOURCE = 'core_launch.company_context_source';

    public function __construct(private readonly Store $session)
    {
    }

    public function set(CoreOrganizationLink $organizationLink, string $applicationContext): void
    {
        $this->establishFromCoreLaunch($organizationLink, $applicationContext);
    }

    public function establishFromCoreLaunch(CoreOrganizationLink $organizationLink, string $applicationContext): void
    {
        $this->session->put(self::COMPANY_ID, $organizationLink->company_id);
        $this->session->put(self::ORGANIZATION_LINK_ID, $organizationLink->id);
        $this->session->put(self::CORE_ORGANIZATION_ID, $organizationLink->core_organization_id);
        $this->session->put(self::APPLICATION_CONTEXT, $applicationContext);
        $this->session->put(self::SOURCE, 'core');
    }

    public function establishFromLegacyUser(User $user): void
    {
        if (! is_string($user->company_id) || $user->company_id === '') {
            $this->clear();

            return;
        }

        $this->session->put(self::COMPANY_ID, $user->company_id);
        $this->session->forget(self::ORGANIZATION_LINK_ID);
        $this->session->forget(self::CORE_ORGANIZATION_ID);
        $this->session->put(self::APPLICATION_CONTEXT, (string) config('core_integration.context'));
        $this->session->put(self::SOURCE, 'legacy');
    }

    public function clear(): void
    {
        $this->session->forget([
            self::COMPANY_ID,
            self::ORGANIZATION_LINK_ID,
            self::CORE_ORGANIZATION_ID,
            self::APPLICATION_CONTEXT,
            self::SOURCE,
        ]);
    }

    public function isEstablished(): bool
    {
        return $this->companyId() !== null;
    }

    public function requireEstablished(): void
    {
        if (! $this->isEstablished()) {
            throw new OrganizationLinkRequired('Current company context is required.');
        }
    }

    public function companyId(): ?string
    {
        $companyId = $this->session->get(self::COMPANY_ID);

        return is_string($companyId) ? $companyId : null;
    }

    public function company(): ?Company
    {
        $companyId = $this->companyId();

        if ($companyId === null) {
            return null;
        }

        return Company::query()->whereKey($companyId)->first();
    }

    public function requireCompany(): Company
    {
        $this->requireEstablished();

        $company = $this->company();

        if (! $company instanceof Company) {
            throw new OrganizationLinkRequired('Current company context is required.');
        }

        return $company;
    }

    public function coreOrganizationId(): ?string
    {
        $coreOrganizationId = $this->session->get(self::CORE_ORGANIZATION_ID);

        return is_string($coreOrganizationId) ? $coreOrganizationId : null;
    }

    public function applicationContext(): ?string
    {
        $context = $this->session->get(self::APPLICATION_CONTEXT);

        return is_string($context) ? $context : null;
    }

    public function source(): ?string
    {
        $source = $this->session->get(self::SOURCE);

        return is_string($source) ? $source : null;
    }

    public function ensureCompanyId(string $companyId): void
    {
        if ($this->isEstablished() && $this->companyId() !== $companyId) {
            throw new OrganizationLinkRequired('Current company context does not allow this company.');
        }
    }
}
