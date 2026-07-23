# CI: bootstrap SP Clean (workflow `sp-clean-ci.yml`)

Data: 2026-07-23

Status: Implementado. Primeiro workflow de CI do monorepo
(`.github/workflows/sp-clean-ci.yml`).

## Objetivo

Validar em CI, contra um ambiente descartável, que o runtime Legacy SP
Clean + CORE sobe do zero de forma correta: migrations, provisioning real,
Launch, sessão Legacy, lifecycle e cleanup — sem depender de estado
deixado por execuções anteriores.

## Checkout multi-repositório

CORE e Legacy não vivem mais neste monorepo (`apps/sicode-core` e
`apps/sicode-legacy` foram removidos — ver
`docs/inventory/repository-split-ownership.md`). O workflow faz checkout
lado a lado dos três repositórios no runner:

```text
${{ github.workspace }}/
├── ecosystem
├── sicode-core
└── sicode-legacy
```

- `sicode-core` e `sicode-legacy` são checkouts de
  `BiGSerial/sicode-core`/`BiGSerial/sicode-legacy`, fixados por SHA
  (`env.CORE_REF`/`env.LEGACY_REF` no workflow) — não por `main`.
- Autenticação: secret `SICODE_COMPONENTS_READ_TOKEN` (fine-grained PAT
  somente leitura — `Contents: Read-only`, `Metadata: Read-only`,
  restrito a `sicode-core`/`sicode-legacy`). O `GITHUB_TOKEN` padrão do
  Actions não alcança repositórios irmãos privados, por isso não é usado
  para esses dois checkouts. Evolução recomendada se a automação crescer:
  GitHub App dedicado em vez de PAT pessoal.
- Cadastro do secret (passo manual, não versionado): Settings → Secrets
  and variables → Actions → New repository secret, nome
  `SICODE_COMPONENTS_READ_TOKEN`, valor = o PAT fine-grained. Ou, via
  `gh` CLI (pede o valor de forma interativa, não o recebe como
  argumento):

  ```bash
  gh secret set SICODE_COMPONENTS_READ_TOKEN \
    --repo BiGSerial/sicode-ecosystem
  ```

  Enquanto este secret não existir, o job falha explicitamente no passo
  "Checkout CORE" (`Input required and not supplied: token`) — esse é o
  único bloqueio conhecido para o E2E remoto completo.
- Um passo de validação confere que os HEADs dos checkouts batem com as
  refs esperadas e que `apps/sicode-core`/`apps/sicode-legacy` não
  reapareceram no checkout do Ecosystem, falhando explicitamente caso
  contrário.
- Todos os passos do Ecosystem rodam com `working-directory: ecosystem`;
  o Compose resolve `../sicode-core`/`../sicode-legacy` para os checkouts
  irmãos automaticamente (mesmos defaults usados localmente).

### Histórico de refs

`CORE_REF`/`LEGACY_REF` são fixados por SHA completo (40 caracteres), nunca
por `main` — cada atualização é uma decisão explícita de "este é o commit
validado", não um acompanhamento automático da branch. Isso evita que uma
mudança não revisada em `sicode-core`/`sicode-legacy` altere o
comportamento do E2E do Ecosystem sem uma atualização correspondente aqui.

| Data | CORE_REF | LEGACY_REF | Motivo |
| --- | --- | --- | --- |
| 2026-07-23 | `0f3c9fae8da58aee1062b36c0db8b7cbfce50c8f` | `0692af424fd0c540747eaf948c4e0834fadb8f19` | HEADs confirmados no momento da separação dos monorepos (primeiro teste remoto planejado). |
| 2026-07-23 | `5acde66bbcfdbd99142e1b40b5bc717207c8162f` | `c739506bb9760a8c0e455d698379edfe34b7406a` | HEADs após os fixes reais de `quality.yml` em CORE (env Redis do teste de isolamento) e Legacy (baseline PHPStan + env Redis do teste de isolamento) — ambos com `quality.yml` verde nesses SHAs. |

**Rerodar o workflow** após atualizar as refs (push já dispara
automaticamente; para forçar sem novo commit):

```bash
gh workflow run sp-clean-ci.yml --repo BiGSerial/sicode-ecosystem --ref main
```

**Condição para substituir SHA por tag `v0.1.0+`**: somente depois que os
três pipelines (`quality.yml` do CORE, `quality.yml` do Legacy, e este
`sp-clean-ci.yml` rodando o E2E completo) passarem com o secret
`SICODE_COMPONENTS_READ_TOKEN` cadastrado — ver
`docs/architecture/component-version-compatibility.md`. Não criar a tag
antes disso.

## O que o workflow faz

1. build das imagens `sicode-core`/`sicode-legacy` a partir dos checkouts
   irmãos;
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
