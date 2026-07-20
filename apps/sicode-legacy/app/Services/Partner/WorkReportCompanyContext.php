<?php

namespace App\Services\Partner;

use App\CoreIntegration\CurrentCompanyContext;
use App\CoreIntegration\LegacyCompanyAccessResolver;
use App\Models\Company;
use App\Models\User;
use App\Models\WorkReport;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class WorkReportCompanyContext
{
    public function __construct(
        private readonly CurrentCompanyContext $currentCompanyContext,
        private readonly LegacyCompanyAccessResolver $companyAccessResolver,
    ) {
    }

    public function companyIdForSubmission(?string $browserCompanyId = null, bool $canSelectCompany = false): ?string
    {
        $user = Auth::user();

        $candidateCompanyId = $canSelectCompany
            ? $this->normalizeCompanyId($browserCompanyId)
            : $this->contractCompanyId($user);

        if (! $canSelectCompany && $candidateCompanyId === null) {
            throw new AuthorizationException('Work report requires a contract company.');
        }

        if (! $this->currentCompanyContext->isEstablished()) {
            return $candidateCompanyId;
        }

        $companyId = (string) $this->currentCompanyContext->companyId();

        if ($candidateCompanyId !== null && $candidateCompanyId !== $companyId) {
            throw new AuthorizationException('Current company context does not allow this work report company.');
        }

        if (! $user instanceof User || ! $this->companyAccessResolver->canOperateForCompany($user, $companyId)) {
            throw new AuthorizationException('Current user cannot operate for the current company.');
        }

        return $companyId;
    }

    public function assertCanUse(WorkReport $workReport): void
    {
        if (! $this->currentCompanyContext->isEstablished()) {
            return;
        }

        $companyId = (string) $this->currentCompanyContext->companyId();

        if ($workReport->company_id !== $companyId) {
            throw new AuthorizationException('Work report belongs to another company.');
        }

        $user = Auth::user();
        if (! $user instanceof User || ! $this->companyAccessResolver->canOperateForCompany($user, $companyId)) {
            throw new AuthorizationException('Current user cannot operate for this work report company.');
        }
    }

    public function applyToQuery(Builder $query, string $column = 'company_id'): Builder
    {
        if ($this->currentCompanyContext->isEstablished()) {
            $query->where($column, $this->currentCompanyIdForOperation());
        }

        return $query;
    }

    public function availableCompaniesQuery(): Builder
    {
        $query = Company::query();

        if ($this->currentCompanyContext->isEstablished()) {
            $query->whereKey($this->currentCompanyIdForOperation());
        }

        return $query;
    }

    private function currentCompanyIdForOperation(): string
    {
        $companyId = (string) $this->currentCompanyContext->companyId();

        $user = Auth::user();
        if (! $user instanceof User || ! $this->companyAccessResolver->canOperateForCompany($user, $companyId)) {
            throw new AuthorizationException('Current user cannot operate for the current company.');
        }

        return $companyId;
    }

    private function contractCompanyId($user): ?string
    {
        if (! $user instanceof User) {
            return null;
        }

        $companyId = $user->Employee?->Contract?->company_id;

        return $this->normalizeCompanyId($companyId);
    }

    private function normalizeCompanyId(?string $companyId): ?string
    {
        if ($companyId === null || $companyId === '') {
            return null;
        }

        return $companyId;
    }
}
