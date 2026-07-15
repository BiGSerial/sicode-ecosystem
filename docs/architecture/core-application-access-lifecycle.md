# CORE Application Access Lifecycle

Data: 2026-07-15

## Conceito

`ApplicationAccess` representa o direito individual de um `User` entrar em uma `Application`, opcionalmente escopado por `ApplicationContext`.

Esse direito pertence ao CORE porque responde a pergunta global:

> este usuario pode entrar nesta aplicacao/contexto neste instante?

`ApplicationAccess` nao representa papel operacional, permissao interna, workflow, alçada, fila, tenant local ou perfil de produto.

## Grant contratual versus acesso individual

`ContractApplicationGrant` e autorizacao institucional por contrato.

`ApplicationAccess` e autorizacao individual.

Quando uma aplicacao ou contexto exige contrato, a entrada final exige as duas evidencias:

1. `ApplicationAccess` efetivo para o usuario, aplicacao e contexto exato;
2. `ContractApplicationGrant` efetivo para a organizacao do usuario, aplicacao e contexto exato.

Um grant contratual nao substitui acesso individual.

Um acesso individual nao substitui contrato ou grant contratual quando contrato e exigido.

## Estados

Estados canonicos:

- `active`: direito individual concedido.
- `suspended`: direito pausado.
- `revoked`: direito encerrado.

Transicoes permitidas:

- `active -> suspended`
- `active -> revoked`
- `suspended -> active`
- `suspended -> revoked`

`revoked` e terminal operacional.

## Vigencia temporal

A efetividade sempre deve ser resolvida com instante explicito `at`.

Um `ApplicationAccess` efetivo precisa atender simultaneamente:

- mesmo `user_id`;
- mesma `application_id`;
- mesmo `context_id`, incluindo `NULL` somente quando a entrada tambem nao tiver contexto;
- `status = active`;
- `starts_at <= at`;
- `ends_at IS NULL OR ends_at >= at`.

`starts_at` e `ends_at` sao inclusivos.

## ApplicationEntry

`EvaluateApplicationEntry` continua sendo read-only e side-effect free.

Ele orquestra a decisao final e delega a regra individual para `ResolveEffectiveApplicationAccess`.

Os reason codes existentes permanecem:

- sem registro equivalente: `APPLICATION_ACCESS_NOT_GRANTED`;
- registro equivalente existe, mas nao e efetivo: `APPLICATION_ACCESS_NOT_EFFECTIVE`.

## Invariantes

Invariantes estruturais:

- `application_accesses.status` aceita apenas `active`, `suspended` ou `revoked`;
- `ends_at` nao pode ser anterior a `starts_at`;
- `revoked` exige `ends_at` preenchido;
- `context_id`, quando presente, precisa pertencer a mesma `application_id`;
- so pode existir um `active` equivalente por `user_id`, `application_id` e `context_id`.

Invariantes transacionais:

- concessao exige aplicacao ativa;
- concessao escopada por contexto exige contexto ativo da mesma aplicacao;
- concessao nao pode criar duplicidade ativa equivalente;
- reativacao nao pode conflitar com outro acesso ativo equivalente;
- revogacao exige motivo e data de revogacao maior ou igual a `starts_at`;
- revogado nao pode voltar para outro estado.

## Auditoria

Mutacoes de `ApplicationAccess` registram `CoreAuditEvent` na mesma transacao:

- `APPLICATION_ACCESS_GRANTED`;
- `APPLICATION_ACCESS_SUSPENDED`;
- `APPLICATION_ACCESS_REACTIVATED`;
- `APPLICATION_ACCESS_REVOKED`.

O audit usa allowlist de detalhes:

- `user_id`;
- `application_id`;
- `context_id`;
- `from_status` e `to_status` quando houver transicao.

`EvaluateApplicationEntry` nao grava auditoria porque e decisao read-only.

## Limites

Esta camada nao cria:

- papel administrativo global;
- bypass implicito;
- permissao operacional;
- API externa;
- UI administrativa;
- integracao Legacy, SICODESK, SICODE 2.0, Azure, OAuth ou OIDC.
