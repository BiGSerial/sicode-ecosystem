<?php

namespace App\Services\Ads;

use App\Contracts\AdsSubmissionPolicy;
use App\CoreIntegration\AdsCompanyContext;
use App\Models\Note;
use App\Support\{UnitCapabilities, UnitCapability};
use InvalidArgumentException;

final class EsAdsSubmissionPolicy implements AdsSubmissionPolicy
{
    public function __construct(
        private readonly AdsCompanyContext $adsCompanyContext,
        private readonly UnitCapabilities $unitCapabilities,
    ) {
    }

    public function validateSubmission(Note $note, array $payload): void
    {
        $this->unitCapabilities->require(UnitCapability::ADS_DELIVERY);
        $this->adsCompanyContext->validateNoteAccess($note);

        if ($this->isAdsClosed($note)) {
            throw new InvalidArgumentException('Esta obra já possui ADS entregue e não pode ser reenviada no ES.');
        }

        if (!$note->WorkForm || $note->WorkForm->rejected) {
            throw new InvalidArgumentException('Esta obra não possui Informe de Obra válido para entrega da ADS.');
        }
    }

    public function isAdsClosed(Note $note): bool
    {
        $hasOldAds = $note->relationLoaded('OldAds')
            ? $note->OldAds->isNotEmpty()
            : $note->OldAds()->exists();

        if ($hasOldAds) {
            return true;
        }

        $adsForm = $note->relationLoaded('WorkForm') && $note->WorkForm
            ? $note->WorkForm->Adsform
            : $note->WorkForm()?->first()?->Adsform;

        if (!$adsForm) {
            return false;
        }

        if ($adsForm->tacit && !$adsForm->tacit_delivered_at) {
            return false;
        }

        return $adsForm->files()->exists() || (bool) $adsForm->tacit_delivered_at;
    }
}
