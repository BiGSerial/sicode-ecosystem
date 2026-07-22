<?php

namespace App\CoreIntegration;

use App\Models\CoreIdentityLink;
use App\Models\User;

final class CoreIdentityLinkResolver
{
    public function resolve(CoreLaunchIdentity $identity): CoreIdentityLink
    {
        $links = CoreIdentityLink::query()
            ->where('core_issuer', $identity->issuer)
            ->where('core_subject', $identity->coreSubject)
            ->where(function ($query) use ($identity): void {
                $query->where('application_context', $identity->context)
                    ->orWhere('application_context', strtoupper($identity->context))
                    ->orWhere('application_context', strtolower($identity->context));
            })
            ->where('status', CoreIdentityLink::STATUS_ACTIVE)
            ->limit(2)
            ->get();

        if ($links->isEmpty()) {
            throw new IdentityLinkRequired('CORE identity link is required.');
        }

        if ($links->count() > 1) {
            throw new DuplicateCoreLink('Duplicate active CORE identity link.');
        }

        $link = $links->sole();

        if (! $link->user instanceof User || $link->user->trashed()) {
            throw new IdentityLinkRequired('CORE identity link is required.');
        }

        return $link;
    }
}
