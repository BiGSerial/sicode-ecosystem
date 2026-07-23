# Inventário de ownership — separação CORE / Legacy / Ecosystem

Data: 2026-07-23

Status: normativo para a extração de `sicode-core` e `sicode-legacy` em
repositórios locais independentes (ver
`docs/architecture/component-version-compatibility.md` depois da
extração). Este documento é a fonte única da lista de caminhos usada pelos
comandos `git filter-repo` das Etapas 5 e 13.

Legenda de proprietário: **CORE** (vai para `sicode-core`), **LEGACY**
(vai para `sicode-legacy`), **ECOSYSTEM** (permanece no monorepo de
integração), **COMPARTILHADO** (usado por mais de um repo, precisa de
cópia temporária documentada), **A REVISAR** (não bloqueia a extração,
mas a classificação não é definitiva).

## 1. Aplicações

| Caminho | Proprietário atual | Proprietário futuro | Destino | Dependências | Motivo | Cópia temporária? | Fonte canônica futura | Ação de remoção |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `apps/sicode-core/` | Ecosystem | CORE | raiz de `sicode-core` | Postgres, Redis, `packages/design-system/theme.css` (COMPARTILHADO), `infra/docker/php84` (COMPARTILHADO) | Aplicação Laravel 13 completa e isolada, sem código Legacy | Não — é o próprio conteúdo movido | `sicode-core` (repo próprio) | Etapa 23: `rm -rf apps/sicode-core` no Ecosystem, só depois de Etapa 22 verde |
| `apps/sicode-legacy/` | Ecosystem | LEGACY | raiz de `sicode-legacy` | MariaDB, Redis, `infra/docker/legacy` | Aplicação Laravel 10 importada, runtime multiunidade ES/SP | Não — é o próprio conteúdo movido | `sicode-legacy` (repo próprio) | Etapa 23: `rm -rf apps/sicode-legacy` no Ecosystem |
| `apps/sicodesk/` | Ecosystem | ECOSYSTEM | permanece | `infra/docker/php84`, `packages/design-system/theme.css` | Fora de escopo desta extração (o pedido não menciona SICODESK) | — | Ecosystem | nenhuma |

## 2. Infraestrutura

| Caminho | Proprietário atual | Proprietário futuro | Destino | Dependências | Motivo | Cópia temporária? | Fonte canônica futura | Ação de remoção |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `infra/docker/legacy/Dockerfile`, `entrypoint.sh`, `README.md` | Ecosystem | LEGACY | `sicode-legacy/infra/docker/` (ou raiz `Dockerfile`) | — | Runtime exclusivo do Legacy, imagem única `sicode-legacy:0.1.0` | Não | `sicode-legacy` | Etapa 23: `rm -rf infra/docker/legacy` no Ecosystem |
| `infra/docker/php84/Dockerfile` | Ecosystem (usado hoje por CORE e SICODESK) | **COMPARTILHADO** | permanece no Ecosystem (para SICODESK); CORE recebe cópia própria | PhpRedis (adicionado no commit `3628133`) | Ver Etapa 3 — decisão registrada abaixo | **Sim**, CORE recebe cópia em `sicode-core/infra/docker/Dockerfile` | Duplicado: Ecosystem (SICODESK) + `sicode-core` (CORE) | Não remove do Ecosystem (SICODESK ainda usa); reavaliar convergência quando SICODESK também for extraído |
| `infra/caddy/Caddyfile` | Ecosystem | ECOSYSTEM | permanece | roteia CORE/SICODESK/Legacy locais | Infra de integração local, não pertence a uma aplicação só | — | Ecosystem | nenhuma |
| `infra/postgres/init.sql` | Ecosystem | ECOSYSTEM | permanece | usado pelo compose central (Postgres CORE + SICODESK) | Script de bootstrap multi-banco do compose de integração | — | Ecosystem | nenhuma |

## 3. Scripts

| Caminho | Proprietário atual | Proprietário futuro | Destino | Dependências | Motivo | Cópia temporária? | Fonte canônica futura | Ação de remoção |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `scripts/e2e/legacy-sp-lifecycle.sh` | Ecosystem | ECOSYSTEM | permanece | CORE + Legacy rodando via compose central | E2E entre sistemas — pedido explícito: "não incluir o E2E CORE → Legacy" nos CI próprios de cada app | — | Ecosystem | nenhuma |
| Não há scripts internos exclusivos de CORE ou Legacy fora de `apps/*` hoje | — | — | — | — | — | — | — | — |

## 4. Orquestração e CI

| Caminho | Proprietário atual | Proprietário futuro | Destino | Dependências | Motivo | Cópia temporária? | Fonte canônica futura | Ação de remoção |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `compose.yaml` | Ecosystem | ECOSYSTEM | permanece, adaptado (Etapa 18) | build context externo ou imagem versionada | Compose de integração, orquestra todos os serviços locais | — | Ecosystem | nenhuma (só adaptação) |
| `Makefile` | Ecosystem | ECOSYSTEM | permanece, adaptado (Etapa 23) | alvos `core-*`/`legacy-*` continuam existindo, mas operando sobre repos externos | Fachada de comandos do monorepo de integração | — | Ecosystem | remove só os alvos que dependiam fisicamente de `apps/*` embutido (nenhum hoje depende do path física além do `docker compose exec`, que não muda) |
| `.github/workflows/sp-clean-ci.yml` | Ecosystem | ECOSYSTEM | permanece, adaptado (Etapa 20) | checkout de 3 repos ou imagens versionadas | CI de integração, não pertence a uma aplicação | — | Ecosystem | nenhuma |
| `packages/design-system/theme.css` | Ecosystem (usado por CORE e SICODESK) | **COMPARTILHADO** | permanece no Ecosystem; CORE recebe cópia própria | `@import` relativo em `apps/sicode-core/resources/css/app.css` e `apps/sicodesk/resources/css/app.css` | Design system compartilhado entre CORE e SICODESK (SICODESK não sai do monorepo) | **Sim**, `sicode-core/resources/css/design-system/theme.css` | Ecosystem (`docs/design-system` + `packages/design-system/theme.css`) | Não remove; CORE mantém cópia até existir mecanismo de distribuição de pacote compartilhado |

## 5. Documentação (`docs/`)

### 5.1 `docs/architecture/`

| Arquivo | Proprietário futuro | Motivo |
| --- | --- | --- |
| `core-application-access-lifecycle.md` | CORE | domínio CORE |
| `core-application-authorization-boundaries.md` | CORE | domínio CORE |
| `core-application-entry-evaluation.md` | CORE | domínio CORE |
| `core-application-launch-protocol.md` | CORE | implementação do lado CORE (servidor) |
| `core-audit-foundation.md` | CORE | domínio CORE |
| `core-contracts-and-application-grants.md` | CORE | domínio CORE |
| `core-hub.md` | CORE | domínio CORE |
| `core-identity-access-canon.md` | CORE | domínio CORE, citado em `AGENTS.md` |
| `core-identity-access-physical-model.md` | CORE | domínio CORE, citado em `AGENTS.md` |
| `core-identity-access-temporal-lifecycle.md` | CORE | domínio CORE |
| `core-identity-domain-model.md` | CORE | domínio CORE |
| `core-local-authentication.md` | CORE | domínio CORE |
| `core-local-password-credentials.md` | CORE | domínio CORE |
| `core-organizations-and-memberships.md` | CORE | domínio CORE |
| `core-application-integration-standard.md` | **ECOSYSTEM** | padrão normativo pra qualquer app integrada ao HUB, não só CORE |
| `core-to-legacy-sp-provisioning.md` | **ECOSYSTEM** | contrato intersistemas (CORE emite, Legacy consome) |
| `legacy-ads-unit-rules.md` | LEGACY | domínio Legacy |
| `legacy-core-integration.md` | LEGACY | integração do ponto de vista consumidor (Legacy), conforme Etapa 4 do pedido |
| `legacy-multi-unit-runtime.md` | LEGACY | runtime multiunidade Legacy |
| `legacy-sp-provisioning.md` | LEGACY | domínio Legacy |
| `legacy-to-core-transition-map.md` | **ECOSYSTEM** | mapa de fronteira/transição entre sistemas, citado em `AGENTS.md` como documento canônico global |

### 5.2 `docs/standards/`

| Arquivo | Proprietário futuro | Motivo |
| --- | --- | --- |
| `hub-integrated-application-runtime.md` | ECOSYSTEM | padrão HUB, referência para qualquer app integrada, citado por CORE e Legacy |
| `redis-isolation.md` | ECOSYSTEM | padrão Redis global |
| `local-projection-lifecycle.md` | ECOSYSTEM | referencia explicitamente docs de CORE e Legacy, é o contrato provisioning/reconciliation entre os dois |
| `core-launch-consumer.md` | ECOSYSTEM | contrato consumido pelo Legacy, emitido pelo CORE |

### 5.3 `docs/deployment/`

| Arquivo | Proprietário futuro | Motivo |
| --- | --- | --- |
| `core-redis-runtime.md` | CORE | runtime Redis exclusivo do CORE |
| `legacy-es-local-instance.md` | LEGACY | deployment ES |
| `legacy-sp-clean-instance.md` | LEGACY | deployment SP Clean |
| `legacy-sp-schema-archive.md` | LEGACY | deployment/classificação do schema archive |

### 5.4 `docs/testing/`

| Arquivo | Proprietário futuro | Motivo |
| --- | --- | --- |
| `core-application-contract-testing.md` | CORE | testes de contrato do lado CORE |
| `core-legacy-sp-end-to-end.md` | ECOSYSTEM | E2E entre sistemas |

### 5.5 `docs/reports/`

| Arquivo | Proprietário futuro | Motivo |
| --- | --- | --- |
| `legacy-sp-historical-snapshot-preservation.md` | LEGACY | investigação específica do banco Legacy SP |
| `legacy-sp-unexpected-data-investigation-2026-07-22.md` | LEGACY | idem |
| `legacy-launch-consumer-blocked-2026-07-16.md` | LEGACY | incidente do lado consumidor (Legacy) |
| `projectv2-7-current-scope-2026-07-15.md`, `projectv2-7-roadmap-revisado-2026-07-14.csv/json/md` | **A REVISAR** | roadmap de produto cross-cutting, não fica claro que pertença a um único repo; permanece no Ecosystem por padrão, não bloqueia a extração |

### 5.6 `docs/inventory/`

| Arquivo | Proprietário futuro | Motivo |
| --- | --- | --- |
| `legacy/*.md` (5 arquivos) | LEGACY | evidência técnica do Legacy, citada em `AGENTS.md` como "não é arquitetura canônica" |
| `repository-split-ownership.md` (este arquivo) | ECOSYSTEM | registro da própria separação |

### 5.7 `docs/runbooks/`

| Arquivo | Proprietário futuro | Motivo |
| --- | --- | --- |
| `core-application-client-secret-rotation.md` | CORE | operação exclusiva do CORE |
| `legacy-sp-provisioning-secret-rotation.md` | LEGACY | operação exclusiva do Legacy |

### 5.8 `docs/decisions/` (ADRs)

Todas as 4 ADRs (`ADR-001`…`ADR-004`) → **ECOSYSTEM**. ADRs são registros
históricos de decisão global, mesmo quando o assunto é específico de um
lado (ex.: ADR-003 é sobre o Legacy) — não são reescritas nem movidas,
permanecem como fonte de contexto arquitetural do Ecosystem.

### 5.9 Ecosystem-only (sem exceções)

`docs/agent/`, `docs/checklists/`, `docs/ci/`, `docs/design-system/`,
`docs/development/`, `docs/skills/` (todas as subpastas), `docs/templates/`
→ **ECOSYSTEM**. São normativos de tooling/processo/design compartilhados
por todo o monorepo, referenciados por `AGENTS.md`.

## 6. Raiz do repositório

| Caminho | Proprietário atual | Proprietário futuro | Ação |
| --- | --- | --- | --- |
| `AGENTS.md` | Ecosystem | ECOSYSTEM | Etapa 23: reescrito para apontar pros repos irmãos como fonte de verdade de CORE/Legacy, remove seções sobre topologia embutida |
| `README.md` | não existe hoje | ECOSYSTEM (novo) | Etapa 23: criado do zero explicando a nova topologia de 3 repos |
| `.env.example` | Ecosystem | ECOSYSTEM | permanece (é do compose de integração) |
| `.agents/`, `.claude/`, `.codex/` | Ecosystem | ECOSYSTEM | configuração de agentes/tooling, não pertence a uma aplicação |

## 7. Decisão registrada — Etapa 3: runtime PHP 8.4

**Opção adotada: A + B híbrido**, seguindo a recomendação do pedido:

- **CORE recebe `Dockerfile` próprio**, cópia de `infra/docker/php84/Dockerfile`
  no estado atual (já inclui PhpRedis, adicionado no commit `3628133 fix(core):
  adopt isolated Redis runtime`). Vive em `sicode-core/infra/docker/Dockerfile`.
- **SICODESK continua usando o runtime do Ecosystem**
  (`infra/docker/php84/Dockerfile`, inalterado). Não é afetado por esta
  extração.
- **Nenhum repositório de imagem base é criado nesta tarefa** (opção C
  rejeitada por enquanto — menor acoplamento operacional agora é ter cada
  app dona do próprio Dockerfile do que introduzir um terceiro repo de
  runtime compartilhado antes de existir massa crítica para justificá-lo).

Duplicação temporária aceita e documentada: os dois Dockerfiles (Ecosystem
e `sicode-core`) podem divergir ao longo do tempo — isso é esperado e
aceitável, cada um evolui conforme a necessidade da sua aplicação. Critério
de convergência futura: se/quando SICODESK também for extraído, reavaliar
se faz sentido um repositório de imagem base versionada (opção C).

Mesmo raciocínio aplicado a `packages/design-system/theme.css`: cópia
própria em `sicode-core/resources/css/design-system/theme.css`, ajustando
o `@import` em `resources/css/app.css` de `../../../../packages/design-system/theme.css`
para o caminho local. Fonte canônica de design continua sendo
`docs/design-system/` no Ecosystem; divergência entre a cópia do CORE e a
fonte canônica deve ser tratada como dívida técnica documentada, não como
bug.

## 8. Resumo do comando `git filter-repo` (Etapa 5, Legacy)

Caminhos com `--path` (raiz do monorepo original):

```
apps/sicode-legacy/
infra/docker/legacy/
docs/architecture/legacy-ads-unit-rules.md
docs/architecture/legacy-core-integration.md
docs/architecture/legacy-multi-unit-runtime.md
docs/architecture/legacy-sp-provisioning.md
docs/deployment/legacy-es-local-instance.md
docs/deployment/legacy-sp-clean-instance.md
docs/deployment/legacy-sp-schema-archive.md
docs/inventory/legacy/
docs/reports/legacy-sp-historical-snapshot-preservation.md
docs/reports/legacy-sp-unexpected-data-investigation-2026-07-22.md
docs/reports/legacy-launch-consumer-blocked-2026-07-16.md
docs/runbooks/legacy-sp-provisioning-secret-rotation.md
```

`--path-rename apps/sicode-legacy/:` move a app para a raiz;
`--path-rename infra/docker/legacy/:infra/docker/` achata o Dockerfile;
os `docs/*` preservados ficam sob `docs/inherited-from-ecosystem/<mesmo caminho>`
via `--path-rename docs/:docs/inherited-from-ecosystem/` (aplicado só aos
caminhos `docs/*` explicitamente listados acima, não a `docs/` inteiro).

## 9. Resumo do comando `git filter-repo` (Etapa 13, CORE)

Caminhos com `--path`:

```
apps/sicode-core/
docs/architecture/core-application-access-lifecycle.md
docs/architecture/core-application-authorization-boundaries.md
docs/architecture/core-application-entry-evaluation.md
docs/architecture/core-application-launch-protocol.md
docs/architecture/core-audit-foundation.md
docs/architecture/core-contracts-and-application-grants.md
docs/architecture/core-hub.md
docs/architecture/core-identity-access-canon.md
docs/architecture/core-identity-access-physical-model.md
docs/architecture/core-identity-access-temporal-lifecycle.md
docs/architecture/core-identity-domain-model.md
docs/architecture/core-local-authentication.md
docs/architecture/core-local-password-credentials.md
docs/architecture/core-organizations-and-memberships.md
docs/deployment/core-redis-runtime.md
docs/testing/core-application-contract-testing.md
docs/runbooks/core-application-client-secret-rotation.md
```

`--path-rename apps/sicode-core/:` move a app para a raiz; os `docs/*`
preservados ficam sob `docs/inherited-from-ecosystem/<mesmo caminho>`,
mesmo padrão do Legacy. `packages/design-system/theme.css` **não** entra
no filter-repo (é COMPARTILHADO) — é copiado manualmente depois, como
arquivo novo sem histórico prévio no repo CORE.
