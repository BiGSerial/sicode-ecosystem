# CI: bootstrap SP Clean (workflow `sp-clean-ci.yml`)

Data: 2026-07-23

Status: Implementado. Primeiro workflow de CI do monorepo
(`.github/workflows/sp-clean-ci.yml`).

## Objetivo

Validar em CI, contra um ambiente descartável, que o runtime Legacy SP
Clean + CORE sobe do zero de forma correta: migrations, provisioning real,
Launch, sessão Legacy, lifecycle e cleanup — sem depender de estado
deixado por execuções anteriores.

## O que o workflow faz

1. build das imagens `sicode-core`/`sicode-legacy`;
2. `docker compose up -d --wait` de `redis`, `sicode-postgres`,
   `sicode-legacy-sp-mariadb`, `sicode-core`, `sicode-legacy`;
3. confirma que Legacy ES (`sicode-legacy-es`) e o schema archive
   (`sicode-legacy-snapshot`, profile `snapshot`) **não** estão rodando;
4. confirma que o banco `sicode_sp` começa vazio (0 tabelas) antes de
   qualquer migration;
5. `php artisan migrate --force` no CORE;
6. `php artisan migrate --force` no Legacy SP Clean;
7. `scripts/e2e/legacy-sp-lifecycle.sh` (`SICODE_E2E_ALLOWED=true`,
   `LEGACY_TEST_DATABASE_ALLOWED=true`) — cobre provisioning real via
   `core:e2e:legacy-sp-lifecycle`, Launch, sessão Legacy, e finaliza com
   `legacy:e2e:sp-fixtures inspect|cleanup|verify-clean`;
8. slice vertical de ADS (`tests/Feature/AdsDomainUnitRulesTest.php`) contra
   o Legacy SP Clean;
9. suíte completa de testes do CORE, incluindo
   `CoreRuntimeIsolationGuardTest`/`CoreRedisRuntimeIsolationTest`
   (`CORE_TEST_REDIS_ALLOWED=true`);
10. em falha, despeja `docker compose logs --tail=500` de todos os
    serviços para diagnóstico;
11. cleanup sempre (`if: always()`): `docker compose down --remove-orphans`
    (sem `-v` — não apaga volumes nomeados; o runner inteiro é descartado
    ao final do job de qualquer forma).

## O que o workflow NÃO cobre

- **Legacy ES real** (`sicode-legacy-es`, banco `tools_mariadb`/`sicode`) —
  nunca sobe neste workflow. Cobertura de ES fica com `make legacy-es-smoke`
  e `make legacy-test-es`, rodados localmente/separadamente.
- **Legacy SP Schema Archive** (profile `snapshot`) — nunca sobe neste
  workflow; não é fonte de regressão baseada em massa de dados (ver
  `docs/deployment/legacy-sp-schema-archive.md`).
- **Workers/scheduler ativos, Horizon, Single Logout** — fora de escopo do
  runtime CORE/Legacy atual, portanto fora de escopo deste workflow.
- **Deploy real** — este workflow só valida bootstrap local/CI, não faz
  deploy de nenhum ambiente.

## Rodando localmente sem GitHub Actions

```bash
make sp-clean-ci-local
```

Executa o mesmo `scripts/e2e/legacy-sp-lifecycle.sh` usado no passo 7 do
workflow, assumindo que os serviços já estão de pé (`make legacy-runtime-up`
ou `docker compose up -d redis sicode-postgres sicode-legacy-sp-mariadb
sicode-core sicode-legacy`). Não reproduz os passos 3/4/8/9/10 do workflow —
para isso, rode os comandos Make/artisan equivalentes descritos acima
manualmente, ou use `act` (não usado/validado neste projeto).

## Limitações conhecidas

- Não há `act` (ou equivalente) configurado neste projeto para rodar o
  workflow localmente de ponta a ponta — a validação local usada durante o
  desenvolvimento deste workflow foi revisão manual + execução dos mesmos
  comandos via `make`/`docker compose exec` fora do GitHub Actions.
- O passo de migration do Legacy SP Clean não pede confirmação interativa
  (diferente de `make legacy-sp-clean-migrate`) porque CI não tem TTY — isso
  é seguro apenas porque o banco é confirmado vazio no passo anterior.
