<?php

declare(strict_types=1);

namespace App\ApplicationLaunch;

use Carbon\CarbonInterface;

final readonly class ApplicationLaunchExchangeResult
{
    public function __construct(
        public string $issuer,
        public string $coreSubject,
        public ?string $coreOrganizationId,
        public string $application,
        public ?string $context,
        public string $launchId,
        public CarbonInterface $issuedAt,
        public CarbonInterface $expiresAt,
        public string $state,
    ) {}

    /**
     * @return array{
     *     iss: string,
     *     core_subject: string,
     *     core_organization_id: string|null,
     *     application: string,
     *     context: string|null,
     *     launch_id: string,
     *     issued_at: string,
     *     expires_at: string,
     *     state: string
     * }
     */
    public function toArray(): array
    {
        return [
            'iss' => $this->issuer,
            'core_subject' => $this->coreSubject,
            'core_organization_id' => $this->coreOrganizationId,
            'application' => $this->application,
            'context' => $this->context,
            'launch_id' => $this->launchId,
            'issued_at' => $this->issuedAt->toJSON(),
            'expires_at' => $this->expiresAt->toJSON(),
            'state' => $this->state,
        ];
    }
}
