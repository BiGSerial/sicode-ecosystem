<?php

declare(strict_types=1);

namespace App\ApplicationLaunch;

final readonly class ApplicationLaunchRedirect
{
    public function __construct(
        public string $launchId,
        public string $callbackUrl,
        public string $code,
        public string $state,
    ) {}

    public function redirectUrl(): string
    {
        $separator = str_contains($this->callbackUrl, '?') ? '&' : '?';

        return $this->callbackUrl.$separator.http_build_query([
            'code' => $this->code,
            'state' => $this->state,
        ], '', '&', PHP_QUERY_RFC3986);
    }
}
