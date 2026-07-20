# Inventario semantico de `company_id` no SICODE Legacy

Data: 2026-07-20

## Escopo

Este inventario registra a leitura tecnica inicial dos usos de `company_id` no Legacy real apos o commit `89b1eef`.

Busca executada em Controllers, Services, Models, Repositories, Jobs, Commands, Policies, Observers, Listeners, Livewire, Blade e testes. Foram encontrados 765 matches relevantes:

| Area | Matches |
| --- | ---: |
| `app/Http/Livewire` | 553 |
| `resources/views` | 79 |
| `app/Console` | 40 |
| `app/Jobs` | 33 |
| `app/Services` | 23 |
| `app/Models` | 18 |
| `tests` | 15 |
| `app/Http/Controllers` | 1 |
| `app/Repositories`, `app/Policies`, `app/Observers`, `app/Listeners` | 0 |

Padroes buscados: `company_id`, `Auth::user()->company_id`, `auth()->user()->company_id`, `where('company_id'`, `whereCompanyId`, `request('company_id')`, `company_user`, `Employee->Contract`, `employee->contract`, `Contract->company` e variacoes indiretas.

## Classificacao

| Arquivo / simbolo | Modulo | Uso | Origem atual | Tabela ou fluxo | Categoria | Risco | Recomendacao | Prioridade | Testes existentes | Pendencia funcional |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `app/Http/Livewire/Production/Actions/NewProduction::executeCreateNewProduction` | Productions | Gravacao | estado Livewire `companySelected` | `productions.company_id` | A / B | Browser podia manipular empresa quando havia contexto de sessao | Migrado para `CurrentCompanyContext` quando estabelecido; preserva ID local | Alta | `CoreLaunchConsumerTest` | Expandir para demais actions de atribuicao |
| `app/Http/Livewire/Production/Actions/NewProduction::executeTransferProduction` | Productions | Atualizacao | estado Livewire `companySelected` | `productions.company_id` | A / B | Mesma superficie de manipulacao entre empresas | Migrado para `CurrentCompanyContext` quando estabelecido | Alta | `CoreLaunchConsumerTest` | Cobrir fluxo Livewire real em tarefa posterior |
| `app/Http/Livewire/Production/Actions/ToAssign::executeAssign` | Productions | Atualizacao | estado Livewire `companySelected` | `productions.company_id` | A / B | Candidato ao mesmo hardening; ainda fora do slice | Migrar na proxima fatia operacional | Alta | Nao identificado | Pendente |
| `app/Http/Livewire/Reports/Productions::getListsProperty` | Reports / Productions | Leitura | `auth()->user()->employee->contract->company_id` ou filtro `company` | consulta `productions` | D / E / G | Mistura contrato, selecao administrativa e relatorio historico | Nao migrar globalmente; separar modo contratual, admin e contexto CORE antes de alterar | Media | Nao identificado | Pendente |
| `app/Jobs/Reports/ExportProductionJob` | Reports export | Leitura | parametro `company` | export de `productions` | E / G | Parametro empresarial precisa autorizacao no chamador | Manter ate revisar autorizacao dos filtros | Media | Nao identificado | Pendente |
| `app/Jobs/ExportProductionListJob` | Reports export | Leitura | `user->employee->contract->company_id` ou parametro `company` | export de `productions` | D / E / G | Regra contratual e selecao administrativa coexistem | Preservar contrato; revisar parametro | Media | Nao identificado | Pendente |
| `app/Http/Livewire/Dispatchs/*/Stack` | Dispatch | Leitura e gravacao | `Auth()->User()->Employee->Contract->company_id`, filtros `company_fs`, estado `company_s` | `productions.company_id` e usuarios | A / D / E | Alto volume e regras por servico; risco de regressao se migrado em massa | Inventariar por service antes de migrar | Alta | Nao identificado | Pendente |
| `app/Http/Livewire/Partner/Forms/Workreports` | Partner / informes | Gravacao | `Auth()->User()->Employee->Contract->company->id` ou selecao controlada | `work_reports.company_id` | D / E | Contrato operacional real, nao equivale automaticamente ao Launch CORE | Preservar ate modelagem especifica de Partner | Media | Nao identificado | Pendente |
| `app/Http/Livewire/Dispatchs/Common/ReturnInMass` | Dispatch | Leitura e gravacao | `companySelected`, `user->company_id` | retorno em massa | C / E | `users.company_id` usado como origem operacional local | Revisar separadamente; nao usar como fallback CORE | Media | Nao identificado | Pendente |
| `app/Models/Production` | Dominio operacional | Propriedade persistida | coluna do registro | `productions.company_id` | B | Nao deve ser substituida por UUID CORE | Preservar FK local para `companies.id` | Alta | `CoreLaunchConsumerTest` | Nenhuma nesta tarefa |
| `app/Models/User` | Identidade Legacy local | Propriedade do usuario | `users.company_id` e pivot `company_user` | login Legacy, escopo local | C / D / F | Pode divergir do Launch CORE | Usar apenas como compatibilidade Legacy explicita | Alta | `CoreLaunchConsumerTest` | Reconciliacao administrativa futura |
| `app/Models/Contract` e `app/Models/Employee` | Contratos Legacy | Relacao operacional | `contracts.company_id`, `employees.contract_id` | contrato/funcionario | D | Nao equivale ao contrato institucional CORE | Preservar regra real do Legacy | Alta | `CoreLaunchConsumerTest` | Corrigir typo historico `company_id ` em tarefa propria se necessario |
| `company_user` em `User::Companies` e `Company::Users` | Escopo Legacy local | Vinculo N:N | pivot Legacy | visibilidade/operacao | D | Permite multivinculo e possiveis duplicatas tecnicas | Documentar como multivinculo Legacy; nao converter automaticamente em membership CORE | Media | Nao identificado | Pendente |
| Blades com `wire:model="company_id"` ou `form.company_id` | UI Livewire | Entrada | navegador | filtros ou forms | E | Pode ser legitimo em administracao, perigoso em contexto operacional | Autorizar no componente antes de persistir | Media | Parcial no slice | Pendente |
| Commands de sync/import (`SyncNoteInformFlows`, `MigrateFiveNotesFromBaseD5`) | Integracao operacional | Leitura e gravacao | dados operacionais importados | notes, work reports, SQL Server | B / G | Fluxos batch podem depender de dados historicos | Nao aplicar `CurrentCompanyContext`; revisar por job | Baixa | Nao identificado | Pendente |

## Decisao do slice

O primeiro slice migrado foi `Production\Actions\NewProduction`, porque:

- grava `productions.company_id`;
- a origem anterior era estado Livewire manipulavel;
- a regra e operacional e pequena;
- a alteracao nao exige trocar FKs nem alterar historico;
- quando `CurrentCompanyContext` esta estabelecido, a empresa deve vir da sessao.

Comportamento atual:

- entrada CORE: `core_organization_id -> core_organization_links -> companies.id -> CurrentCompanyContext -> productions.company_id`;
- login Legacy: `users.company_id` pode materializar contexto de origem `legacy`;
- sem contexto estabelecido: o fluxo Legacy preserva comportamento existente;
- empresa divergente enviada pelo browser e rejeitada quando ha contexto estabelecido;
- UUID CORE nao e aceito como `productions.company_id`.

## Segundo slice: Productions operacional

O segundo slice de hardening foi limitado ao modulo operacional de Productions. A decisao foi nao migrar relatorios, exports, wall, dashboards, jobs batch, consultas historicas nem telas administrativas multempresa, porque esses fluxos nao possuem necessariamente uma unica empresa efetiva de sessao.

Arquivos operacionais migrados para a abstracao local `App\Services\Production\ProductionCompanyContext`:

| Arquivo / simbolo | Uso protegido | Politica aplicada |
| --- | --- | --- |
| `Production\Actions\NewProduction` | criar nova producao e transferir producao | `company_id` gravado vem do contexto atual quando estabelecido |
| `Production\Actions\ToAssign` | atribuir e desatribuir usuario | selecao de empresa do browser nao pode trocar a empresa do contexto |
| `Production\Actions\ToReturn` | retorno operacional | producao precisa pertencer a empresa atual |
| `Production\Actions\ToRemove` | remocao operacional | producao precisa pertencer a empresa atual |
| `Production\Actions\ToRemoveTransfer` | remocao de transferencia | producao precisa pertencer a empresa atual |
| `Production\Actions\SetPriority` | prioridade | producao precisa pertencer a empresa atual |
| `Production\Actions\Delete` | exclusao | producao precisa pertencer a empresa atual |
| `Production\Actions\Reattribute` | reatribuicao | producao precisa pertencer a empresa atual |
| `Production\Actions\Geralreattribute` | reatribuicao geral | producao precisa pertencer a empresa atual |
| `Production\Return\ReturnWork` | retorno de informe | producao precisa pertencer a empresa atual |
| `Production\Return\ReturnRamalWork` | retorno de ramal | producao precisa pertencer a empresa atual |
| `Production\Return\RejectInformPartial` | rejeicao parcial | producao precisa pertencer a empresa atual |
| `ServicesController::production` | abertura direta da producao por rota | producao precisa pertencer a empresa atual |

Rotas com middleware `current.company` neste slice:

- `services.production`;
- `construction.production`.

Rotas mantidas sem `current.company` por desenho:

- `reports.productions`;
- exports;
- wall e dashboards;
- telas administrativas multempresa;
- login, logout, callback CORE e reconciliacao.

`ProductionCompanyContext` nao interpreta payload CORE. Ele consome `CurrentCompanyContext`, valida a empresa local autorizada e delega a checagem de operacao local para `LegacyCompanyAccessResolver`.

`LegacyCompanyAccessResolver` responde apenas se o usuario Legacy pode operar para uma empresa local por algum vinculo Legacy conhecido:

- `users.company_id`;
- pivot `company_user`;
- `employees -> contracts -> company_id`.

Esses vinculos nao inferem organizacao CORE. Eles somente validam acesso local depois que o contexto empresarial efetivo ja foi materializado.

Politica operacional aplicada:

- com `CurrentCompanyContext` estabelecido, `productions.company_id` deve continuar recebendo somente `companies.id` local;
- UUID de organizacao CORE nunca deve ser persistido em `productions.company_id`;
- parametro de empresa vindo do browser e diferente do contexto atual e rejeitado;
- producao de outra empresa e rejeitada antes da mutacao;
- sem contexto empresarial estabelecido, o comportamento Legacy existente e preservado para compatibilidade.

Testes especificos deste slice estao em `tests/Feature/ProductionCompanyContextTest.php`.

## Auditoria da suite Legacy

| Arquivo | Trait ou operacao | Tabelas afetadas | Risco | Correcao recomendada |
| --- | --- | --- | --- | --- |
| `tests/Feature/CoreLaunchConsumerTest.php` | antes usava `RefreshDatabase` | schema inteiro | Destruiria o dump restaurado | Corrigido: usa `UsesRestoredLegacyDatabase` com `DatabaseTransactions` e guarda de ambiente |
| `tests/Feature/CancellationRequestsTest.php` | `RefreshDatabase` | schema inteiro; cria notes, orders, users, cancellation tables, evidence files | Incompativel com `sicode_legacy` restaurado | Nao executar contra dump ate migrar para transacoes/fixtures isoladas |
| `tests/Feature/UserFakerCreate.php` | importa `RefreshDatabase`, cria 40 usuarios, sem trait ativo no corpo | `users` | Se o trait for ativado, destroi dump; sem trait, acumula usuarios | Reescrever ou remover do gate do dump |
| `tests/Pest.php` e `tests/Feature/ExampleTest.php` | referencias comentadas a `RefreshDatabase` | nenhuma | Baixo, apenas evidencia de padrao antigo | Manter observado |
| `tests/Concerns/UsesRestoredLegacyDatabase.php` | `DatabaseTransactions` | somente registros criados pelo teste | Baixo; rollback ao final do teste | Padrao recomendado para testes CORE sobre dump |

Nao foram encontrados em `tests/` usos ativos de `DatabaseMigrations`, `DatabaseTruncation`, `migrate:fresh`, `db:wipe`, `truncate`, `delete()` sem filtro, `Model::query()->delete()`, `DROP TABLE` ou `DELETE FROM`.

## Validacao objetiva do dump

Contagens antes dos testes:

| Tabela | Registros |
| --- | ---: |
| `companies` | 24 |
| `users` | 333 |
| `core_identity_links` | 0 |
| `core_organization_links` | 0 |
| `services` | 13 |
| `notes` | 695328 |
| `productions` | 399439 |
| `contracts` | 27 |
| `employees` | 333 |

Contagens depois de duas execucoes da suite focada: identicas.

Estrategia de isolamento: `DatabaseTransactions` por teste, fixtures com prefixo `TEST_CORE_LAUNCH_`, UUIDs randomicos e flag explicita `LEGACY_TEST_DATABASE_ALLOWED=true`.
