# ADR-003: Runtime multiunidade do SICODE Legacy

Data: 2026-07-20

Status: Aceita

## Contexto

O SICODE Legacy precisa operar ES e SP a partir de uma unica base de codigo, preservando dados historicos e foreign keys locais. As diferencas entre ES e SP nao podem gerar forks de schema nem remapear `company_id` para identificadores CORE.

## Decisao

Cada instancia Legacy declara uma unidade obrigatoria (`es` ou `sp`) em configuracao local cacheavel. O runtime expoe `CurrentUnit`, capacidades por allowlist e bindings unit-aware centralizados no container.

O Launch CORE e validado contra o contexto esperado da unidade antes de resolver identidade, organizacao ou sessao. O identificador de organizacao CORE continua entrando apenas pelo exchange backend-to-backend e e resolvido por `core_organization_links` para `companies.id` local.

ES e SP usam o mesmo schema-base. Diferencas devem seguir a hierarquia: configuracao, capacidade, Policy, Strategy, Workflow e, por ultimo, modulo separado.

## Consequencias

`productions.company_id`, `notes.company_id`, `work_reports.company_id` e tabelas operacionais equivalentes continuam apontando somente para `companies.id`.

O modo de identidade e explicito (`reconciliation` ou `provisioning`) e nao e inferido automaticamente pela unidade.

Provisionamento SP fica fora do Launch/login e sera tratado por contratos proprios antes de qualquer sincronizacao operacional.
