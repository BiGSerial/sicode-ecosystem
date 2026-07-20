<?php

namespace App\Support;

final class UnitCapabilities
{
    /** @var array<string, true> */
    private array $enabled;

    /**
     * @param array<int, string> $capabilities
     */
    public function __construct(array $capabilities)
    {
        $this->enabled = [];

        foreach ($capabilities as $capability) {
            if (! UnitCapability::tryFrom($capability) instanceof UnitCapability) {
                throw new UnsupportedUnitCapability("Unknown SICODE unit capability [{$capability}].");
            }

            $this->enabled[$capability] = true;
        }
    }

    public function supports(UnitCapability $capability): bool
    {
        return isset($this->enabled[$capability->value]);
    }

    public function require(UnitCapability $capability): void
    {
        if (! $this->supports($capability)) {
            throw new UnsupportedUnitCapability("SICODE unit capability [{$capability->value}] is not enabled.");
        }
    }
}
