<?php

namespace App\CoreProvisioning;

use Illuminate\Support\Facades\Log;

final class LegacyProvisioningAuditLogger
{
    /**
     * @param array<string, mixed> $context
     */
    public function info(string $event, array $context = []): void
    {
        Log::info('core_provisioning.'.$event, $this->safeContext($context));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function warning(string $event, array $context = []): void
    {
        Log::warning('core_provisioning.'.$event, $this->safeContext($context));
    }

    /**
     * @param array<string, mixed> $context
     * @return array<string, mixed>
     */
    private function safeContext(array $context): array
    {
        return array_filter([
            'correlation_id' => $context['correlation_id'] ?? null,
            'result' => $context['result'] ?? null,
            'reason' => $context['reason'] ?? null,
            'resource_type' => $context['resource_type'] ?? null,
            'client_identifier' => $context['client_identifier'] ?? null,
            'core_issuer' => $context['core_issuer'] ?? null,
            'core_organization_id' => $context['core_organization_id'] ?? null,
            'core_subject' => $context['core_subject'] ?? null,
            'application_context' => $context['application_context'] ?? null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');
    }
}
