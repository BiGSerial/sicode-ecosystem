<?php

namespace App\Http\Livewire\Concerns;

use App\CoreIntegration\CurrentCompanyContext;
use App\Models\Company;

trait UsesCurrentCompanyContext
{
    protected function currentCompanyContext(): CurrentCompanyContext
    {
        return app(CurrentCompanyContext::class);
    }

    protected function currentCompany(): Company
    {
        return $this->currentCompanyContext()->requireCompany();
    }
}
