# Database Testing

## Objetivo

Definir testes para constraints, concorrencia e comportamento PostgreSQL.

## Quando esta skill e obrigatoria

Use ao criar/alterar migrations, constraints, indices, transacoes, queries criticas ou regras dependentes de PostgreSQL.

## Fontes normativas

- `docs/skills/database/database-design.md`
- `docs/skills/database/laravel-migrations.md`

## Regras obrigatorias

- Constraints importantes devem ter teste.
- Unique, CHECK, FK e indices parciais devem ser validados quando implementados.
- Comportamento PostgreSQL especifico deve ser testado em PostgreSQL real.
- Concorrencia deve ser testada quando houver risco de corrida.

## Padroes recomendados

- Teste a falha da constraint, nao so o sucesso.
- Use transacoes e factories de modo explicito.
- Valide queries que dependem de indice com cuidado proporcional ao risco.

## Padroes proibidos

- Aceitar SQLite como prova de CHECK/indice parcial PostgreSQL.
- Remover constraint para facilitar teste.
- Testar apenas validacao Laravel quando a garantia e de banco.

## Processo de execucao

1. Liste invariantes de banco.
2. Escreva caso valido e invalido.
3. Execute em PostgreSQL.
4. Documente limitacoes.

## Checklist de conclusao

- Constraints principais cobertas.
- PostgreSQL usado quando necessario.
- Concorrencia considerada.

## Quando interromper e propor ADR

- Invariante nao testavel com infraestrutura atual e alta criticidade.

