<?php

declare(strict_types=1);

namespace App\Hub;

final readonly class HubApplicationEntry
{
    public function __construct(
        public string $applicationId,
        public string $applicationCode,
        public string $applicationName,
        public ?string $applicationDescription,
        public ?string $contextId,
        public ?string $contextCode,
        public ?string $contextName,
        public ?string $launchUrl,
    ) {}

    public function displayContext(): ?string
    {
        if ($this->contextName === null) {
            return null;
        }

        if ($this->contextCode === null) {
            return $this->contextName;
        }

        return $this->contextName.' · '.$this->contextCode;
    }
}
