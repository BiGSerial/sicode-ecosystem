# SICODE CORE Hub

Data: 2026-07-15

## Responsabilidade

O Hub CORE e a primeira experiencia autenticada do usuario no SICODE CORE.

Ele exibe aplicacoes permitidas.

Ele nao decide autorizacao.

`ApplicationEntry` decide autorizacao.

## Fluxo funcional atual

Fluxo entregue:

```text
login local -> sessao CORE -> Hub -> ApplicationEntry -> aplicacoes permitidas
```

O login local continua sendo responsabilidade da fundacao `StartLocalSession`, que usa `AuthenticateLocalUser` e grava auditoria de autenticacao.

O logout continua usando `EndLocalSession`, invalidando a sessao, regenerando o CSRF token e preservando auditoria de encerramento.

## Resolvedor de aplicacoes do usuario

`ResolveUserHubApplications` monta a lista apresentada no Hub.

Responsabilidades:

1. receber o `User` autenticado;
2. receber explicitamente o instante `at`;
3. carregar aplicacoes ativas do catalogo CORE;
4. carregar contextos cadastrados dessas aplicacoes;
5. chamar `EvaluateApplicationEntry` para cada aplicacao sem contexto ou para cada contexto cadastrado;
6. retornar somente entradas com decisao `ALLOWED`.

O resolvedor retorna `HubApplicationEntry`, um DTO de apresentacao. A view nao recebe Models Eloquent como contrato de autorizacao.

## Uso obrigatorio de ApplicationEntry

O Hub nao consulta diretamente:

- `application_accesses`;
- `organization_memberships`;
- `contracts`;
- `contract_application_grants`;
- roles;
- permissions;
- status internos de autorizacao.

Toda aplicacao exibida no Hub passou por `EvaluateApplicationEntry` no backend.

Aplicacoes negadas nao sao exibidas ao usuario final nesta etapa.

## Contextos

Quando uma aplicacao possui `ApplicationContext`, o Hub avalia cada contexto explicitamente.

Um contexto autorizado gera uma entrada de Hub.

Mais de um contexto autorizado para a mesma aplicacao e representado como mais de uma entrada, preservando a informacao sem escolher contexto implicitamente.

O Hub nao usa `user.organization_id`, nao seleciona primeira organizacao e nao infere contexto por ultimo acesso.

## Determinismo temporal

A camada HTTP obtem o instante uma vez e o fornece ao resolvedor.

O mesmo `at` e usado em todas as chamadas de `ApplicationEntry` durante a montagem daquela pagina do Hub.

## Fronteira entre apresentacao e autorizacao

O Hub e uma interface de apresentacao.

Ele pode esconder aplicacoes negadas porque a decisao ja foi tomada no servidor por `ApplicationEntry`, mas isso nao substitui autorizacao server-side em endpoints futuros de entrada.

Qualquer endpoint futuro de lancamento de aplicacao deve executar autorizacao novamente no backend.

## Lancamento de aplicacoes

O modelo atual de `Application` e `ApplicationContext` nao possui destino seguro de lancamento.

O protocolo arquitetural inicial de lancamento CORE -> aplicacao consumidora esta definido em `docs/decisions/ADR-002-core-launch-protocol-and-legacy-consumer.md`.

O lado CORE do protocolo esta implementado em `docs/architecture/core-application-launch-protocol.md`.

Quando uma entrada possui `ApplicationClient` ativo e callback HTTPS configurado, o Hub inicia o lancamento por formulario `POST` com CSRF para o endpoint CORE. O Hub nao navega diretamente para aplicacao consumidora nem transporta identidade no card.

Quando a aplicacao/contexto ainda nao possui configuracao valida de launch, o Hub preserva o comportamento seguro e mostra a entrada como indisponivel para lancamento.

## Evolucao futura

Evolucoes esperadas exigem decisao propria:

- OAuth 2.0 / OIDC;
- URLs de aplicacao ou clients com contrato de redirect;
- seletor rico de contexto quando houver regra canonica aprovada;
- painel administrativo para catalogo, grants e accesses.
