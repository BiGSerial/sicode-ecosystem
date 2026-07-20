# Hardening de contexto empresarial em Productions

Data: 2026-07-20

Base tecnica: SICODE Legacy real em `apps/sicode-legacy`, Laravel 10, Livewire 2, MariaDB 11, dump `sicode_prod-20260601-0000.sql.gz` restaurado em `sicode_legacy`.

## Objetivo

Endurecer o modulo operacional de Productions para consumir a empresa local autorizada por uma abstracao Legacy, sem substituir `productions.company_id` por UUID CORE, subject CORE, email, dominio ou parametro enviado pelo navegador.

O fluxo canonico permanece:

```text
CORE organization
-> core_organization_links
-> companies.id local
-> CurrentCompanyContext
-> ProductionCompanyContext
-> productions.company_id
```

## Fora de escopo

Nao foram migrados neste slice:

- relatorios historicos de producao;
- exports;
- wall e dashboards;
- jobs batch;
- consultas pessoais por usuario;
- telas administrativas multempresa;
- regras de Dispatch, Partner, Payment, Engineers e Publication.

Esses fluxos misturam filtros administrativos, historico, contrato operacional e visao multempresa. Migrar em massa poderia transformar um relatorio autorizado em tela de sessao unica sem validacao funcional.

## Classificacao operacional

| Categoria | Significado | Decisao neste slice |
| --- | --- | --- |
| A | fluxo operacional com mutacao de `productions` | migrado para `ProductionCompanyContext` |
| B | persistencia em `productions.company_id` | preservada como `companies.id` local |
| C | `users.company_id` como empresa principal Legacy | aceito apenas como vinculo local, nao como fallback CORE |
| D | vinculo funcional Legacy (`company_user`, `employees -> contracts`) | usado somente para validar operacao local |
| E | filtro administrativo escolhido na UI | mantido fora do contexto obrigatorio |
| F | fluxo multempresa ou historico | mantido sem `current.company` |
| G | compatibilidade Legacy sem contexto estabelecido | comportamento existente preservado |

## Abstracoes implementadas

`CurrentCompanyContext` continua sendo a abstracao de sessao empresarial materializada apos login Legacy ou launch CORE.

`ProductionCompanyContext` e a abstracao especifica de Productions. Responsabilidades:

- retornar a empresa efetiva local para criacao/atualizacao de producoes;
- rejeitar `company_id` enviado pelo browser quando divergir do contexto atual;
- rejeitar uso de uma producao que pertenca a outra empresa;
- aplicar filtro de empresa em queries de Productions quando houver contexto estabelecido;
- preservar comportamento Legacy quando nao houver contexto empresarial.

`LegacyCompanyAccessResolver` valida se o usuario local pode operar para uma empresa Legacy por:

- `users.company_id`;
- pivot `company_user`;
- vinculo `employees -> contracts -> company_id`.

Essa validacao e deliberadamente posterior a resolucao da organizacao CORE. Ela nao infere organizacao, nao cria `core_organization_links` e nao substitui a politica `OrganizationLinkRequired`.

## Superficies migradas

| Superficie | Protecao |
| --- | --- |
| `Production\Actions\NewProduction` | criacao e transferencia gravam a empresa efetiva local |
| `Production\Actions\ToAssign` | atribuicao/desatribuicao valida producao e empresa selecionada |
| `Production\Actions\ToReturn` | retorno valida empresa da producao |
| `Production\Actions\ToRemove` | remocao valida empresa da producao |
| `Production\Actions\ToRemoveTransfer` | remocao de transferencia valida empresa da producao |
| `Production\Actions\SetPriority` | prioridade valida empresa da producao |
| `Production\Actions\Delete` | exclusao valida empresa da producao |
| `Production\Actions\Reattribute` | reatribuicao valida empresa da producao |
| `Production\Actions\Geralreattribute` | reatribuicao geral valida empresa da producao |
| `Production\Return\ReturnWork` | retorno de informe valida empresa da producao |
| `Production\Return\ReturnRamalWork` | retorno de ramal valida empresa da producao |
| `Production\Return\RejectInformPartial` | rejeicao parcial valida empresa da producao |
| `ServicesController::production` | abertura direta valida empresa antes do redirect |

Rotas protegidas com `current.company`:

- `services.production`;
- `construction.production`.

`reports.productions` permanece sem `current.company` porque e rota administrativa/relatorio, nao um fluxo operacional de empresa unica.

## Politica de divergencia

No launch CORE, a politica aprovada continua sendo `CompanyDivergenceRejected`: se `users.company_id` divergir da empresa resolvida via `core_organization_links`, o launch e rejeitado, sem alterar `users.company_id` e sem autenticar silenciosamente com outra empresa.

Dentro de Productions, uma divergencia entre empresa do contexto e empresa enviada pelo browser e rejeitada. Uma divergencia entre empresa do contexto e `productions.company_id` tambem e rejeitada antes da mutacao.

Politicas futuras possiveis exigem decisao administrativa/ADR:

- permitir atuacao contextual sem mudar a empresa principal do usuario;
- exigir reconciliacao administrativa;
- separar papel de empresa principal, empresas operacionais e contrato funcional.

## Confirmacoes pendentes

Pontos que exigem validacao funcional com mantenedores do Legacy antes de novas migracoes:

- se `company_user` e vinculo operacional ativo, historico ou apenas visibilidade de UI;
- se `employees -> contracts` define empresa operacional primaria por servico ou somente contrato trabalhista;
- quais relatorios devem respeitar contexto de sessao e quais devem permanecer multempresa;
- se actions legadas incompletas/com codigo comentado em `Production\Actions\Attribute` devem ser mantidas, removidas ou migradas em tarefa propria;
- se Dispatch e Partner compartilham a mesma politica de contexto empresarial de Productions.

## Testes

Suite focada:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Unit/LegacyDumpDatabaseGuardTest.php tests/Feature/CoreLaunchConsumerTest.php tests/Feature/ProductionCompanyContextTest.php --env=testing
```

Cobertura adicionada:

- contexto CORE filtra listagem de Productions para a empresa atual;
- rota de producao rejeita producao de outra empresa;
- rota de producao aceita producao da mesma empresa;
- parametro de empresa do browser nao troca a empresa do contexto em atribuicao;
- UUID CORE nunca e persistido em `productions.company_id`;
- producao de outra empresa nao pode ser editada;
- status/prioridade de producao de outra empresa nao e alterado;
- producao mantem sua empresa persistida quando o contexto atual diverge;
- resolver Legacy aceita empresa primaria, pivot `company_user` e contrato funcional;
- rota administrativa de relatorio de producoes permanece sem `current.company`.

Base preservada em execucoes repetidas com `DatabaseTransactions`.
