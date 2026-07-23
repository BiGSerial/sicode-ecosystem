<?php

namespace App\Support;

use Illuminate\Support\Facades\Config;

/**
 * Recusa o boot do CORE quando o runtime nao bate com o fingerprint
 * esperado: prefixo Redis, database Redis por conexao, cookie de sessao,
 * issuer, URL da aplicacao e conexoes de cache/sessao/fila.
 *
 * Nao deve nunca incluir senha, secret ou credencial na mensagem de erro.
 *
 * Ver docs/standards/redis-isolation.md.
 */
final class CoreRuntimeIsolationGuard
{
    public function assert(): void
    {
        if (! (bool) Config::get('runtime_isolation.enabled', false)) {
            return;
        }

        $this->assertRedisConnectionFingerprints();
        $this->assertNoLegacyRedisPrefix();
        $this->assertSessionCookie();
        $this->assertAppEnv();
        $this->assertIssuer();
        $this->assertApplicationUrl();
        $this->assertCacheSessionQueueConnections();
    }

    private function assertRedisConnectionFingerprints(): void
    {
        $redisPrefix = trim((string) Config::get('runtime_isolation.redis_prefix', ''));

        if ($redisPrefix === '') {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: CORE nao possui SICODE_CORE_EXPECTED_REDIS_PREFIX configurado.'
            );
        }

        $connections = (array) Config::get('runtime_isolation.connections', []);

        foreach ($connections as $connection => $expected) {
            $expectedDatabase = (int) $expected['database'];
            $expectedPrefix = $redisPrefix.$expected['purpose'].':';

            $actualDatabase = (int) Config::get("database.redis.{$connection}.database");
            $actualPrefix = (string) Config::get("database.redis.{$connection}.options.prefix");

            if ($actualDatabase !== $expectedDatabase) {
                throw new CoreRuntimeIsolationViolation(
                    "Runtime isolation guard: conexao Redis '{$connection}' esta com database divergente do fingerprint esperado para o CORE."
                );
            }

            if ($actualPrefix !== $expectedPrefix) {
                throw new CoreRuntimeIsolationViolation(
                    "Runtime isolation guard: conexao Redis '{$connection}' esta com prefixo divergente do fingerprint esperado para o CORE."
                );
            }
        }
    }

    private function assertNoLegacyRedisPrefix(): void
    {
        $forbidden = (string) Config::get('runtime_isolation.forbidden_redis_prefix', 'sicode:legacy:');
        $connections = array_keys((array) Config::get('runtime_isolation.connections', []));

        foreach ($connections as $connection) {
            $actualPrefix = (string) Config::get("database.redis.{$connection}.options.prefix");

            if ($actualPrefix !== '' && str_starts_with($actualPrefix, $forbidden)) {
                throw new CoreRuntimeIsolationViolation(
                    "Runtime isolation guard: conexao Redis '{$connection}' esta usando um prefixo do namespace Legacy."
                );
            }
        }
    }

    private function assertSessionCookie(): void
    {
        $expected = trim((string) Config::get('runtime_isolation.session_cookie', ''));
        $actual = (string) Config::get('session.cookie', '');
        $forbiddenPattern = (string) Config::get('runtime_isolation.forbidden_session_cookie_pattern', '');

        if ($expected === '' || $actual === '' || $actual !== $expected) {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: SESSION_COOKIE do CORE nao corresponde ao padrao esperado.'
            );
        }

        if ($forbiddenPattern !== '' && preg_match($forbiddenPattern, $actual) === 1) {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: SESSION_COOKIE do CORE corresponde a um padrao de cookie reservado ao Legacy.'
            );
        }
    }

    private function assertAppEnv(): void
    {
        $expected = trim((string) Config::get('runtime_isolation.app_env', ''));
        $actual = (string) Config::get('app.env', '');

        if ($actual === '') {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: APP_ENV do CORE nao esta configurado.'
            );
        }

        if ($expected !== '' && $actual !== $expected) {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: APP_ENV do CORE nao corresponde ao valor esperado.'
            );
        }
    }

    private function assertIssuer(): void
    {
        $expected = trim((string) Config::get('runtime_isolation.issuer', ''));
        $actual = (string) Config::get('core_launch.issuer', '');

        if ($expected === '' || $actual === '' || $actual !== $expected) {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: CORE_LAUNCH_ISSUER nao corresponde ao valor esperado.'
            );
        }
    }

    private function assertApplicationUrl(): void
    {
        $expected = trim((string) Config::get('runtime_isolation.app_url', ''));
        $actual = (string) Config::get('app.url', '');

        if ($actual === '') {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: APP_URL do CORE nao esta configurado.'
            );
        }

        if ($expected !== '' && $actual !== $expected) {
            throw new CoreRuntimeIsolationViolation(
                'Runtime isolation guard: APP_URL do CORE nao corresponde ao valor esperado.'
            );
        }
    }

    private function assertCacheSessionQueueConnections(): void
    {
        $checks = [
            'cache.default' => 'redis',
            'cache.stores.redis.lock_connection' => 'default',
            'session.driver' => 'redis',
            'session.connection' => 'redis_session',
            'queue.default' => 'redis',
            'queue.connections.redis.connection' => 'queue',
        ];

        foreach ($checks as $key => $expected) {
            $actual = (string) Config::get($key);

            if ($actual !== $expected) {
                throw new CoreRuntimeIsolationViolation(
                    "Runtime isolation guard: configuracao '{$key}' do CORE nao corresponde ao valor esperado."
                );
            }
        }
    }
}
