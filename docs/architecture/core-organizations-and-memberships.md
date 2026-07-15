# CORE Organizations and Memberships

Este documento registra o comportamento transacional aprovado para gestao de organizacoes e vinculos organizacionais no SICODE CORE.

## Escopo

Esta capability cobre somente:

- criacao de `organizations`;
- alteracao de status de `organizations`;
- criacao de `organization_memberships`;
- encerramento de `organization_memberships`;
- resolucao do vinculo organizacional efetivo para fluxos que exigem contexto operacional.

Contratos, grants por aplicacao, UI, homologacao de dados e regras de entrada em aplicacoes permanecem fora deste escopo, exceto pelo reuso do resolvedor de vinculo efetivo pela avaliacao de entrada existente.

## Organizacoes

Organizacoes usam o catalogo fechado de status:

- `active`;
- `suspended`;
- `disabled`.

Novas organizacoes nascem como `active`. Mudancas de status exigem motivo explicito e geram evento em `core_audit_events`.

Documentos conhecidos sao normalizados antes da persistencia:

- `document_type` e aparado e convertido para minusculas;
- `document_value` remove caracteres nao alfanumericos e e convertido para maiusculas;
- tipo e valor devem ser informados em conjunto ou ambos omitidos.

## Vinculos Organizacionais

Vinculos usam o catalogo fechado de status:

- `active`;
- `suspended`;
- `ended`.

Um usuario pode existir sem vinculo organizacional. Um usuario pode ter multiplos vinculos ativos em organizacoes diferentes, mas nao pode ter dois vinculos ativos para a mesma organizacao.

Criar vinculo ativo exige que a organizacao esteja `active`. Encerrar vinculo preserva o historico, exige motivo e define `status = ended` com `ended_at` informado.

## Vinculo Efetivo

Um vinculo efetivo exige:

- `organization_memberships.status = active`;
- `started_at <= instante de avaliacao`;
- `ended_at` nulo ou `ended_at >= instante de avaliacao`;
- `organizations.status = active`.

Nenhum vinculo efetivo resulta em decisao `none`. Mais de um vinculo efetivo resulta em decisao `ambiguous`; consumidores nao devem escolher uma organizacao implicitamente.

## Auditoria

Eventos obrigatorios desta capability:

- `ORGANIZATION_CREATED`;
- `ORGANIZATION_SUSPENDED`;
- `ORGANIZATION_REACTIVATED`;
- `ORGANIZATION_DISABLED`;
- `ORGANIZATION_MEMBERSHIP_CREATED`;
- `ORGANIZATION_MEMBERSHIP_ENDED`.

Os detalhes de auditoria sao allowlistados para identificadores e transicoes de status. Payloads completos de modelos, documentos, credenciais, tokens ou dados sensiveis nao devem ser gravados em `details`.
