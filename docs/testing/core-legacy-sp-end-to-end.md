# CORE -> Legacy SP End-to-End

Data: 2026-07-21

## Objetivo

Validar, de forma explicita e fora das suites rapidas, o ciclo real:

```text
CORE fixtures
-> provisioning HTTP real para Legacy SP
-> links locais Legacy
-> Application Launch CORE
-> callback Legacy SP
-> exchange backend-to-backend
-> sessao Laravel 10
-> CurrentCompanyContext
-> rota protegida auth + current.company
-> limpeza seletiva
```

O fluxo principal nao usa `Http::fake`.

## Topologia

O alvo `make legacy-sp-e2e` usa os containers existentes do Compose e inicia servidores efemeros internos:

- CORE: `php artisan serve` em porta interna aleatoria `18xxx`;
- Legacy SP: `php artisan serve` em porta interna aleatoria `19xxx`;
- PostgreSQL CORE: banco `sicode_core`;
- MariaDB Legacy: banco `sicode_legacy`;
- comunicacao HTTP interna por host Docker `sicode-core` e `sicode-legacy`.

O runtime Legacy efemero e iniciado como:

```text
APP_ENV=testing
SICODE_UNIT=sp
SICODE_IDENTITY_MODE=provisioning
CORE_LAUNCH_CONTEXT=SP
SESSION_DRIVER=file
```

`SESSION_DRIVER=file` e necessario porque o `.env.testing` do Legacy usa `array`, que nao preserva sessao entre callback e rota protegida em HTTP real.

## Guards

O harness falha antes de escrever quando faltam:

- `APP_ENV=testing`;
- `SICODE_E2E_ALLOWED=true`;
- `LEGACY_TEST_DATABASE_ALLOWED=true`;
- banco CORE `sicode_core` em host permitido;
- banco Legacy `sicode_legacy` em host permitido;
- Legacy `SICODE_UNIT=sp`;
- Legacy `SICODE_IDENTITY_MODE=provisioning`;
- contexto CORE/Legacy `SP`;
- provisioning CORE habilitado para contexto `sp`;
- host Legacy allowlisted (`sicode-legacy`, `127.0.0.1`, `localhost`).

Secrets de teste sao injetados por ambiente pelo script e nao sao impressos.

## Comandos

Executar o ciclo completo:

```bash
make legacy-sp-e2e
```

Limpar manualmente uma execucao conhecida:

```bash
SICODE_E2E_RUN_ID=20260721141714-11534 make legacy-sp-e2e-clean
```

Verificar limpeza Legacy:

```bash
SICODE_E2E_RUN_ID=20260721141714-11534 make legacy-sp-e2e-verify
```

## Evidencias Capturadas

O alvo imprime JSON com:

- `run_id`;
- contagens CORE antes e depois do fluxo;
- IDs CORE de usuario, organizacao, app, contexto, client e launch;
- resultado do provisioning de organizacao e usuario;
- retry idempotente (`already_provisioned`);
- falha parcial controlada por usuario sem membership;
- payload da rota protegida Legacy com usuario autenticado, empresa local, organizacao CORE, contexto `SP` e origem `core`;
- inspecao Legacy de `companies`, `users`, `core_organization_links` e `core_identity_links`;
- contagens Legacy apos cleanup.

`core_audit_events` e append-only e nao e limpo. A diferenca de auditoria CORE apos o E2E e esperada.

## Fixtures

Fixtures por execucao usam prefixo:

```text
TEST_E2E_SP_{run_id}
```

O CORE cria:

- usuario ativo;
- organizacao ativa;
- membership ativa;
- contrato ativo;
- grant institucional para `sicode-legacy/sp`;
- access individual para `sicode-legacy/sp`.

O catalogo `Application`, `ApplicationContext` e `ApplicationClient` usa registros estaveis:

- application: `sicode-legacy`;
- context: `sp`;
- client: `sicode-legacy-sp-e2e`.

Esses registros sao configuracao de teste reutilizavel. As fixtures transacionais por `run_id` sao removidas.

## Limpeza

Legacy limpa por identificadores exatos derivados do prefixo:

```text
core_identity_links
core_organization_links
sessions, quando existir tabela
users
companies
```

CORE limpa:

```text
application_launches
legacy_provisioning_operations
application_accesses
contract_application_grants
contracts
organization_memberships
users
organizations
```

Nao usa `truncate`, `migrate:fresh`, `db:wipe`, `RefreshDatabase` ou delecao global.

## Launch Local HTTP

O CORE persiste `callback_url` HTTPS, conforme constraint e contrato do Launch:

```text
https://sicode-legacy:{porta}/core/launch/callback
```

Como o servidor efemero local nao oferece TLS, o harness troca apenas o esquema para `http` ao simular o navegador dentro da rede Docker. Host, porta, path, code e state permanecem os mesmos. Em CI ou ambiente com TLS interno, essa adaptacao pode ser removida.

## Timeout Ambiguo

O alvo atual valida retry idempotente real e falha parcial real. Timeout ambiguo nao e automatizado porque exige atrasar a resposta apos commit remoto sem bloquear o worker de teste. Procedimento manual recomendado:

1. iniciar o Legacy SP efemero;
2. inserir atraso controlado apos commit do endpoint Legacy em ambiente descartavel;
3. reduzir `LEGACY_SP_PROVISIONING_TIMEOUT_SECONDS`;
4. executar o provisioning CORE;
5. repetir a mesma operacao e confirmar `already_provisioned`;
6. confirmar ausencia de duplicidade em links Legacy.

## CI

Nao incluir no pipeline rapido. O desenho recomendado e workflow separado:

```text
quality rapida -> unit/feature
integration e2e -> containers CORE + Legacy SP + bancos efemeros
```

O workflow deve injetar secrets por mecanismo seguro da plataforma, usar bancos efemeros, executar somente SP para este E2E e manter a matriz ES/SP nos testes normais.

## Troubleshooting

- `Legacy SP provisioning failed before launch`: verificar `CORE_PROVISIONING_CLIENT_SECRETS`, `SICODE_UNIT=sp`, `SICODE_IDENTITY_MODE=provisioning` e logs Legacy.
- `callback did not redirect to /home`: verificar exchange CORE, contexto `SP`, links locais e issuer.
- `protected current-company route status=401`: sessao nao persistiu; confirmar `SESSION_DRIVER=file` no servidor Legacy efemero.
- `GET_LOCK identifier too long`: o Legacy deve usar chave de lock derivada por hash curto.
