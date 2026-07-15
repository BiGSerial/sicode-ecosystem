<?php

declare(strict_types=1);

namespace App\Organizations;

final class OrganizationDocumentNormalizer
{
    /**
     * @return array{type: string|null, value: string|null}
     */
    public function normalize(?string $type, ?string $value): array
    {
        $normalizedType = $this->blankToNull($type);
        $normalizedValue = $this->blankToNull($value);

        if ($normalizedType !== null) {
            $normalizedType = mb_strtolower($normalizedType);
        }

        if ($normalizedValue !== null) {
            $strippedValue = preg_replace('/[^[:alnum:]]/u', '', $normalizedValue);
            $normalizedValue = $this->blankToNull($strippedValue === null ? null : mb_strtoupper($strippedValue));
        }

        return [
            'type' => $normalizedType,
            'value' => $normalizedValue,
        ];
    }

    private function blankToNull(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        return $trimmed === '' ? null : $trimmed;
    }
}
