<?php

namespace App\Services\Production;

use App\CoreIntegration\CurrentCompanyContext;
use App\CoreIntegration\LegacyCompanyAccessResolver;
use App\Models\Production;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class ProductionCompanyContext
{
    public function __construct(
        private readonly CurrentCompanyContext $currentCompanyContext,
        private readonly LegacyCompanyAccessResolver $companyAccessResolver,
    ) {
    }

    public function effectiveCompanyId(?string $browserCompanyId = null): string
    {
        if (! $this->currentCompanyContext->isEstablished()) {
            return (string) $browserCompanyId;
        }

        $companyId = (string) $this->currentCompanyContext->companyId();

        if ($browserCompanyId !== null && $browserCompanyId !== '' && $browserCompanyId !== $companyId) {
            throw new AuthorizationException('Current company context does not allow this company.');
        }

        $user = Auth::user();
        if (! $user || ! $this->companyAccessResolver->canOperateForCompany($user, $companyId)) {
            throw new AuthorizationException('Current user cannot operate for the current company.');
        }

        return $companyId;
    }

    public function assertCanUse(Production $production): void
    {
        if (! $this->currentCompanyContext->isEstablished()) {
            return;
        }

        $companyId = (string) $this->currentCompanyContext->companyId();

        if ($production->company_id !== $companyId) {
            throw new AuthorizationException('Production belongs to another company.');
        }

        $user = Auth::user();
        if (! $user || ! $this->companyAccessResolver->canOperateForCompany($user, $companyId)) {
            throw new AuthorizationException('Current user cannot operate for this production company.');
        }
    }

    public function applyToQuery(Builder $query): Builder
    {
        if ($this->currentCompanyContext->isEstablished()) {
            $query->where('company_id', $this->effectiveCompanyId());
        }

        return $query;
    }
}
