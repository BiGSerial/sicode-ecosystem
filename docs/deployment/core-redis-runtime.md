# Runtime Redis do SICODE CORE

Data: 2026-07-23

Status: Implementado. Ver `docs/standards/redis-isolation.md#core` para o
padrão normativo completo e `docs/standards/hub-integrated-application-runtime.md`
para o padrão de runtime HUB do qual este documento é uma instanciação.

## Identidade

| Atributo | Valor |
| --- | --- |
| Prefixo Redis | `sicode:core:global:` |
| Cookie de Sessão | `SESSION_COOKIE=sicode_core_session` |
| `SICODE_CORE_ISOLATION_GUARD_ENABLED` | `true` nos containers reais, `false` em testes por padrão |

## Conexões Redis

| Conexão (`config/database.php`) | DB | Prefixo físico | Finalidade |
| --- | --- | --- | --- |
| `default` | 12 | `sicode:core:global:lock:` | locks (`Cache::lock()`), rate limiting |
| `cache` | 13 | `sicode:core:global:cache:` | `CACHE_STORE=redis` |
| `redis_session` | 14 | `sicode:core:global:session:` | sessão HTTP (`SESSION_DRIVER=redis`) |
| `queue` | 15 | `sicode:core:global:queue:` | filas (`QUEUE_CONNECTION=redis`) |

## Configuração (Laravel 13)

```
CACHE_STORE=redis
SESSION_DRIVER=redis
SESSION_CONNECTION=redis_session
QUEUE_CONNECTION=redis
SESSION_COOKIE=sicode_core_session

REDIS_PREFIX=sicode:core:global:
REDIS_DB=12
REDIS_CACHE_DB=13
REDIS_SESSION_DB=14
REDIS_QUEUE_DB=15
REDIS_QUEUE_CONNECTION=queue
```

`SESSION_CONNECTION=redis_session` aponta diretamente para a conexão Redis
nomeada `redis_session` — não há uma cache store dedicada separada (ao
contrário do Legacy, que usa `SESSION_STORE=redis_session` +
`SESSION_CONNECTION=session`). Ver a nota de variação em
`docs/standards/hub-integrated-application-runtime.md#cache-sessão-filas-locks`.

`config/cache.php`: a store `redis` usa `lock_connection => 'default'`
fixo (sem indireção por env), para que `Cache::lock()` sempre grave sob DB
12 / prefixo `lock:`.

## Guard de boot

`App\Support\CoreRuntimeIsolationGuard`
(`apps/sicode-core/app/Support/CoreRuntimeIsolationGuard.php`), registrado
em `AppServiceProvider::boot()`, configurado por
`apps/sicode-core/config/runtime_isolation.php`. Valida:

- fingerprint exato (database **e** prefixo) de cada uma das 4 conexões;
- ausência de prefixo `sicode:legacy:` em qualquer conexão;
- `SESSION_COOKIE` esperado, e rejeição de cookies do padrão Legacy
  (`sicode_{es|sp|snapshot}_session`);
- `APP_ENV` não vazio (e, se `SICODE_CORE_EXPECTED_APP_ENV` setado, igual);
- `CORE_LAUNCH_ISSUER` esperado;
- `APP_URL` não vazio;
- `cache.default`/`cache.stores.redis.lock_connection`/`session.driver`/
  `session.connection`/`queue.default`/`queue.connections.redis.connection`.

Nenhuma mensagem de erro do guard inclui secret ou credencial — apenas o
nome do parâmetro/conexão que divergiu.

## Comandos Make

```bash
make core-redis-smoke              # inspeciona driver/prefixo/conexões em runtime
make core-runtime-isolation-test   # guard (config-only) + Redis físico
make core-runtime-clear-ephemeral  # SCAN+DEL por DB e prefixo de finalidade (nunca FLUSHALL/FLUSHDB)
```

## Troubleshooting

- **Guard recusa o boot**: rode `make core-redis-smoke` para ver o valor
  atual de cada configuração relevante e compare com a tabela acima.
- **Teste de isolamento pulado ("skipped")**: os testes Redis reais exigem
  `APP_ENV=testing` + `CORE_TEST_REDIS_ALLOWED=true` explicitamente — não
  rodam por padrão em `make core-test`.
- **Suspeita de chave vazando para o Legacy**: `docker compose exec redis
  redis-cli -n {12..15} --scan --pattern 'sicode:core:global:*'` deve ser a
  única coisa visível nesses DBs; qualquer chave `sicode:legacy:*` neles é
  bug.
