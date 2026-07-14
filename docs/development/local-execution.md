# Execucao local do SICODE Ecosystem

Este documento descreve a estrutura executavel inicial do monorepo.

## Matriz de stacks

| Aplicacao | Laravel | Livewire | PHP | Banco |
| --- | ---: | ---: | ---: | --- |
| SICODE CORE | 13 | 4 | 8.4 | PostgreSQL |
| SICODESK | 13 | 4 | 8.4 | PostgreSQL |
| SICODE Legacy | 10 | 2 | compativel com o legado | conforme legado |

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

`apps/sicode-legacy` e reservado para importacao futura do codigo real. Nenhuma aplicacao Laravel 10 vazia foi gerada para simular o Legacy.

## Bancos locais

O compose prepara dois bancos PostgreSQL independentes:

- `sicode_core`;
- `sicodesk`.

O banco Legacy nao e configurado enquanto o codigo real e sua tecnologia de conexao nao forem confirmados.

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
make logs
make down
```

Comandos Legacy serao adicionados apenas quando o codigo real estiver integrado.

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

Use `make core-analyse` para executar PHPStan com Larastan no SICODE CORE.

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
