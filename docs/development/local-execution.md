# Execucao local do SICODE Ecosystem

Este documento descreve a estrutura executavel inicial do monorepo.

## Matriz de stacks

| Aplicacao | Laravel | Livewire | PHP | Banco |
| --- | ---: | ---: | ---: | --- |
| SICODE CORE | 13 | 4 | 8.4 | PostgreSQL |
| SICODESK | 13 | 4 | 8.4 | PostgreSQL |
| SICODE Legacy | 10 | 2 | 8.2 local | MariaDB 11 |

Exemplos e comandos de uma stack nao devem ser aplicados automaticamente a outra.

## Topologia

```text
apps/
├── sicode-core/
├── sicodesk/
└── sicode-legacy/

infra/
├── caddy/
├── docker/
│   ├── php84/
│   └── legacy/
└── postgres/
```

## Aplicacoes

CORE e SICODESK sao aplicacoes Laravel independentes. Cada uma possui:

- `composer.json`;
- `composer.lock`;
- `vendor`;
- `artisan`;
- `.env.example`;
- migrations;
- testes;
- configuracao propria.

Nao ha autoload cruzado entre aplicacoes.

## Legacy

`apps/sicode-legacy` contem o codigo Legacy real importado de `BiGSerial/SICODE2.git`. A execucao local usa PHP 8.2 e MariaDB 11 isolado no compose do monorepo.

## Bancos locais

O compose prepara dois bancos PostgreSQL independentes:

- `sicode_core`;
- `sicodesk`.

O Legacy importado suporta duas instâncias locais isoladas sobre a mesma base de código:

- **Instância SP (`sicode-legacy`)**:
  - servico: `sicode-legacy`;
  - banco: `sicode_legacy` (container `sicode-legacy-mariadb`, porta host `3311`);
  - unidade: `sp` (`SICODE_IDENTITY_MODE=provisioning`);
  - porta host: `http://localhost:8083`.

- **Instância ES (`sicode-legacy-es`)**:
  - servico: `sicode-legacy-es`;
  - banco: `sicode` (container MariaDB existente `tools_mariadb`, rede `database_default`);
  - unidade: `es` (`SICODE_IDENTITY_MODE=reconciliation`);
  - porta host: `http://localhost:8084`.

As credenciais locais sao descartaveis e configuradas por variaveis do `compose.yaml`; nao versionar `.env` real.

## Fachada oficial

Use o `Makefile` na raiz:

```bash
make build
make up
make health
make core-analyse
make core-quality
make core-test
make core-test-pgsql
make core-migrate
make sicodesk-test
make sicodesk-migrate
make legacy-test
make legacy-migrate
make logs
make down
```

Comandos Legacy disponiveis apos a importacao do codigo real:

```bash
make legacy-shell
make legacy-migrate
make legacy-test
make legacy-test-es
make legacy-test-sp
make legacy-test-matrix
```

O runtime Legacy local inicia como unidade ES por padrao:

```bash
SICODE_LEGACY_UNIT=es SICODE_LEGACY_CORE_CONTEXT=ES docker compose up -d sicode-legacy
```

Para validar o mesmo codigo em configuracao SP sem provisionar uma segunda instancia:

```bash
docker compose exec -T -e APP_ENV=testing -e SICODE_UNIT=sp -e CORE_LAUNCH_CONTEXT=SP sicode-legacy php artisan test tests/Unit/SicodeMultiUnitRuntimeTest.php tests/Feature/CoreLaunchUnitContextTest.php --env=testing
```

O gate focado da matriz multiunidade pode ser executado por:

```bash
make legacy-test-matrix
```

Testes de integracao CORE -> Legacy sobre a base restaurada `sicode_legacy` exigem autorizacao explicita para evitar execucao acidental contra banco incorreto:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Feature/CoreLaunchConsumerTest.php --env=testing
```

Para validar o contrato CORE -> Legacy e o hardening operacional de Productions em conjunto:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/ProductionCompanyContextTest.php --env=testing
```

Para validar tambem o hardening operacional de Informe de Obra:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/ProductionCompanyContextTest.php tests/Feature/WorkReportCompanyContextTest.php --env=testing
```

Esses testes devem usar transacoes ou limpeza seletiva. Nao use `RefreshDatabase`, `DatabaseMigrations`, `migrate:fresh`, `db:wipe`, truncates globais ou drops contra `sicode_legacy`.

## Health checks

Via Caddy:

- `http://localhost:8090/core/health`
- `http://localhost:8090/sicodesk/health`

Direto nos apps:

- `http://localhost:8081/health`
- `http://localhost:8082/health`

Os health checks verificam inicialmente apenas resposta da aplicacao.

## Testes PostgreSQL do CORE

Use `make core-test-pgsql` para validar constraints e comportamento especifico do PostgreSQL nas migrations canonicas do CORE.

## Ambiente testing do CORE

O ambiente de testes do `apps/sicode-core` usa uma combinacao controlada:

- `.env.testing` define os valores minimos e seguros para o bootstrap Laravel em `APP_ENV=testing`;
- `phpunit.xml` define o ambiente PHPUnit e usa PostgreSQL, nao SQLite;
- a `APP_KEY` versionada nesses arquivos e fixa, descartavel e exclusiva de testes;
- o Compose injeta as variaveis de runtime local e pode sobrescrever detalhes do PostgreSQL por ambiente, sem depender de `.env` pessoal.

No container oficial, `php artisan test` e `vendor/bin/phpunit` devem executar sem um arquivo `.env` local. Nao monte nem versione `.env` real para testes.

## Analise estatica do CORE

Use `make core-analyse` para executar PHPStan com Larastan no SICODE CORE. O comando oficial usa `--memory-limit=512M` para evitar falhas de infraestrutura no worker paralelo do PHPStan.

Politica inicial:

- nivel: 5;
- caminhos: `app`, `routes`, `database/migrations`, `tests`;
- baseline: nao utilizado;
- ignores genericos: nao utilizados.

O nivel 5 foi escolhido para cobrir o bootstrap Laravel, migrations e testes sem gerar baseline inicial. A evolucao esperada e aumentar o nivel quando houver mais codigo de dominio e contratos estabilizados.

Use `make core-quality` para executar o gate local composto do CORE: Composer validate, Pint, PHPStan/Larastan, testes gerais e testes PostgreSQL.

## Memberships organizacionais do CORE

O modelo fisico aprovado permite que um usuario tenha zero, um ou varios `organization_memberships` ativos em organizacoes diferentes. A constraint PostgreSQL impede apenas o mesmo par ativo `user_id + organization_id` duplicado.

Essa decisao evita recriar `users.company_id` como principalidade global. Quando uma aplicacao exigir um vinculo efetivo, a escolha deve ser derivada por regra transacional do CORE, conforme o contexto da aplicacao.

## Segredos

Arquivos `.env` reais nao devem ser versionados. Os `.env.example` nao contem secrets reais. `APP_KEY` deve ser gerada por ambiente.
