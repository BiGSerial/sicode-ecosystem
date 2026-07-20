<?php

namespace App\CoreIntegration;

use Illuminate\Support\Facades\Auth;

final class ConsumeCoreLaunch
{
    public function __construct(
        private readonly CoreLaunchExchangeClient $exchangeClient,
        private readonly CoreIdentityLinkResolver $identityLinkResolver,
        private readonly CoreOrganizationLinkResolver $organizationLinkResolver,
        private readonly CurrentCompanyContext $currentCompanyContext,
    ) {
    }

    public function __invoke(string $code, string $state): void
    {
        $identity = $this->exchangeClient->exchange($code, $state);
        $identityLink = $this->identityLinkResolver->resolve($identity);
        $organizationLink = $this->organizationLinkResolver->resolve($identity);

        if (
            $identityLink->user->company_id !== null
            && $identityLink->user->company_id !== $organizationLink->company_id
        ) {
            throw new CompanyDivergenceRejected('CORE launch company diverges from the user primary company.');
        }

        Auth::guard('web')->login($identityLink->user);
        request()->session()->regenerate();
        request()->session()->put('core_launch.auth_source', 'core');
        request()->session()->put('core_launch.core_subject', $identity->coreSubject);
        request()->session()->put('core_launch.identity_link_id', $identityLink->id);
        request()->session()->put('core_launch.launch_id', $identity->launchId);

        $this->currentCompanyContext->establishFromCoreLaunch($organizationLink, $identity->context);

        $identityLink->forceFill(['last_used_at' => now()])->save();
        $organizationLink->forceFill(['last_used_at' => now()])->save();
    }
}
