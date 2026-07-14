# Laravel Migrations

## Objetivo

Definir como escrever migrations Laravel seguras, reversiveis quando adequado e alinhadas ao PostgreSQL.

## Quando esta skill e obrigatoria

Use antes de criar ou alterar qualquer migration.

## Fontes normativas

- `docs/skills/database/database-design.md`
- `docs/architecture/core-identity-access-physical-model.md`

## Regras obrigatorias

- Nao crie migration para conceito nao aprovado.
- Toda migration deve ter nome claro e escopo pequeno.
- `down()` deve ser reversivel quando tecnicamente seguro; quando nao for, explique.
- Use constraints de banco para invariantes estruturais.
- Considere capabilities PostgreSQL para CHECK, indices parciais, expressions e triggers.
- Revise SQL resultante quando usar constraint avancada.

## Padroes recomendados

- Nomeie constraints quando o nome automatico prejudicar manutencao.
- Agrupe indices relacionados ao mesmo contrato de consulta.
- Prefira migrations incrementais legiveis a mega-migrations opacas.
- Documente em comentario curto somente decisoes nao obvias.

## Padroes proibidos

- `nullable()` por conveniencia.
- `softDeletes()` automatico.
- `cascadeOnDelete()` sem avaliar historico.
- JSON genérico para evitar modelagem.
- Indice sem regra, consulta ou constraint que o justifique.

## Processo de execucao

1. Leia modelo fisico e skills de database.
2. Escreva migration minima.
3. Gere/inspecione SQL quando houver recurso PostgreSQL avancado.
4. Teste migrate/rollback quando seguro.
5. Adicione testes de constraint quando aplicavel.

## Checklist de conclusao

- Migration segue documento fisico.
- Constraints e indices conferidos.
- Rollback seguro ou excecao documentada.
- Nenhum conceito novo foi introduzido silenciosamente.

## Quando interromper e propor ADR

- Migration exige alterar modelo fisico aprovado.
- Invariante nao pode ser representada sem mudar decisao arquitetural.
- Necessidade de tabela de transicao no CORE nao prevista.

