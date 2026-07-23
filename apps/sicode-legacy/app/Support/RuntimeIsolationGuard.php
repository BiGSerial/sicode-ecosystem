<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Recusa o boot da aplicacao quando o runtime da unidade (ES ou SP) nao
 * bate com o fingerprint esperado: banco, prefixo Redis, cookie de sessao,
 * prefixo de storage, contexto CORE e modo de identidade.
 *
 * Nao deve nunca incluir senha, secret ou credencial na mensagem de erro.
 *
 * Ver docs/standards/redis-isolation.md.
 */
final class RuntimeIsolationGuard
{
    public function __construct(private readonly CurrentUnit $currentUnit)
    {
    }

    public function assert(): void
    {
        if (! (bool) Config::get('sicode.isolation.enabled', true)) {
            return;
        }

        $unit = $this->currentUnit->value()->value;

        $this->assertDatabase($unit);
        $this->assertNotSnapshotDatabaseWithProvisioning($unit);
        $this->assertRedisPrefix($unit);
        $this->assertSessionCookie($unit);
        $this->assertStoragePrefix($unit);
        $this->assertCoreContext($unit);
        $this->assertProvisioningAllowed($unit);
    }

    private function assertDatabase(string $unit): void
    {
        $expected = trim((string) Config::get('sicode.isolation.expected_database', ''));

        if ($expected === '') {
            return;
        }

        $connection = (string) Config::get('database.default');
        $actual = trim((string) Config::get("database.connections.{$connection}.database"));

        if ($actual !== $expected) {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' esperava o banco configurado em SICODE_EXPECTED_DATABASE e recebeu um banco divergente."
            );
        }
    }

    private function assertNotSnapshotDatabaseWithProvisioning(string $unit): void
    {
        $identityMode = strtolower((string) Config::get('sicode.identity_mode'));

        if ($identityMode !== 'provisioning') {
            return;
        }

        $connection = (string) Config::get('database.default');
        $actual = trim((string) Config::get("database.connections.{$connection}.database"));

        $snapshotDatabase = trim((string) Config::get('sicode.isolation.snapshot_database', 'sicode_legacy'));

        if ($actual === $snapshotDatabase) {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' com SICODE_IDENTITY_MODE=provisioning nao pode apontar para o banco snapshot ('{$snapshotDatabase}'). Use o banco SP Clean."
            );
        }
    }

    private function assertRedisPrefix(string $unit): void
    {
        $expected = $this->renderPattern('sicode.isolation.redis_prefix_pattern', $unit);
        $actual = (string) Config::get('database.redis.cache.options.prefix');

        if ($expected === '' || $actual === '') {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' nao possui REDIS_PREFIX configurado."
            );
        }

        if ($actual !== $expected.'cache:') {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' possui REDIS_PREFIX que nao corresponde ao padrao esperado para esta unidade."
            );
        }
    }

    private function assertSessionCookie(string $unit): void
    {
        $expected = $this->renderPattern('sicode.isolation.session_cookie_pattern', $unit);
        $actual = (string) Config::get('session.cookie');

        if ($expected === '' || $actual === '' || $actual !== $expected) {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' possui SESSION_COOKIE que nao corresponde ao padrao esperado para esta unidade."
            );
        }
    }

    private function assertStoragePrefix(string $unit): void
    {
        $expected = $this->renderPattern('sicode.isolation.storage_prefix_pattern', $unit);
        $actual = trim((string) Config::get('sicode.storage.prefix'));

        if ($expected === '' || $actual === '' || $actual !== $expected) {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' possui SICODE_STORAGE_PREFIX que nao corresponde ao padrao esperado para esta unidade."
            );
        }
    }

    private function assertCoreContext(string $unit): void
    {
        $unitContext = strtoupper((string) Config::get("sicode.units.{$unit}.core_context"));
        $expectedContext = strtoupper((string) Config::get('sicode.core.expected_context'));

        if ($unitContext === '' || $expectedContext === '' || $unitContext !== $expectedContext) {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' possui CORE_LAUNCH_CONTEXT que nao corresponde ao contexto CORE esperado para esta unidade."
            );
        }
    }

    private function assertProvisioningAllowed(string $unit): void
    {
        $identityMode = strtolower((string) Config::get('sicode.identity_mode'));
        $provisioningAllowed = (bool) Config::get("sicode.units.{$unit}.provisioning_allowed", false);

        if ($identityMode === 'provisioning' && ! $provisioningAllowed) {
            throw new RuntimeIsolationViolation(
                "Runtime isolation guard: unidade '{$unit}' nao permite SICODE_IDENTITY_MODE=provisioning."
            );
        }
    }

    private function renderPattern(string $configKey, string $unit): string
    {
        $pattern = (string) Config::get($configKey, '');

        if ($pattern === '') {
            return '';
        }

        return str_replace('{unit}', $unit, $pattern);
    }
}
