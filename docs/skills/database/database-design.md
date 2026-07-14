# Database Design

## Objetivo

Definir padroes de modelagem persistente para PostgreSQL no SICODE Ecosystem.

## Quando esta skill e obrigatoria

Use ao criar ou alterar tabelas, colunas, constraints, indices, status, historico, lifecycle ou regras temporais.

## Fontes normativas

- `docs/architecture/core-identity-access-physical-model.md`
- `docs/architecture/core-identity-domain-model.md`
- `docs/architecture/legacy-to-core-transition-map.md`

## Regras obrigatorias

- Para persistencia CORE, leia o modelo fisico antes de qualquer desenho.
- Use PostgreSQL como referencia de comportamento.
- Proteja invariantes estruturais com PK, FK, UNIQUE, CHECK, NOT NULL e indices parciais quando aplicavel.
- UUID e o padrao para entidades centrais CORE.
- Status deve ser especifico por aggregate; nao use enum generico `Status`.
- Soft delete nao e automatico.

## Padroes recomendados

- Use `varchar + CHECK` para estados evolutiveis.
- Modele vigencia temporal com `starts_at`/`ends_at` e status separadamente.
- Use indices derivados de consultas, unicidade ou regra de negocio real.
- Separe invariante de banco de regra transacional.

## Padroes proibidos

- `nullable()` por conveniencia.
- Colunas Legacy em entidades CORE canonicas.
- Misturar `bigint`, UUID e ULID sem justificativa de dominio.
- Criar indice especulativo sem consulta/regra.
- Usar SQLite como prova de comportamento PostgreSQL especifico.

## Processo de execucao

1. Identifique aggregate e lifecycle.
2. Liste invariantes estruturais.
3. Defina constraints e indices.
4. Separe regras transacionais.
5. Verifique estrategia de delecao.
6. Confirme compatibilidade com PostgreSQL.

## Checklist de conclusao

- PK/FK definidas.
- Nulabilidade justificada.
- Constraints nomeadas quando necessario.
- Indices alinhados a regras reais.
- Lifecycle e historico definidos.

## Quando interromper e propor ADR

- Nova estrategia de ID.
- Mudanca de lifecycle canonico.
- Necessidade de tabela nao aprovada em dominio CORE.
- Mudanca de banco ou recurso estrutural de persistencia.

