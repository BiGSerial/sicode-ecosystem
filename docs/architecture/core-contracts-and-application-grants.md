# CORE Contracts and Application Grants

Este documento registra o comportamento transacional aprovado para gestao de contratos organizacionais e concessoes de aplicacoes no SICODE CORE.

## Responsabilidade

`Contract` representa contrato institucional de uma `Organization` no ecossistema. Contrato nao pertence ao usuario e nao substitui `ApplicationAccess` individual.

`ContractApplicationGrant` representa disponibilidade contratual de uma `Application` ou `ApplicationContext` para um contrato.

O CORE usa contratos e grants para decidir entrada global quando a aplicacao ou contexto declara `requires_contract = true`. Permissoes internas da aplicacao continuam fora do CORE.

## Relacao Organizacao -> Contrato

Uma organizacao pode possuir zero, um ou varios contratos.

Contratos sobrepostos para a mesma organizacao sao permitidos pelo modelo fisico aprovado. Isso preserva historico e suporta transicoes contratuais sem impor principalidade global.

Criacao de contrato exige organizacao `active`. Organizacoes inativas nao produzem contrato efetivo.

## Status e Vigencia

Contratos usam o catalogo:

- `draft`: cadastrado, sem efeito de acesso;
- `active`: pode autorizar grants se temporalmente vigente;
- `suspended`: preservado, mas nao autoriza acesso;
- `ended`: encerrado e terminal operacional.

Contrato efetivo exige:

- organizacao `active`;
- contrato `active`;
- `starts_at <= instante de avaliacao`;
- `ends_at` nulo ou `ends_at >= instante de avaliacao`.

O instante de avaliacao e informado explicitamente aos resolvedores para manter testes e decisoes deterministicas.

## Grant de Aplicacao

Grants usam o catalogo:

- `active`;
- `suspended`;
- `revoked`.

Um grant pode apontar para a aplicacao inteira (`context_id NULL`) ou para um contexto operacional especifico. Quando contexto e informado, ele deve pertencer a mesma aplicacao.

Um grant de contexto ES nao autoriza SP. Um grant sem contexto nao substitui grant contextual quando a entrada esta sendo avaliada para um contexto especifico.

## Separacao de Responsabilidades

`ContractApplicationGrant` responde somente:

```text
Este contrato torna esta aplicacao/contexto contratualmente disponivel para a organizacao?
```

Ele nao representa papel, permissao operacional, workflow, alçada ou autorizacao sobre entidades internas de SICODE, SICODESK ou qualquer aplicacao consumidora.

Mesmo com grant contratual efetivo, o usuario precisa de `ApplicationAccess` individual efetivo.

## Interacao com Application Entry

Quando `requires_contract` e verdadeiro, `EvaluateApplicationEntry` avalia:

1. usuario, aplicacao, contexto e `ApplicationAccess`;
2. vinculo organizacional efetivo unico;
3. existencia de ao menos um contrato efetivo da organizacao;
4. existencia de grant efetivo para aplicacao/contexto em algum contrato efetivo.

Se nao houver contrato efetivo, a decisao e `CONTRACT_NOT_EFFECTIVE`.

Se houver contrato efetivo, mas nenhum grant efetivo para a aplicacao/contexto, a decisao e `CONTRACT_APPLICATION_GRANT_NOT_EFFECTIVE`.

## Auditoria

Eventos obrigatorios desta capability:

- `CONTRACT_CREATED`;
- `CONTRACT_ACTIVATED`;
- `CONTRACT_SUSPENDED`;
- `CONTRACT_REACTIVATED`;
- `CONTRACT_ENDED`;
- `CONTRACT_APPLICATION_GRANT_GRANTED`;
- `CONTRACT_APPLICATION_GRANT_REVOKED`.

Eventos sao gravados na mesma transacao da mutacao critica. `details` usa allowlist de IDs e transicoes de status, sem payload completo de models, documentos, tokens, secrets ou dumps de request.

## Concorrencia e Integridade

Garantias de banco existentes:

- FK de contratos para organizacoes;
- FK de grants para contratos, aplicacoes e contextos;
- CHECK de status;
- CHECK temporal `ends_at >= starts_at`;
- trigger que garante `context_id` pertencendo a mesma `application_id`;
- unique parcial para impedir grant ativo equivalente duplicado por `contract_id + application_id + context_id`;
- CHECK incremental exigindo `contracts.status = ended` somente com `ends_at` informado;
- CHECK incremental exigindo `contract_application_grants.status = revoked` somente com `ends_at` informado.

Garantias transacionais de dominio:

- criacao de contrato bloqueia a organizacao e exige organizacao ativa;
- mudanca de status bloqueia o contrato;
- encerramento bloqueia o contrato e preserva historico;
- concessao de aplicacao bloqueia contrato, aplicacao e contexto;
- revogacao de grant bloqueia o grant;
- actions verificam duplicidade antes de inserir e o indice unico parcial permanece como protecao final contra corrida.

Sobreposicao de contratos para a mesma organizacao permanece permitida por decisao do modelo fisico. Por isso, o resolvedor de contrato singular pode retornar decisao ambigua, mas a avaliacao de grant considera o conjunto de contratos efetivos da organizacao.

## Fora de Escopo

Esta capability nao implementa:

- Hub visual;
- CRUD administrativo;
- API publica;
- OAuth/OIDC;
- tokens entre aplicacoes;
- regras internas de SICODE/SICODESK;
- integracao com Legacy, SP ou ES;
- infraestrutura externa.
