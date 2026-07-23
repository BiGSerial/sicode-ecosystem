# Padrão: isolamento de Redis por aplicação/contexto

Data: 2026-07-22

Status: Implementado para SICODE Legacy ES/SP e SICODE CORE. Normativo para
novas aplicações integradas ao HUB.

## Motivação

Uma investigação de isolamento entre SICODE Legacy ES e SP encontrou cache e
sessão `file` fisicamente compartilhados entre os dois containers (mesmo
bind mount de `storage/`), com `CACHE_PREFIX` inócuo porque o driver `file`
do Laravel ignora esse valor. Ver
`docs/reports/legacy-sp-unexpected-data-investigation-2026-07-22.md` para o
diagnóstico completo. Este documento define o padrão de correção e o
baseline obrigatório daqui para frente.

## Backend oficial

Redis é o backend oficial para cache, sessão, filas e locks em todas as
aplicações integradas ao HUB. Não usar `file`/`database` para essas
finalidades em runtime de container.

Cliente PHP: **PhpRedis** (extensão nativa `redis`), não Predis, exceto
justificativa técnica documentada em ADR. PhpRedis é mais rápido, não exige
dependência Composer e é o cliente padrão assumido pelo
`Illuminate\Redis\Connectors\PhpRedisConnector`.

`REDIS_CLIENT=phpredis` deve estar setado explicitamente (não confiar apenas
no default do framework).

## Serviço Redis

Compartilhado entre todas as aplicações locais (`compose.yaml`, serviço
`redis`), isolado por prefixo de chave (ver abaixo), não por instância
física — mais simples de operar localmente, e o isolamento por prefixo é o
mecanismo que efetivamente protege contra colisão, então uma instância só
por aplicação não traria isolamento adicional real.

Requisitos do serviço (implementados em `compose.yaml`):

- imagem com versão fixada (`redis:7.4-alpine`, não `redis:7-alpine`
  flutuante);
- healthcheck (`redis-cli ping`);
- rede interna (`ecosystem_default`), sem exigir acesso externo;
- persistência via AOF (`--appendonly yes`) + volume nomeado
  (`sicode-redis-data`), para não perder locks/filas em restart local;
- política de memória documentada e aplicada via `command:`
  (`--maxmemory 256mb --maxmemory-policy allkeys-lru` por padrão, ajustável
  via `REDIS_MAXMEMORY`/`REDIS_MAXMEMORY_POLICY`);
- porta exposta ao host (`6380`) apenas para inspeção local
  (`redis-cli`/`make legacy-redis-inspect`) — aceitável em ambiente local
  dev; em ambientes não locais, não expor publicamente e exigir
  `REDIS_PASSWORD` (`requirepass`) via secret, nunca em arquivo versionado.

## Prefixos por aplicação/contexto

Cada aplicação/contexto usa um prefixo base próprio:

```text
sicode:core:global:
sicode:legacy:es:
sicode:legacy:sp:
```

Novas aplicações seguem o padrão `sicode:{aplicacao}:{contexto}:`, com
`{contexto}` omitido (`global:`) quando a aplicação não tem múltiplos
contextos/unidades.

## Finalidades distinguíveis (não depender só do número do DB)

Cada prefixo base é sufixado por finalidade, e cada finalidade usa uma
conexão Redis lógica própria com seu próprio número de DB — as duas camadas
juntas (prefixo + DB) formam o isolamento, nunca apenas uma delas:

| Finalidade | Sufixo de prefixo | Uso |
| --- | --- | --- |
| lock/default | `lock:` | `Cache::lock()`, scheduler locks, rate limiting |
| cache | `cache:` | `Cache::store('redis')` |
| session | `session:` | sessão HTTP (`SESSION_DRIVER=redis`) |
| queue | `queue:` | filas (`QUEUE_CONNECTION=redis`) |

Exemplo de chave física completa para um lock do Legacy SP:
`sicode:legacy:sp:lock:{nome-do-lock}`.

### Implementação de referência (`apps/sicode-legacy/config/database.php`)

```php
'redis' => [
    'client' => env('REDIS_CLIENT', 'phpredis'),
    'options' => ['cluster' => env('REDIS_CLUSTER', 'redis')],

    'default' => [ /* host/port/database=REDIS_DB */
        'options' => ['prefix' => env('REDIS_PREFIX', '...') . 'lock:'],
    ],
    'cache' => [ /* database=REDIS_CACHE_DB */
        'options' => ['prefix' => env('REDIS_PREFIX', '...') . 'cache:'],
    ],
    'session' => [ /* database=REDIS_SESSION_DB */
        'options' => ['prefix' => env('REDIS_PREFIX', '...') . 'session:'],
    ],
    'queue' => [ /* database=REDIS_QUEUE_DB */
        'options' => ['prefix' => env('REDIS_PREFIX', '...') . 'queue:'],
    ],
],
```

O prefixo é declarado dentro da chave `options` de **cada conexão nomeada**,
não apenas em `redis.options.prefix` (top-level). Isso é uma decisão
deliberada e verificada contra o código-fonte do
`Illuminate\Redis\Connectors\PhpRedisConnector::connect()`: o array de
`options` per-conexão é mesclado por último e vence sobre o `options`
global, então um prefixo por conexão só funciona se declarado assim. Um
prefixo apenas em `redis.options.prefix` se aplicaria identicamente a
*todas* as conexões (cache, sessão, fila, lock), eliminando a distinção por
finalidade.

`config/cache.php` mantém `'prefix' => env('CACHE_PREFIX', '')` (vazio por
padrão) porque o isolamento já acontece na camada de conexão Redis; somar um
`CACHE_PREFIX` não vazio duplicaria o namespace sem ganho.

Sessão usa uma store de cache dedicada (`redis_session`, conectada à conexão
`session`) porque `SessionManager::createRedisDriver()` reaproveita uma
`Cache\Repository` — sem uma store própria, sessão herdaria o mesmo prefixo
de aplicação da store `redis` genérica.

### Números de DB por unidade (defesa em profundidade)

Além do prefixo, cada unidade usa uma faixa de DB Redis distinta, para que
mesmo um bug de prefixo não misture fisicamente os dados nos comandos que
operam por DB inteiro (`SCAN`, `DBSIZE`, um eventual `FLUSHDB` mal feito):

| Unidade | default/lock | cache | session | queue |
| --- | --- | --- | --- | --- |
| Legacy ES | 0 | 1 | 2 | 3 |
| Legacy SP | 4 | 5 | 6 | 7 |
| Legacy SP Schema Archive | 8 | 9 | 10 | 11 |
| CORE (global) | 12 | 13 | 14 | 15 |

Isso **não substitui** o isolamento por prefixo — é complementar. O padrão
explicitamente rejeita depender só do número do DB porque DBs Redis não têm
controle de acesso próprio (qualquer client autenticado no servidor pode dar
`SELECT` em qualquer DB).

## CORE

O CORE é uma aplicação única (sem múltiplas unidades como ES/SP), então usa
`sicode:core:global:` como prefixo base — `global:` no lugar de um contexto,
seguindo a convenção `sicode:{aplicacao}:{contexto}:` descrita acima.

Diferença deliberada de nomenclatura em relação ao Legacy: a conexão Redis
dedicada à sessão se chama `redis_session` (não `session`), e
`SESSION_CONNECTION=redis_session` aponta diretamente para essa conexão —
não há uma cache store `redis_session` separada como no Legacy, porque
`SessionManager::createRedisDriver()` sempre chama
`setConnection(config('session.connection'))` depois de resolver a store,
então o nome da conexão é o que importa fisicamente, não o nome da store.
`config('session.store')` fica no default do framework (`redis`) sem
problema, já que a conexão é sobrescrita explicitamente de qualquer forma.

Implementação: `apps/sicode-core/config/database.php` (bloco `redis`,
conexões `default`/`cache`/`redis_session`/`queue`), `config/cache.php`
(`lock_connection` fixo em `default`), `config/queue.php`
(`REDIS_QUEUE_CONNECTION` padrão `queue`).

Guard equivalente: `App\Support\CoreRuntimeIsolationGuard`, registrado em
`AppServiceProvider::boot()` — ver seção "Guard de boot" abaixo. Testes:
`tests/Unit/CoreRuntimeIsolationGuardTest.php` e
`tests/Feature/CoreRedisRuntimeIsolationTest.php`, rodáveis via
`make core-runtime-isolation-test`.

## Filas e locks

`QUEUE_CONNECTION=redis`, com `config('queue.connections.redis.connection')`
apontando para a conexão nomeada `queue` (não `default`) — outra correção
deliberada em relação ao stub padrão do Laravel, que aponta filas para
`default` e assim colidiria com o namespace de locks.

Locks (`Cache::lock()`, scheduler locks futuros, rate limiting) usam a
conexão `default`/`lock:` via `lock_connection` da store `redis` em
`config/cache.php`.

## Guard de boot

`App\Support\RuntimeIsolationGuard` (`app/Support/RuntimeIsolationGuard.php`,
Legacy) roda no `boot()` de `UnitServiceProvider` e recusa subir a aplicação
se o prefixo Redis, cookie de sessão, prefixo de storage, banco de dados ou
modo de identidade não corresponderem ao padrão esperado da unidade
configurada. O CORE tem um guard equivalente,
`App\Support\CoreRuntimeIsolationGuard`, registrado em
`AppServiceProvider::boot()`: valida o fingerprint exato (DB **e** prefixo)
de cada uma das 4 conexões Redis, cookie de sessão, issuer, APP_ENV, APP_URL
e conexões de cache/sessão/fila — nunca aceita apenas "DB dentro de uma
lista permitida", sempre o par DB+prefixo esperado por conexão. Ver
`docs/standards/hub-integrated-application-runtime.md#guards-de-boot`.

## Testes obrigatórios

Legacy: `tests/Unit/RuntimeIsolationGuardTest.php` (guard, sem I/O) e
`tests/Feature/RedisRuntimeIsolationTest.php` (Redis real, requer
`APP_ENV=testing` + `LEGACY_TEST_REDIS_ALLOWED=true`) provam, contra um
Redis físico, que cache/sessão/fila/lock de uma unidade não são visíveis
pela outra mesmo usando a mesma chave lógica. Rodar via
`make legacy-runtime-isolation-test`.

CORE: `tests/Unit/CoreRuntimeIsolationGuardTest.php` e
`tests/Feature/CoreRedisRuntimeIsolationTest.php` (Redis real, requer
`APP_ENV=testing` + `CORE_TEST_REDIS_ALLOWED=true`) provam o mesmo contra o
namespace `sicode:core:global:`, incluindo sessão HTTP real via
`SessionManager` (não só client Redis cru) e contadores de rate limiting
isolados do Legacy. Rodar via `make core-runtime-isolation-test`.

## Fora de escopo aqui

Redis Cluster, Sentinel, Horizon, workers/scheduler ativos — ver
`docs/standards/hub-integrated-application-runtime.md#fora-de-escopo`.
