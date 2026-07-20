<?php

namespace App\CoreIntegration;

use App\Models\Company;
use App\Models\CoreOrganizationLink;
use Illuminate\Session\Store;

final class CurrentCompanyContext
{
    private const COMPANY_ID = 'core_launch.current_company_id';
    private const ORGANIZATION_LINK_ID = 'core_launch.organization_link_id';
    private const APPLICATION_CONTEXT = 'core_launch.application_context';

    public function __construct(private readonly Store $session)
    {
    }

    public function set(CoreOrganizationLink $organizationLink, string $applicationContext): void
    {
        $this->session->put(self::COMPANY_ID, $organizationLink->company_id);
        $this->session->put(self::ORGANIZATION_LINK_ID, $organizationLink->id);
        $this->session->put(self::APPLICATION_CONTEXT, $applicationContext);
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
        $company = $this->company();

        if (! $company instanceof Company) {
            throw new OrganizationLinkRequired('CORE organization link is required.');
        }

        return $company;
    }

    public function applicationContext(): ?string
    {
        $context = $this->session->get(self::APPLICATION_CONTEXT);

        return is_string($context) ? $context : null;
    }
}
