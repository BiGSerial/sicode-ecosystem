<?php

namespace App\CoreIntegration;

use App\Models\{Company, Note};
use App\Support\{CurrentUnit, IdentityMode, SicodeUnit};
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AdsCompanyContext
{
    public function __construct(
        private readonly CurrentCompanyContext $currentCompanyContext,
        private readonly CurrentUnit $currentUnit,
        private readonly IdentityMode $identityMode,
    ) {
    }

    public function currentCompanyId(): string
    {
        if ($this->currentCompanyContext->isEstablished()) {
            $companyId = $this->currentCompanyContext->companyId();

            if (is_string($companyId) && $companyId !== '') {
                $this->assertNotCoreUuid($companyId);

                return $companyId;
            }
        }

        if ($this->currentUnit->value() === SicodeUnit::ES && $this->identityMode->isReconciliation()) {
            $userCompanyId = auth()->user()?->company_id;

            if (is_string($userCompanyId) && $userCompanyId !== '') {
                $this->assertNotCoreUuid($userCompanyId);

                return $userCompanyId;
            }
        }

        throw new OrganizationLinkRequired('Current company context is required for ADS operations.');
    }

    public function validateNoteAccess(?Note $note): void
    {
        if (!$note instanceof Note) {
            throw new InvalidArgumentException('Nota inválida para verificação de acesso ADS.');
        }

        $activeCompanyId = $this->currentCompanyId();
        $noteCompanyId   = $note->WorkForm?->company_id ?? $note->WorkFormAny?->company_id;

        if ($noteCompanyId === null || (string) $noteCompanyId !== $activeCompanyId) {
            throw new OrganizationLinkRequired('A nota informada não pertence à empresa do contexto ativo.');
        }
    }

    public function assertNotCoreUuid(string $companyId): void
    {
        // Must be a valid local company ID and must exist in companies table
        if (Str::isUuid($companyId)) {
            $isCoreUuidOnly = !Company::query()->whereKey($companyId)->exists();

            if ($isCoreUuidOnly) {
                throw new InvalidArgumentException('CORE UUID cannot be used directly as local company_id.');
            }
        }
    }
}
