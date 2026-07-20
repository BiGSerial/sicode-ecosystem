<?php

namespace App\CoreIntegration;

use App\Support\CurrentUnit;

final class CoreLaunchContextValidator
{
    public function __construct(private readonly CurrentUnit $currentUnit)
    {
    }

    public function assertMatchesConfiguredUnit(CoreLaunchIdentity $identity): void
    {
        $unit = $this->currentUnit->value()->value;
        $unitContext = (string) config("sicode.units.{$unit}.core_context");
        $expectedContext = (string) config('sicode.core.expected_context');
        $clientContext = (string) config('core_integration.context');

        if ($unitContext === '' || $expectedContext === '' || $clientContext === '') {
            throw new CoreLaunchContextMismatch('CORE launch exchange rejected.');
        }

        if ($unitContext !== $expectedContext || $unitContext !== $clientContext || $identity->context !== $unitContext) {
            throw new CoreLaunchContextMismatch('CORE launch exchange rejected.');
        }
    }
}
