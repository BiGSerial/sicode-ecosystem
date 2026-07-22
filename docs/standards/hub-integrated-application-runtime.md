# Padrão: runtime obrigatório para aplicações integradas ao HUB

Data: 2026-07-22

Status: Normativo. Referência de implementação: SICODE Legacy (ES e SP).

## Objetivo

Definir o baseline de runtime que toda aplicação/contexto integrado ao
SICODE HUB (via CORE) deve seguir, para que isolamento entre
aplicações/contextos seja uma propriedade estrutural do runtime — não algo
que dependa de disciplina manual em cada deploy.

Este documento é o guarda-chuva. Detalhes específicos vivem em documentos
próprios linkados abaixo.

## Identidade da aplicação/contexto

Toda aplicação HUB declara, via variáveis de ambiente carregadas no boot do
container (nunca inferidas em runtime a partir de sessão, cookie, header ou
request):

- `SICODE_UNIT` (ou equivalente): identificador do contexto operacional
  (ex.: `es`, `sp`). Resolvido uma única vez no boot via singleton — ver
  `App\Support\CurrentUnit` / `App\Providers\UnitServiceProvider` como
  referência de implementação;
- `SICODE_INSTANCE_CODE` / `SICODE_INSTANCE_NAME`: identificador estável
  para logs, auditoria e correlação;
- `APP_KEY`: **único por aplicação/contexto**, nunca compartilhado entre
  unidades (ex.: ES e SP de uma mesma aplicação não podem usar o mesmo
  `APP_KEY`, mesmo sendo o mesmo código-fonte — compartilhar `APP_KEY`
  permite que um cookie/sessão criptografado por uma unidade seja
  decriptável pela outra).

## Container

- um container por aplicação/contexto (não multiplexar ES e SP no mesmo
  processo PHP-FPM/`artisan serve`);
- código-fonte pode ser compartilhado via bind mount entre
  aplicação/contexto quando é literalmente o mesmo codebase parametrizado
  por env (caso do Legacy ES/SP) — mas **storage nunca pode ser
  compartilhado** (ver `#storage`);
- `healthcheck` obrigatório, com `depends_on: condition: service_healthy`
  (não `service_started`) para toda dependência de infraestrutura (banco,
  Redis);
- imagens com versão de base fixada, nunca `latest` ou tag major flutuante
  sem minor (`redis:7.4-alpine`, não `redis:7-alpine`).

## Cache, sessão, filas, locks

Redis é o backend oficial. Ver `docs/standards/redis-isolation.md` para o
padrão completo de prefixos, conexões lógicas e números de DB por
finalidade.

Resumo do que toda aplicação/contexto deve setar:

```text
CACHE_DRIVER=redis
SESSION_DRIVER=redis
SESSION_STORE=redis_session   # ou equivalente: store dedicada para sessão
SESSION_CONNECTION=session
QUEUE_CONNECTION=redis
SESSION_COOKIE=sicode_{unit}_session
REDIS_PREFIX=sicode:{app}:{unit}:
```

## Storage

`storage/app` (uploads, exports, arquivos temporários) e `storage/logs`
(logs operacionais) devem ser volumes fisicamente distintos por
aplicação/contexto — nunca o mesmo bind mount/volume entre unidades, mesmo
quando o código-fonte é compartilhado.

Referência de implementação: `compose.yaml`, serviços `sicode-legacy` e
`sicode-legacy-es`, cada um com `sicode-legacy-{unit}-storage-app` e
`sicode-legacy-{unit}-storage-logs` como volumes nomeados próprios,
montados por cima do bind mount de código compartilhado. Um script de
entrypoint (`infra/docker/legacy/entrypoint.sh`) garante que a estrutura de
diretórios exigida pelo Laravel (`storage/app/public`,
`storage/framework/*`, `storage/logs`) existe em qualquer volume novo/vazio
antes do boot.

`storage/framework/views` (Blade compilado) pode continuar em um caminho
compartilhado/efêmero por container — não contém dado de negócio, é
determinístico a partir do template-fonte, e recompilar não tem custo de
correção, apenas performance de primeiro request.

## Banco de dados local

- uma aplicação/contexto nunca compartilha schema/tabelas com outra;
- o nome do banco esperado por unidade deve ser declarado em config
  (`SICODE_EXPECTED_DATABASE` no Legacy) e validado no boot — ver
  `#guards-de-boot`;
- convenção de nomes de banco por ambiente (local/staging/produção) fica a
  cargo de cada aplicação, mas deve ser documentada explicitamente (ver
  checklist de onboarding).

## Provisioning vs. reconciliation

Ver `docs/standards/local-projection-lifecycle.md` para o padrão completo.
Resumo: cada aplicação/contexto declara `SICODE_IDENTITY_MODE` como
`provisioning` (CORE cria a projeção local via contrato técnico) ou
`reconciliation` (a aplicação resolve/vincula identidade já existente
localmente, sem criar). Um contexto nunca deve permitir os dois modos ao
mesmo tempo sem decisão explícita documentada.

## ApplicationClient / Launch callback / exchange backend-to-backend

Ver `docs/standards/core-launch-consumer.md`. Resumo: toda aplicação
consumidora do CORE Application Launch Protocol implementa uma camada
anticorrupção própria (`CoreIntegration` no Legacy), nunca aceita identidade
vinda do navegador como autoridade, e troca o código de lançamento por
canal backend-to-backend autenticado.

## Vínculos de identidade e organização

`core_identity_links` (usuário CORE → usuário local) e
`core_organization_links` (organização CORE → empresa/tenant local) são
estruturas locais próprias de cada aplicação/contexto, nunca tabelas
compartilhadas entre unidades. Ver ADR-002 e
`docs/architecture/legacy-multi-unit-runtime.md` para o contrato de
cardinalidade.

## Guards de boot

Toda aplicação/contexto deve recusar subir (lançar exceção no boot, antes
de atender qualquer request) quando:

- o banco de dados configurado não corresponde ao esperado para a unidade;
- o prefixo Redis não corresponde ao padrão esperado para a unidade;
- o cookie de sessão não corresponde ao padrão esperado (ou colide com
  outra unidade conhecida);
- o prefixo de storage não corresponde ao padrão esperado;
- o contexto CORE (`CORE_LAUNCH_CONTEXT`) não corresponde ao contexto da
  unidade;
- `SICODE_IDENTITY_MODE=provisioning` está habilitado em uma unidade que
  não deveria provisionar (ex.: ES).

Nenhuma mensagem de guard deve expor senha, secret ou credencial — apenas o
nome do parâmetro que divergiu.

Referência de implementação: `App\Support\RuntimeIsolationGuard`
(`apps/sicode-legacy/app/Support/RuntimeIsolationGuard.php`), acionado em
`UnitServiceProvider::boot()`, habilitado via
`SICODE_ISOLATION_GUARD_ENABLED=true` nos containers reais e desabilitado
por padrão em testes (para não exigir que toda suíte de testes replique o
fingerprint completo de runtime).

## Auditoria e logs

- eventos de auditoria nunca registram segredo, token bruto ou payload de
  request não validado;
- logs operacionais ficam no volume de storage próprio da
  aplicação/contexto (ver `#storage`), nunca compartilhados.

## Healthcheck e readiness

- `healthcheck` de container testa a aplicação respondendo, não apenas o
  processo vivo;
- dependências externas (banco, Redis) usam `condition: service_healthy`.

## Secrets

- nunca versionar secret real em `.env.example`/`compose.yaml` — apenas
  defaults de desenvolvimento local claramente identificados como tal
  (ex.: `legacy_dev_password`);
- em ambientes não locais, Redis e bancos exigem autenticação
  (`REDIS_PASSWORD`/`requirepass`), injetada via secret manager, nunca em
  arquivo versionado.

## Migrations e rollback

- migrations são aditivas por padrão; mudanças destrutivas exigem plano de
  rollback documentado no PR;
- nenhuma migration deve ramificar comportamento por unidade/contexto (ver
  `tests/Unit/SicodeMultiUnitRuntimeTest.php::test_migrations_do_not_branch_schema_by_unit`
  como exemplo de teste que garante isso).

## Testes obrigatórios

Toda aplicação HUB deve ter, no mínimo:

1. teste de guard de boot (config inválida recusa subir) — sem I/O real;
2. teste de isolamento físico de Redis (cache/sessão/fila/lock) contra um
   Redis real, gated por flag explícita de opt-in;
3. teste de que o contexto CORE (Launch) é validado contra a unidade
   configurada;
4. teste de que provisioning/reconciliation não se misturam.

Ver `apps/sicode-legacy/tests/Unit/RuntimeIsolationGuardTest.php` e
`apps/sicode-legacy/tests/Feature/RedisRuntimeIsolationTest.php` como
referência.

## Fora de escopo

Este padrão **não** cobre, e cada decisão abaixo exige ADR própria quando
adotada:

- Laravel Horizon;
- workers de fila ativos em produção local;
- scheduler (`schedule:run`) ativo em produção local;
- Redis Cluster / Sentinel;
- OAuth2/OIDC completo para o Launch Protocol (hoje é secret compartilhado
  por cliente, ver ADR-002).

## Documentos relacionados

- `docs/standards/redis-isolation.md`
- `docs/standards/core-launch-consumer.md`
- `docs/standards/local-projection-lifecycle.md`
- `docs/templates/hub-application-onboarding-checklist.md`
- `docs/decisions/ADR-002-core-launch-protocol-and-legacy-consumer.md`
- `docs/decisions/ADR-003-sicode-legacy-multi-unit-runtime.md`
- `docs/reports/legacy-sp-unexpected-data-investigation-2026-07-22.md`
