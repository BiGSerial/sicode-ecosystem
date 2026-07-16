# Bloqueio do consumidor Legacy do CORE Application Launch Protocol

Data: 2026-07-16

## Contexto

Foi solicitada a retomada do desenvolvimento do primeiro consumidor real do CORE Application Launch Protocol para o SICODE Legacy Laravel 10, partindo do commit atual do repositorio.

O gate inicial confirmou:

- `HEAD`: `ceb75d5` (`docs(workflow): add project management issue closure rules`);
- worktree limpo antes da analise;
- `apps/sicode-legacy` contem somente `README.md`;
- nao ha `composer.json`, `artisan`, `phpunit.xml`, Models, migrations, tabela local de usuarios, guard, rotas, controllers, Livewire 2 ou codigo Laravel 10 real no Legacy deste workspace.

## Evidencia documental

`docs/development/local-execution.md` declara:

```text
apps/sicode-legacy e reservado para importacao futura do codigo real. Nenhuma aplicacao Laravel 10 vazia foi gerada para simular o Legacy.
```

`apps/sicode-legacy/README.md` declara:

```text
Nao gerar uma aplicacao Laravel 10 vazia para simular o Legacy.
Nao inventar dependencias, migrations, Models ou modulos.
Codigo Legacy ainda nao esta presente neste workspace. A importacao sera uma tarefa posterior.
```

## Decisao tomada

A implementacao do consumidor Legacy foi interrompida antes de qualquer codigo de aplicacao porque a premissa operacional da tarefa nao existe no repositorio atual: nao ha aplicacao Legacy Laravel 10 real onde instalar o callback, a camada `CoreIntegration`, a migration `core_identity_links`, o resolver de usuario local, o estabelecimento de sessao Laravel 10, nem os testes Livewire 2.

Criar uma aplicacao Laravel 10 vazia ou inventar Models/tabelas de usuario apenas para satisfazer o slice violaria a documentacao normativa atual do projeto.

## Contrato CORE verificado

O protocolo CORE ja exposto para consumidores esta implementado em:

- `apps/sicode-core/app/ApplicationLaunch`;
- `apps/sicode-core/app/Http/Controllers/ApplicationLaunchController.php`;
- `apps/sicode-core/app/Http/Controllers/ApplicationLaunchExchangeController.php`;
- `apps/sicode-core/routes/api.php`;
- `apps/sicode-core/config/core_launch.php`;
- `apps/sicode-core/app/Models/ApplicationLaunch.php`;
- `apps/sicode-core/app/Models/Application.php`;
- `apps/sicode-core/app/Models/ApplicationClient.php`;
- `apps/sicode-core/app/Models/ApplicationContext.php`;
- `apps/sicode-core/tests/Feature/ApplicationLaunchProtocolTest.php`.

Contrato observado para troca backend-to-backend:

- `POST /api/core/launch/exchange`;
- request: `client_identifier`, `client_secret`, `code`, `state`;
- resposta: `iss`, `core_subject`, `application`, `context`, `launch_id`, `issued_at`, `expires_at`, `state`;
- falha de autenticacao do consumidor: `401` com mensagem neutra;
- falha de artefato, replay, expiracao, cliente ou state divergente: `422` com mensagem neutra.

## Gaps impeditivos no Legacy

Nao foi possivel inventariar no codigo real:

- guard Laravel 10 efetivo;
- provider Eloquent e Model local de usuario;
- tabela local `users`;
- regra formal de usuario ativo, inativo, bloqueado ou soft-deleted;
- login local existente;
- logout local existente;
- rotas autenticadas;
- componentes Livewire 2;
- estrategia real de banco do Legacy;
- estrategia real de testes do Legacy.

Sem esses elementos, qualquer implementacao de `core_identity_links`, resolucao `core_subject -> usuario local`, conflito de sessao, logout local, ou prova Livewire 2 seria especulativa.

## Resultado dos gates

Executados em `apps/sicode-core`:

- `composer validate --strict`: passou;
- `vendor/bin/pint --test`: passou;
- `vendor/bin/phpstan analyse --memory-limit=512M --debug`: passou;
- `vendor/bin/phpstan analyse app/ApplicationLaunch --memory-limit=512M --debug`: passou.

Tentado:

- `php artisan test tests/Feature/ApplicationLaunchProtocolTest.php --env=testing`: falhou por infraestrutura local, porque `sicode-postgres` nao resolve sem a stack Docker oficial ativa. Os 10 testes do arquivo falharam antes de assertions por `SQLSTATE[08006] [7] could not translate host name "sicode-postgres" to address`.

## Proximo passo necessario

Antes de implementar o consumidor real, e necessario importar ou disponibilizar o codigo Legacy Laravel 10 real em `apps/sicode-legacy`, incluindo sua autenticacao local, tabela/model de usuario, rotas, logout, Livewire 2 e infraestrutura de testes.

Depois da importacao, a tarefa deve ser retomada sem alterar o contrato CORE, implementando a camada anticorrupcao local do Legacy contra o contrato HTTP ja existente.
