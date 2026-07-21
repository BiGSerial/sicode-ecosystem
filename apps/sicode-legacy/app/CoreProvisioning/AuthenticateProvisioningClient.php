<?php

namespace App\CoreProvisioning;

final class AuthenticateProvisioningClient
{
    public function __invoke(string $clientIdentifier, #[\SensitiveParameter] string $clientSecret): void
    {
        $configuredSecrets = config('core_provisioning.client_secrets', []);

        if (! is_array($configuredSecrets) || ! isset($configuredSecrets[$clientIdentifier]) || ! is_string($configuredSecrets[$clientIdentifier])) {
            throw new ProvisioningAuthenticationFailed('INVALID_CLIENT');
        }

        $configuredSecret = $configuredSecrets[$clientIdentifier];

        if ($configuredSecret === '' || ! hash_equals($configuredSecret, $clientSecret)) {
            throw new ProvisioningAuthenticationFailed('INVALID_CLIENT_SECRET');
        }
    }
}
