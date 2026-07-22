<?php

namespace App\CoreIntegration;

use App\Models\Company;
use App\Models\CoreOrganizationLink;

final class CoreOrganizationLinkResolver
{
    public function resolve(CoreLaunchIdentity $identity): CoreOrganizationLink
    {
        $links = CoreOrganizationLink::query()
            ->where('core_issuer', $identity->issuer)
            ->where('core_organization_id', $identity->coreOrganizationId)
            ->where(function ($query) use ($identity): void {
                $query->where('application_context', $identity->context)
                    ->orWhere('application_context', strtoupper($identity->context))
                    ->orWhere('application_context', strtolower($identity->context));
            })
            ->where('status', CoreOrganizationLink::STATUS_ACTIVE)
            ->limit(2)
            ->get();

        if ($links->isEmpty()) {
            throw new OrganizationLinkRequired('CORE organization link is required.');
        }

        if ($links->count() > 1) {
            throw new DuplicateCoreLink('Duplicate active CORE organization link.');
        }

        $link = $links->sole();

        if (! $link->company instanceof Company || $link->company->trashed()) {
            throw new OrganizationLinkRequired('CORE organization link is required.');
        }

        return $link;
    }
}
