# Hardening de contexto empresarial em Informe de Obra

Data: 2026-07-20

Base tecnica: SICODE Legacy real em `apps/sicode-legacy`, Laravel 10, Livewire 2, MariaDB 11, dump `sicode_prod-20260601-0000.sql.gz` restaurado em `sicode_legacy`.

## Dominio real

O modulo chamado na UI de "Informe de Obra" e implementado principalmente como `WorkReport`:

- tabela principal: `work_reports`;
- Model principal: `App\Models\WorkReport`;
- criacao operacional: `App\Http\Livewire\Partner\Forms\Workreports`;
- reenvio/retrabalho operacional: `App\Http\Livewire\Partner\Forms\Reworkreports`;
- lista de rejeitados/reenvio: `App\Http\Livewire\Partner\WorkedRejectedList`;
- acao legada de retorno/reenvio: `App\Http\Livewire\Partner\Actions\WorkedReturnForm`;
- consolidacao derivada: `note_inform_flows`;
- devolucoes: `return_works`;
- anexos: relacao polimorfica `files`;
- equipamentos e medidores: `equipment` e `meeters`;
- ordens associadas: `order_work_report`.

O vocabulario Legacy alterna entre `work report`, `work form`, `informe final` e `informe de obra`. Neste documento, "Informe de Obra" significa `work_reports`.

## Tabelas envolvidas

| Tabela | Papel no modulo | Empresa |
| --- | --- | --- |
| `work_reports` | registro operacional do informe final de obra | `company_id` local para `companies.id` |
| `note_inform_flows` | consolidacao derivada para ciclo parcial/final, dashboards e sincronizacao | `company_id` copiado/derivado da origem operacional |
| `return_works` | historico de devolucoes/rejeicoes do informe | sem `company_id`; herda via `work_report_id` |
| `equipment` | equipamentos declarados no informe | sem `company_id`; herda via `work_report_id` |
| `meeters` | medidores declarados no informe | sem `company_id`; herda via `work_report_id` |
| `order_work_report` | pivot entre ordens e informe | sem `company_id`; herda via `work_report_id` |
| `notes` | nota/OV informada | sem `company_id` no schema real |
| `orders` | ordens associadas a nota e informe | sem `company_id` no schema real |
| `productions` | fluxo operacional anterior/posterior por nota e servico | `company_id` local proprio, ja endurecido no slice Productions |
| `contracts` | vinculo funcional do usuario/empreiteira | `company_id` local para `companies.id` |
| `employees` | vincula usuario Legacy a contrato e service | empresa derivada por `contract_id` |
| `companies` | cadastro local Legacy | alvo das FKs locais |
| `users` | usuario Legacy autenticado | `company_id` e compatibilidade cadastral, nao fonte CORE |

## Politica empresarial encontrada

1. A empresa proprietaria de um Informe de Obra e `work_reports.company_id`.
2. `work_reports.company_id` aponta exclusivamente para `companies.id` local.
3. Para criacao comum do parceiro, o codigo historico usa a empresa do contrato do usuario: `Auth()->User()->Employee->Contract->company_id`.
4. Para selecao administrativa futura (`canSelectCompany = true`), a origem e `form.company_id`, portanto precisa validacao server-side contra o contexto.
5. A empresa executora/empreiteira e a empresa gravada no informe. A evidencia do codigo nao prova uma empresa contratante separada dentro de `work_reports`.
6. `notes` e `orders` nao possuem `company_id`; elas nao podem ser usadas isoladamente como autoridade empresarial do informe.
7. O usuario pode atuar por mais de uma empresa em fluxos Legacy por `company_user` e `employees -> contracts`, mas o slice operacional com `CurrentCompanyContext` restringe a operacao a empresa efetiva da sessao.
8. No launch CORE, a empresa da sessao vem de `core_organization_links -> companies.id -> CurrentCompanyContext`; o modulo nao interpreta `core_organization_id`.
9. Sem contexto estabelecido, a criacao comum preserva compatibilidade Legacy pela empresa contratual (`employees -> contracts`), nao por UUID CORE.
10. A ausencia de contrato funcional para criacao comum falha de forma controlada; `users.company_id` nao substitui a regra contratual do Informe.
11. Relatorios, exports e admin continuam multempresa/administrativos neste slice.
12. Acoes ADS, parciais, viabilidades, baixa, custos e reclaims ficam fora desta fatia.

## Papel de `note_inform_flows.company_id`

`note_inform_flows` e uma tabela consolidada/derivada criada por comandos de sincronizacao, especialmente `SyncNoteInformFlows`. Para ciclos finais, ela referencia `work_report_id`; para ciclos parciais, referencia `partial_id`.

`note_inform_flows.company_id` nao e a fonte operacional primaria para criacao ou autorizacao do Informe de Obra. O papel observado e indexar/relatar o ciclo consolidado com a empresa derivada da origem operacional. Portanto:

- nao deve receber UUID CORE;
- nao deve ser usado para inferir organizacao CORE;
- nao deve substituir `work_reports.company_id`;
- comandos batch continuam fora de `CurrentCompanyContext`;
- qualquer divergencia entre `work_reports.company_id` e `note_inform_flows.company_id` deve ser tratada como problema de sincronizacao/consistencia derivada, nao como regra de sessao.

## Classificacao semantica

| Arquivo / simbolo | Fluxo | Operacao | Origem de empresa | Tabela afetada | Categoria | Risco | Decisao |
| --- | --- | --- | --- | --- | --- | --- | --- |
| `Partner\Forms\Workreports::send_informe` | criacao/envio | grava | contrato do usuario ou selecao controlada | `work_reports.company_id` | A / B / D / F / G | browser podia trocar `form.company_id` quando selecao habilitada | migrado para `WorkReportCompanyContext` |
| `Partner\Forms\Workreports::submit` | confirmacao antes do envio | valida | contrato ou `form.company_id` | nenhum | A / F / G | confirmacao podia ocorrer antes de validar contexto | valida empresa antes de abrir confirmacao |
| `Partner\Forms\Workreports::loadCompanies` | selecao administrativa | leitura | lista de `companies` | `companies` | G | lista completa em contexto de empresa unica | filtrada para contexto quando estabelecido |
| `Partner\Forms\Reworkreports::mount` | reenvio de rejeitado | leitura | `work_reports.company_id` persistido | `work_reports` | A / D | token de sessao poderia apontar para informe de outra empresa | query filtrada por contexto e assert server-side |
| `Partner\Forms\Reworkreports::send_informe` | reenvio de rejeitado | grava | propriedade persistida | `work_reports` | A / D | mutacao cross-company | revalidacao em cada envio |
| `Partner\WorkedRejectedList::getListsProperty` | lista operacional de rejeitados | leitura | `work_reports.company_id` | `work_reports` | A / D | lista podia depender somente de `users.company_id`/pivot | query filtrada por contexto quando estabelecido |
| `Partner\WorkedRejectedList::openRejectDetails` | detalhe operacional | leitura | `work_reports.company_id` | `work_reports`, `return_works` | A / D | detalhe cross-company | query filtrada e assert |
| `Partner\WorkedRejectedList::reinform` | emissao de token de reenvio | leitura/controle | `work_reports.company_id` | sessao local | A / D | token poderia ser criado para informe de outra empresa | query filtrada e assert |
| `Partner\Actions\WorkedReturnForm` | acao legada de reenvio | grava | `work_reports.company_id` persistido | `work_reports` | A / D | mutacao cross-company | assert antes de modal e save |
| `Reports\Workreports` | relatorio de informes | leitura/export | filtros de relatorio | `work_reports` | H / G | restringir automaticamente quebraria relatorio | mantido fora do middleware |
| `Admin\Control\WorkReportList` | admin controle | leitura/exclusao | escopo `can:superadm` | `work_reports` e dependencias | G / H | admin precisa operar multempresa | mantido fora do middleware |
| `Admin\Control\WorkReportEdit` | admin correcao | leitura/grava | campos editaveis de admin | `work_reports`, `adsforms` | G / H | alteracao pode ser reconciliacao operacional | mantido fora deste slice |
| `SyncNoteInformFlows` | consolidacao batch | upsert | dados operacionais derivados | `note_inform_flows` | D / H | contexto de sessao nao existe em batch | mantido fora |

## Abstracao criada

`App\Services\Partner\WorkReportCompanyContext` encapsula somente regras empresariais do Informe de Obra.

Responsabilidades:

- resolver empresa local para envio de informe;
- preservar a empresa contratual como regra Legacy de criacao comum;
- rejeitar divergencia entre empresa do contrato/selecao e `CurrentCompanyContext`;
- rejeitar mutacao de `WorkReport` pertencente a outra empresa;
- aplicar escopo de empresa em queries operacionais;
- filtrar lista de empresas selecionaveis quando houver contexto.

Nao faz:

- consulta a `core_organization_links`;
- interpretacao de `core_organization_id`;
- alteracao de `users.company_id`;
- substituicao de contrato Legacy;
- acesso cross-database;
- regra para ADS, viabilidades, parciais ou relatorios globais.

## Rotas com `current.company`

| Rota | Motivo |
| --- | --- |
| `partner.report.workreport` | entrada operacional de criacao/envio de Informe de Obra |
| `partner.report.rejectedWorked` | fila operacional de informes rejeitados para reenvio |
| `partner.report.reinformWorkreport` | tela operacional de reenvio por token local |

## Rotas fora do middleware

| Rota / area | Motivo |
| --- | --- |
| `partner.report.workedlist` | historico/listagem de parceiro ainda usa escopo Legacy e nao foi migrado neste slice |
| `partner.declared.equipment` | consulta derivada de equipamentos; leitura historica fora do vertical slice |
| `reports.workreport` | relatorio/export multempresa |
| `reports.rejecetedWorkreport` | relatorio historico de rejeitados |
| `reports.return_work_reports` | relatorio gerencial de devolucoes |
| `admin.control.workreports` | controle administrativo `can:superadm` |
| `system` / comandos | batch sem sessao empresarial |

## Vertical slice migrado

Slice implementado:

```text
partner.report.workreport
-> Partner\Forms\Workreports::submit
-> Partner\Forms\Workreports::send_informe
-> work_reports.company_id
```

Tambem foram protegidos os pontos diretamente ligados ao reenvio/rejeitados:

```text
partner.report.rejectedWorked
-> Partner\WorkedRejectedList
-> token local de reenvio
-> Partner\Forms\Reworkreports::send_informe
```

## Fluxos nao alterados

Nao foram alterados:

- ADS e ADS tacita;
- Informe parcial;
- Viabilities;
- Costs;
- Reclaims;
- baixa de fiscalizacao/medicao;
- relatorios e exports;
- admin control de WorkReports;
- comandos de sincronizacao SQL/log;
- dashboards de Engineers/Btzero/Wall.

Motivo: esses fluxos combinam administracao, consolidacao, historico, derivados ou outros dominios. Exigem inventario proprio antes de endurecimento.

## Testes

Suite focada:

```bash
docker compose exec -T -e APP_ENV=testing -e LEGACY_TEST_DATABASE_ALLOWED=true sicode-legacy php artisan test tests/Feature/WorkReportCompanyContextTest.php --env=testing
```

Cobertura:

- criacao usa `companies.id` local da sessao;
- UUID CORE nao e persistido em `work_reports.company_id`;
- `form.company_id` manipulado pelo browser e rejeitado;
- queries operacionais sao filtradas pelo contexto;
- lista Livewire de rejeitados respeita contexto;
- reenvio cross-company e rejeitado;
- status de informe de outra empresa nao e alterado;
- propriedade persistida nao e reinterpretada;
- compatibilidade Legacy sem contexto preserva empresa contratual;
- entrada CORE nao usa `users.company_id` como fallback sem contrato;
- rota operacional exige `current.company`;
- rotas administrativas/relatorios permanecem fora do middleware.

## Limitacoes e pendencias

- A evidencia nao prova se `canSelectCompany` e usado em producao; a regra foi protegida para o caso de ser habilitada.
- O unico usuario `onlyparner` sem `users.company_id` no dump passara a falhar nas rotas operacionais com `current.company` ate reconciliacao cadastral ou politica explicita.
- `company_user` continua ambigua entre visibilidade e operacao; nao foi promovida a fonte de criacao do informe.
- `employees -> contracts` continua sendo regra funcional Legacy do modulo, mas nao equivale a contrato institucional CORE.
- Admin `WorkReportEdit` permite mudar `workReport.company_id` por papel `superadm`; mantido fora por ser reconciliacao administrativa.
- `note_inform_flows` deve ser validada em tarefa propria de consistencia derivada.
