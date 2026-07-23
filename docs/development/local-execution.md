# Execução local do SICODE Ecosystem

Este documento descreve a execução local do modelo multi-repositório.
CORE e Legacy foram extraídos para repositórios irmãos independentes
(`sicode-core`, `sicode-legacy`) e não vivem mais dentro deste repositório
— ver `docs/inventory/repository-split-ownership.md` e
`docs/architecture/component-version-compatibility.md`.

## Topologia oficial

Os três repositórios devem ser clonados lado a lado, num diretório comum:

```text
/home/will/code/
├── ecosystem      # este repositório — infraestrutura, compose, E2E, docs globais
├── sicode-core    # BiGSerial/sicode-core
└── sicode-legacy  # BiGSerial/sicode-legacy
```

**Não use Git submodules.** Cada repositório é um clone independente,
gerenciado com seu próprio `git remote`, seu próprio histórico e seu
próprio pipeline de qualidade. O Ecosystem referencia os outros dois
apenas por caminho relativo (`../sicode-core`, `../sicode-legacy`) nos
build contexts do Compose — nunca por submodule, nunca por cópia de
código para dentro deste repositório.

### Clonando os três repositórios

```bash
mkdir -p ~/code && cd ~/code
git clone git@github.com:BiGSerial/sicode-ecosystem.git ecosystem
git clone git@github.com:BiGSerial/sicode-core.git
git clone git@github.com:BiGSerial/sicode-legacy.git
```

`sicode-core` e `sicode-legacy` são repositórios privados — é preciso ter
acesso de leitura concedido na organização `BiGSerial` para cloná-los.

### Ordem de instalação

1. `sicode-core` e `sicode-legacy` — cada um é uma aplicação Laravel
   independente com seu próprio `composer.json`/`composer.lock`. Não há
   autoload cruzado entre eles nem com o Ecosystem.
2. `ecosystem` — não tem dependências de aplicação próprias; orquestra os
   outros dois via Compose.

Dentro de cada repositório de componente, siga o próprio `README.md`
(`composer install`, `.env`, etc.) se for rodar fora do Compose. Para rodar
via Compose (recomendado), os passos de instalação de dependências
acontecem dentro do container — não é necessário `composer install` no
host.

## Matriz de stacks

| Aplicação | Laravel | Livewire | PHP | Banco |
| --- | ---: | ---: | ---: | --- |
| SICODE CORE | 13 | 4 | 8.4 | PostgreSQL |
| SICODESK | 13 | 4 | 8.4 | PostgreSQL |
| SICODE Legacy | 10 | 2 | 8.2 local | MariaDB 11 |

Exemplos e comandos de uma stack não devem ser aplicados automaticamente a
outra.

## Build contexts

`compose.yaml`, serviços `sicode-core`/`sicode-legacy`/`sicode-legacy-es`/
`sicode-legacy-snapshot`, resolvem os build contexts por variável de
ambiente, com defaults já apontando para os repositórios irmãos:

```text
SICODE_CORE_BUILD_CONTEXT     default: ../sicode-core
SICODE_CORE_SOURCE_DIR        default: ../sicode-core
SICODE_CORE_DOCKERFILE        default: infra/docker/Dockerfile

SICODE_LEGACY_BUILD_CONTEXT   default: ../sicode-legacy
SICODE_LEGACY_SOURCE_DIR      default: ../sicode-legacy
SICODE_LEGACY_DOCKERFILE      default: infra/docker/Dockerfile
```

Com a topologia oficial (os três repositórios lado a lado), **nenhuma
variável de ambiente precisa ser exportada manualmente** — os defaults já
resolvem para os caminhos corretos a partir da raiz do `ecosystem`.

Nenhum caminho de build deve apontar para `apps/sicode-core` ou
`apps/sicode-legacy` — esses diretórios foram removidos deste repositório
e não devem ser recriados.

Para consumir uma imagem já publicada em vez de buildar localmente: defina
`SICODE_CORE_IMAGE`/`SICODE_CORE_TAG` e
`SICODE_LEGACY_IMAGE`/`SICODE_LEGACY_SP_TAG`/`SICODE_LEGACY_ES_TAG`, faça
`docker pull` da imagem correspondente, e rode `docker compose up` **sem**
`--build` — o Compose reaproveita a imagem local já marcada com aquela tag
em vez de reconstruir.

## Subindo o stack

```bash
cd ~/code/ecosystem
make build
make up
make health
```

Ou diretamente via Compose:

```bash
docker compose build sicode-core sicode-legacy
docker compose up -d --wait redis sicode-postgres sicode-legacy-sp-mariadb sicode-core sicode-legacy
```

## Bancos locais

O compose prepara dois bancos PostgreSQL independentes:

- `sicode_core`;
- `sicodesk`.

O Legacy importado suporta três instâncias locais isoladas sobre a mesma
base de código (ver `docs/architecture/legacy-multi-unit-runtime.md`):

- **SP Clean (`sicode-legacy`)** — instância canônica de desenvolvimento SP:
  - serviço: `sicode-legacy`;
  - banco: `sicode_sp` (container `sicode-legacy-sp-mariadb`, porta host `3312`);
  - unidade: `sp` (`SICODE_IDENTITY_MODE=provisioning`);
  - porta host: `http://localhost:8083`.

- **ES (`sicode-legacy-es`)**:
  - serviço: `sicode-legacy-es`;
  - banco: `sicode` (container MariaDB existente `tools_mariadb`, rede `database_default`);
  - unidade: `es` (`SICODE_IDENTITY_MODE=reconciliation`);
  - porta host: `http://localhost:8084`.

- **Legacy SP Schema Archive (`sicode-legacy-snapshot`, profile `snapshot`)** — só schema, sem dados históricos restauráveis; não é backup nem fonte de restore. Não sobe com `docker compose up` padrão. Ver `docs/deployment/legacy-sp-schema-archive.md`:
  - serviço: `sicode-legacy-snapshot`;
  - banco: `sicode_legacy` (container `sicode-legacy-snapshot-mariadb`, porta host `3313`);
  - porta host: `http://localhost:8085`.

As credenciais locais são descartáveis e configuradas por variáveis do
`compose.yaml`; não versionar `.env` real.

### Redis local

Instância única (`compose.yaml`, serviço `redis`), isolada por prefixo de
chave + faixa de DB por aplicação/unidade (ver
`docs/standards/redis-isolation.md`):

| Aplicação/unidade | Redis DBs | Prefixo |
| --- | --- | --- |
| Legacy ES | 0-3 | `sicode:legacy:es:` |
| Legacy SP Clean | 4-7 | `sicode:legacy:sp:` |
| Legacy SP Schema Archive | 8-11 | `sicode:legacy:snapshot:` |
| CORE (global) | 12-15 | `sicode:core:global:` |

Gates: `make core-redis-smoke`, `make core-runtime-isolation-test`,
`make legacy-redis-inspect`, `make legacy-runtime-isolation-test`.

## Fachada oficial

Use o `Makefile` na raiz do `ecosystem`:

```bash
make build
make up
make health
make core-analyse
make core-quality
make core-test
make core-test-pgsql
make core-migrate
make core-redis-smoke
make core-runtime-isolation-test
make sicodesk-test
make sicodesk-migrate
make legacy-shell
make legacy-migrate
make legacy-test
make legacy-test-es
make legacy-test-sp
make legacy-test-matrix
make logs
make down
```

O runtime Legacy local inicia como unidade ES por padrão:

```bash
SICODE_LEGACY_UNIT=es SICODE_LEGACY_CORE_CONTEXT=ES docker compose up -d sicode-legacy
```

Para validar o mesmo código em configuração SP sem provisionar uma segunda
instância:

```bash
docker compose exec -T -e APP_ENV=testing -e SICODE_UNIT=sp -e CORE_LAUNCH_CONTEXT=SP sicode-legacy php artisan test tests/Unit/SicodeMultiUnitRuntimeTest.php tests/Feature/CoreLaunchUnitContextTest.php --env=testing
```

O gate focado da matriz multiunidade pode ser executado por:

```bash
make legacy-test-matrix
```

Testes de integração CORE -> Legacy sobre a base restaurada
`sicode_legacy` exigem autorização explícita para evitar execução
acidental contra banco incorreto:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Feature/CoreLaunchConsumerTest.php --env=testing
```

Para validar o contrato CORE -> Legacy e o hardening operacional de
Productions em conjunto:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/ProductionCompanyContextTest.php --env=testing
```

Para validar também o hardening operacional de Informe de Obra:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/ProductionCompanyContextTest.php tests/Feature/WorkReportCompanyContextTest.php --env=testing
```

Esses testes devem usar transações ou limpeza seletiva. Não use
`RefreshDatabase`, `DatabaseMigrations`, `migrate:fresh`, `db:wipe`,
truncates globais ou drops contra `sicode_legacy`.

## E2E CORE → Legacy SP Clean

```bash
docker compose up -d --wait redis sicode-postgres sicode-legacy-sp-mariadb sicode-core sicode-legacy
SICODE_E2E_ALLOWED=true LEGACY_TEST_DATABASE_ALLOWED=true bash scripts/e2e/legacy-sp-lifecycle.sh
```

Cobre provisioning real, idempotência, Application Launch, exchange,
sessão Legacy, `CurrentCompanyContext`, lifecycle, ADS e cleanup/
verify-clean. Ver `docs/ci/sp-clean-integration-pipeline.md` para o
equivalente em CI.

## Health checks

Via Caddy:

- `http://localhost:8090/core/health`
- `http://localhost:8090/sicodesk/health`

Direto nos apps:

- `http://localhost:8081/health`
- `http://localhost:8082/health`

Os health checks verificam inicialmente apenas resposta da aplicação.

## Testes PostgreSQL do CORE

Use `make core-test-pgsql` para validar constraints e comportamento
específico do PostgreSQL nas migrations canônicas do CORE.

## Ambiente testing do CORE

O ambiente de testes do `sicode-core` usa uma combinação controlada:

- `.env.testing` define os valores mínimos e seguros para o bootstrap
  Laravel em `APP_ENV=testing`;
- `phpunit.xml` define o ambiente PHPUnit e usa PostgreSQL, não SQLite;
- a `APP_KEY` versionada nesses arquivos é fixa, descartável e exclusiva
  de testes;
- o Compose injeta as variáveis de runtime local e pode sobrescrever
  detalhes do PostgreSQL por ambiente, sem depender de `.env` pessoal.

No container oficial, `php artisan test` e `vendor/bin/phpunit` devem
executar sem um arquivo `.env` local. Não monte nem versione `.env` real
para testes.

## Análise estática do CORE

Use `make core-analyse` para executar PHPStan com Larastan no SICODE CORE.
O comando oficial usa `--memory-limit=512M` para evitar falhas de
infraestrutura no worker paralelo do PHPStan.

Política inicial:

- nível: 5;
- caminhos: `app`, `routes`, `database/migrations`, `tests`;
- baseline: não utilizado;
- ignores genéricos: não utilizados.

Use `make core-quality` para executar o gate local composto do CORE:
Composer validate, Pint, PHPStan/Larastan, testes gerais e testes
PostgreSQL. A suíte completa própria de cada componente vive no workflow
`quality.yml` do respectivo repositório (`sicode-core`, `sicode-legacy`) —
o Ecosystem não duplica essas suítes, apenas roda o smoke necessário para
o E2E cross-repositório (ver `docs/ci/sp-clean-integration-pipeline.md`).

## Memberships organizacionais do CORE

O modelo físico aprovado permite que um usuário tenha zero, um ou vários
`organization_memberships` ativos em organizações diferentes. A constraint
PostgreSQL impede apenas o mesmo par ativo `user_id + organization_id`
duplicado.

## Atualizando um componente (CORE ou Legacy)

```bash
cd ~/code/sicode-core   # ou sicode-legacy
git pull origin main
cd ~/code/ecosystem
docker compose build sicode-core   # ou sicode-legacy
docker compose up -d --wait sicode-core
```

Não é preciso mexer no Ecosystem para atualizar código de componente — o
Compose sempre reflete o estado atual do checkout irmão.

## Trocando a ref/tag de um componente em CI

O workflow `.github/workflows/sp-clean-ci.yml` fixa `CORE_REF`/`LEGACY_REF`
por SHA (ou, futuramente, por tag `v0.1.0+`) em vez de `main`. Para validar
uma versão diferente:

1. atualize `env.CORE_REF`/`env.LEGACY_REF` no workflow;
2. confirme que a ref existe no repositório correspondente
   (`git ls-remote origin <ref>`);
3. rode o workflow (push, PR, ou `workflow_dispatch`).

Localmente, o equivalente é simplesmente fazer checkout da ref desejada
dentro de `~/code/sicode-core`/`~/code/sicode-legacy` antes de
`docker compose build`.

## Proibição de submodules

Este projeto não usa Git submodules para consumir `sicode-core` ou
`sicode-legacy`. A integração acontece inteiramente por:

- caminho relativo de build context (`../sicode-core`, `../sicode-legacy`)
  no Compose, assumindo os três repositórios lado a lado;
- checkout lado a lado em CI (`actions/checkout` para cada repositório,
  sem submodule);
- versionamento por SHA/tag fixado em variável de ambiente, nunca por
  referência de submodule no `.git`.

Não adicione `.gitmodules` a este repositório.

## Segredos

Arquivos `.env` reais não devem ser versionados. Os `.env.example` não
contêm secrets reais. `APP_KEY` deve ser gerada por ambiente.

Autenticação de CI entre repositórios privados (checkout de
`sicode-core`/`sicode-legacy` a partir do workflow do Ecosystem) usa o
secret `SICODE_COMPONENTS_READ_TOKEN` — ver
`docs/ci/sp-clean-integration-pipeline.md#checkout-multi-repositório` para
o escopo do token e o passo manual de cadastro. Nunca registrar o valor
desse token em workflow, `.env`, compose, logs, ou documentação.

## Troubleshooting

- **`docker compose build` falha com "no such file or directory" para
  `../sicode-core` ou `../sicode-legacy`**: confirme que os três
  repositórios estão clonados lado a lado, com esses nomes exatos de
  diretório, dentro do mesmo diretório pai.
- **Serviço `sicode-legacy-es` ou `sicode-legacy-snapshot` sobe sem ter
  sido pedido**: confira o `profile`/lista de serviços passada a
  `docker compose up` — o E2E SP Clean nunca deve subir esses dois.
- **`sicode_sp` não está vazio antes das migrations**: rode
  `docker compose down --remove-orphans` (sem `-v`) e confirme que não há
  volume `sicode-legacy-sp-clean-storage-*` residual de uma execução
  anterior com dados que não deveriam persistir; se o volume for
  intencional (dev contínuo), rode as migrations normalmente — a
  verificação de "banco vazio" é uma garantia de CI, não uma regra local
  obrigatória.
- **Erro de autenticação ao clonar `sicode-core`/`sicode-legacy`**:
  confirme acesso de leitura na organização `BiGSerial` para esses dois
  repositórios privados; em CI, confirme que o secret
  `SICODE_COMPONENTS_READ_TOKEN` está cadastrado e ainda não expirou.
