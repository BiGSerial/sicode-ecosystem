# SICODE CORE Application Launch Protocol

Data: 2026-07-15

## Responsabilidade

Esta documentacao descreve a implementacao server-side CORE do protocolo aprovado em `docs/decisions/ADR-002-core-launch-protocol-and-legacy-consumer.md`.

O CORE emite um artefato efemero e opaco para transportar uma decisao de entrada autorizada ate uma aplicacao consumidora. O CORE nao transporta sessao Laravel, nao compartilha cookies e nao envia identidade confiavel pelo navegador.

## Emissao

Endpoint web autenticado:

```text
POST /applications/{application}/launch
```

A borda HTTP resolve o usuario da sessao local CORE, valida parametros tecnicos e chama `App\ApplicationLaunch\IssueApplicationLaunch`.

`IssueApplicationLaunch`:

1. recebe `User`, `Application`, `ApplicationContext` opcional e instante explicito;
2. reavalia `ApplicationEntry`;
3. rejeita qualquer decisao diferente de `ALLOWED`;
4. resolve um `ApplicationClient` ativo e unico para aplicacao/contexto;
5. deriva o callback exclusivamente de `application_clients.redirect_uris`;
6. gera `code` e `state` com entropia criptograficamente segura;
7. persiste somente `token_hash` e `state_hash`;
8. grava `APPLICATION_LAUNCH_ISSUED` na mesma transacao;
9. retorna DTO de redirect contendo somente callback, `code` e `state`.

O endpoint nao aceita `callback`, `redirect_url` ou destino equivalente enviado pelo navegador como autoridade.

## Artefato opaco

Tabela: `application_launches`.

Model: `App\Models\ApplicationLaunch`.

O token publico entregue ao navegador e o `code`. Ele nao e UUID de usuario, ID incremental, session ID, JWT, `core_subject` ou token auto-contido.

Persistencia:

- `token_hash`: SHA-256 do `code`;
- `state_hash`: SHA-256 do `state`;
- `user_id`;
- `application_id`;
- `context_id`;
- `client_id`;
- `callback_url`;
- `issued_at`;
- `expires_at`;
- `consumed_at`;
- `consumed_by_client_id`.

O valor bruto de `code` e `state` nao e persistido.

## TTL

O TTL padrao e 300 segundos, configurado por `CORE_LAUNCH_TTL_SECONDS`.

O valor e deliberadamente curto e pode ser ajustado por ambiente. A implementacao aplica minimo operacional de 60 segundos para evitar configuracao acidentalmente nula.

## Callback autorizado

O callback usado no redirect vem de `application_clients.redirect_uris`.

Regras atuais:

- somente `ApplicationClient` ativo;
- cliente deve pertencer a mesma aplicacao e ao mesmo contexto;
- deve existir exatamente um cliente ativo elegivel para a entrada;
- o primeiro redirect URI HTTPS configurado e usado;
- callback arbitrario de request e ignorado.

Quando a aplicacao/contexto nao possui configuracao valida de launch, o Hub mantem a entrada segura como indisponivel.

## Troca backend-to-backend

Endpoint tecnico:

```text
POST /api/core/launch/exchange
```

Payload esperado:

- `client_identifier`;
- `client_secret`;
- `code`;
- `state`.

A rota e server-to-server e retorna JSON.

## Autenticacao do consumidor

A autenticacao inicial usa secret compartilhado por cliente via configuracao de runtime:

```text
CORE_LAUNCH_CLIENT_SECRETS={"client-id":"secret"}
```

O segredo nao e persistido no banco, nao entra em auditoria e nao e devolvido por API. A comparacao usa `hash_equals`.

Rotacao automatica, hash persistido de secret, JWKS, OAuth Client Credentials e OIDC permanecem fora desta etapa.

## Consumo atomico

`App\ApplicationLaunch\ExchangeApplicationLaunch` localiza o artefato por `token_hash` dentro de transacao PostgreSQL e usa `lockForUpdate`.

Valida:

- client correto;
- `state` correto;
- ausencia de consumo anterior;
- validade temporal;
- client pertencente a aplicacao/contexto do artefato.

Ao aceitar a troca, grava `consumed_at` e `consumed_by_client_id` e registra `APPLICATION_LAUNCH_EXCHANGED` na mesma transacao.

Replay e bloqueado porque a segunda troca encontra `consumed_at` preenchido e registra `APPLICATION_LAUNCH_REPLAY_REJECTED`.

## Identidade minima

A resposta de troca e um DTO externo, nao serializacao de Model Eloquent.

Formato:

```json
{
  "iss": "sicode-core",
  "core_subject": "uuid-do-user-core",
  "application": "codigo-da-aplicacao",
  "context": "codigo-do-contexto-ou-null",
  "launch_id": "uuid-do-artefato",
  "issued_at": "timestamp",
  "expires_at": "timestamp",
  "state": "state-confirmado"
}
```

`core_subject` e o UUID canonico do usuario CORE. E-mail, username, permissoes, grants, contratos, senha, hash de senha, credenciais locais e cookies de sessao nao sao retornados.

## Auditoria

Eventos adicionados:

- `APPLICATION_LAUNCH_ISSUED`;
- `APPLICATION_LAUNCH_REJECTED`;
- `APPLICATION_LAUNCH_EXCHANGED`;
- `APPLICATION_LAUNCH_EXCHANGE_REJECTED`;
- `APPLICATION_LAUNCH_REPLAY_REJECTED`.

Subjects adicionados:

- `APPLICATION_LAUNCH`;
- `APPLICATION_LAUNCH_ATTEMPT`.

Auditoria nunca registra:

- `code` bruto;
- `state` bruto;
- `client_secret`;
- headers de autenticacao;
- payload bruto de request.

Para correlacao segura, a emissao pode gravar fingerprint nao reversivel do codigo.

## Respostas de erro

A troca tecnica retorna mensagens publicas neutras:

- `401` para consumidor nao autenticado ou credencial invalida;
- `422` para artefato invalido, expirado, consumido, de outro cliente ou com `state` divergente.

A auditoria interna preserva motivo tecnico por allowlist. A resposta publica nao diferencia detalhes de token inexistente, malformado, expirado ou consumido.

## Threat model implementado

| Ameaca | Protecao |
| --- | --- |
| Identidade confiavel pelo navegador | Navegador transporta apenas `code` e `state`. |
| Open redirect | Callback e derivado do catalogo CORE. |
| App errado consumindo codigo | Artefato vinculado a `ApplicationClient`. |
| Replay | `consumed_at` protegido por transacao e lock PostgreSQL. |
| Codigo vazado em banco | Banco guarda somente hash do codigo. |
| Secret vazado em auditoria | Auditoria rejeita chaves sensiveis e fluxo nao envia secret para detalhes. |
| Email usado como identidade | Resposta usa `core_subject`. |
| Serializacao acidental de Model | Resposta usa `ApplicationLaunchExchangeResult`. |

## Limites atuais

Ainda nao implementado:

- consumidor Laravel 10 Legacy;
- `core_identity_links`;
- sessao Legacy;
- OAuth/OIDC;
- JWT;
- rotação automatica de secrets;
- CRUD administrativo de clients/secrets;
- purge/scheduler de artefatos expirados.

Limpeza de artefatos expirados/consumidos fica como tarefa posterior, preservando a decisao de nao apagar dados tecnicos antes de definir retencao versus auditoria permanente.
