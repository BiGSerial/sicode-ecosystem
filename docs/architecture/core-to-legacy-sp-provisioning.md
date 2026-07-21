# CORE -> Legacy SP Provisioning

Data: 2026-07-21

## Objetivo

O SICODE CORE possui um cliente tecnico dedicado para provisionar projecoes locais de `Organization` e `User` na instancia SICODE Legacy SP. O fluxo materializa `companies`, `users`, `core_organization_links` e `core_identity_links` no Legacy SP por contrato HTTP, sem criar sessao, sem emitir Launch e sem executar `ApplicationEntry`.

## Fronteira

Responsabilidades do CORE:

- permanecer autoridade canonica de identidade, organizacao e membership;
- selecionar explicitamente organizacao e usuario CORE;
- validar status local elegivel;
- validar membership ativa do usuario na organizacao;
- provisionar organizacao antes do usuario;
- chamar somente o contexto SP;
- registrar operacao, resultado e auditoria segura;
- permitir retry seguro com idempotency key estavel.

Responsabilidades do Legacy SP:

- manter IDs locais proprios;
- validar runtime `SICODE_UNIT=sp`;
- validar `identity_mode=provisioning`;
- autenticar client tecnico;
- criar/atualizar projecoes locais minimas;
- manter links locais entre CORE e Legacy;
- rejeitar conflitos e status ainda nao suportados.

O Legacy ES nao e alvo deste cliente. Configuracao CORE com contexto diferente de `sp` bloqueia a operacao localmente.

## Implementacao CORE

Fronteira de codigo:

```text
apps/sicode-core/app/LegacyProvisioning/
```

Principais componentes:

- `LegacySpProvisioningClient`: cliente HTTP dedicado;
- `OrganizationProvisioningRequest` e `UserProvisioningRequest`: DTOs de entrada do contrato Legacy;
- `LegacyProvisioningHttpResult` e `LegacyProvisioningActionResult`: resultados tipados;
- `LegacyProvisioningIdempotencyKeys`: politica deterministica de idempotencia;
- `ProvisionOrganizationToLegacySp`: action de organizacao;
- `ProvisionUserToLegacySp`: action de usuario;
- `ProvisionLegacySpAccess`: orquestrador do slice;
- `LegacyProvisioningOperationRecorder`: persistencia minima de tentativas;
- `LegacyProvisioningAudit`: eventos CoreAudit por allowlist.

Provisioning nao reutiliza classes de `App\ApplicationLaunch`.

## Contrato HTTP

Endpoints consumidos:

```text
POST /api/core/provisioning/organizations
POST /api/core/provisioning/users
```

Versao atual:

```text
contract_version=2026-07-21
```

Payload de organizacao enviado pelo CORE:

- `client_identifier`;
- `client_secret`;
- `contract_version`;
- `idempotency_key`;
- `core_issuer`;
- `core_organization_id`;
- `name`;
- `status=active`.

Payload de usuario enviado pelo CORE:

- `client_identifier`;
- `client_secret`;
- `contract_version`;
- `idempotency_key`;
- `core_issuer`;
- `core_subject`;
- `core_organization_id`;
- `name`;
- `email`, quando existir;
- `status=active`.

O CORE nunca envia senha, hash, cookie, Authorization header, grants completos, membership completo ou payload de Launch.

## Configuracao

Arquivo cacheavel:

```text
apps/sicode-core/config/legacy_provisioning.php
```

Variaveis:

```text
LEGACY_SP_PROVISIONING_ENABLED=false
LEGACY_SP_PROVISIONING_BASE_URL=
LEGACY_SP_PROVISIONING_CLIENT_ID=
LEGACY_SP_PROVISIONING_CLIENT_SECRET=
LEGACY_SP_PROVISIONING_ISSUER=sicode-core
LEGACY_SP_PROVISIONING_CONTRACT_VERSION=2026-07-21
LEGACY_SP_PROVISIONING_CONTEXT=sp
LEGACY_SP_PROVISIONING_CONNECT_TIMEOUT_SECONDS=2
LEGACY_SP_PROVISIONING_TIMEOUT_SECONDS=8
LEGACY_SP_PROVISIONING_RETRY_MAX_ATTEMPTS=3
LEGACY_SP_PROVISIONING_RETRY_BACKOFF_MS=150
LEGACY_SP_PROVISIONING_RETRY_JITTER_MS=50
LEGACY_SP_PROVISIONING_MAX_RESPONSE_BYTES=65536
```

Fora de `local` e `testing`, a URL base deve usar HTTPS. TLS verification nao e desabilitado.

## Autenticacao

O contrato Legacy atual recebe `client_identifier` e `client_secret` no body JSON. O CORE manteve esse formato para compatibilidade com os endpoints ja implementados.

Divida tecnica registrada: evoluir futuramente para autenticao tecnica em header dedicado, como Basic Auth ou headers proprios assinados. Essa evolucao deve atualizar contrato, testes e documentacao Legacy antes de troca produtiva.

Enquanto o secret permanece no body:

- apenas `LegacySpProvisioningClient` monta o payload bruto;
- actions, operacoes e CoreAudit nunca recebem o body completo;
- exceptions usam mensagens neutras;
- logs e auditoria usam allowlist;
- o secret nao e persistido em `legacy_provisioning_operations`.

## Idempotencia

Chaves deterministicas:

```text
organization:{core_organization_id}:provision:sp:v1
user:{core_subject}:organization:{core_organization_id}:provision:sp:v1
```

As chaves nao contem PII. O CORE persiste somente `idempotency_key_hash` em `legacy_provisioning_operations`.

A mesma chave e reutilizada em retries e novas tentativas da mesma projecao logica. O endpoint Legacy tambem e idempotente por links e locks locais.

## Retry

Retry automatico e limitado a:

- falha de conexao antes de resposta;
- HTTP 502;
- HTTP 503;
- HTTP 504;
- HTTP 429 somente com `Retry-After` seguro.

Nao ha retry automatico para:

- 400;
- 401;
- 403;
- 409;
- payload invalido;
- conflito categorico;
- rejeicao de runtime.

O retry usa numero maximo pequeno, backoff e jitter configuraveis. A idempotency key permanece igual em todas as tentativas da mesma operacao.

## Persistencia de operacoes

Tabela:

```text
legacy_provisioning_operations
```

Campos principais:

- alvo `target_application=sicode-legacy`;
- alvo `target_context=sp`;
- `entity_type`;
- `entity_id`;
- `organization_id`;
- `idempotency_key_hash`;
- `requested_at`;
- `completed_at`;
- `outcome`;
- `attempt_count`;
- `last_error_category`;
- `remote_local_id`;
- timestamps.

Nao persiste:

- secret;
- request completo;
- headers;
- senha;
- hash;
- payload sensivel.

## CoreAudit

Eventos adicionados:

- `LEGACY_PROVISIONING_REQUESTED`;
- `LEGACY_ORGANIZATION_PROVISIONED`;
- `LEGACY_ORGANIZATION_ALREADY_PROVISIONED`;
- `LEGACY_USER_PROVISIONED`;
- `LEGACY_USER_ALREADY_PROVISIONED`;
- `LEGACY_PROVISIONING_CONFLICT`;
- `LEGACY_PROVISIONING_REJECTED`;
- `LEGACY_PROVISIONING_UNAVAILABLE`;
- `LEGACY_PROVISIONING_PARTIALLY_COMPLETED`.

Subjects usam `ORGANIZATION` ou `USER`. Details registram apenas target, entity type, organization id, attempts e categoria segura.

## Falhas parciais

Fluxo parcial esperado:

```text
organization=created|already_provisioned|updated
user=conflict|rejected|unavailable
overall=partially_provisioned
```

O CORE nao executa rollback destrutivo remoto. Se a organizacao foi criada e o usuario falha, uma nova tentativa pode repetir a organizacao com resultado `already_provisioned` e tentar o usuario com a mesma idempotency key logica.

## Status suspenso

Postura conservadora:

- organizacao CORE suspensa ou desabilitada nao e enviada como ativa;
- usuario CORE nao ativo nao e enviado como ativo;
- membership suspensa, encerrada, futura ou ausente bloqueia o usuario;
- suspensao e reativacao remotas ficam fora deste slice.

## Commands

Interface interna inicial:

```text
php artisan core:legacy-sp:provision-organization {organization_id} {--dry-run}
php artisan core:legacy-sp:provision-user {user_id} {organization_id} {--dry-run}
```

Os comandos exigem IDs explicitos, mostram resultados categoricos e retornam exit code diferente de zero para rejeicao, conflito ou indisponibilidade. Eles nao imprimem secret.

## Relacao com Launch

Provisioning e Launch sao contratos independentes:

- provisioning cria projecoes e links locais;
- Launch troca codigo efemero e cria sessao local no consumidor;
- provisioning nao emite `application_launches`;
- provisioning nao chama `IssueApplicationLaunch`;
- provisioning nao avalia `ApplicationEntry`.

Launch para Legacy SP continua exigindo links previamente existentes no Legacy.

## Troubleshooting

`configuration_invalid`:

- verificar `LEGACY_SP_PROVISIONING_ENABLED`;
- verificar URL base;
- verificar `LEGACY_SP_PROVISIONING_CLIENT_ID`;
- verificar `LEGACY_SP_PROVISIONING_CLIENT_SECRET`;
- verificar contexto `sp`.

`authentication_rejected`:

- comparar client id no CORE e no Legacy;
- validar secret configurado no Legacy;
- revisar janela de rotacao.

`conflict`:

- procurar link duplicado ou nao ativo no Legacy;
- verificar company/user local com nome/e-mail ja existente sem link;
- encaminhar para reconciliacao manual.

`rejected`:

- verificar runtime Legacy SP;
- verificar `identity_mode=provisioning`;
- verificar status local CORE e membership.

`unavailable`:

- verificar rede entre CORE e Legacy SP;
- verificar health do Legacy SP;
- revisar timeouts e respostas 502/503/504.

## Teste integrado CORE -> Legacy SP

A suite CORE usa HTTP fake para o limite do cliente. Para demonstracao controlada contra Legacy SP local:

1. subir o monorepo com `make up`;
2. configurar o CORE com `LEGACY_SP_PROVISIONING_BASE_URL=http://sicode-legacy:8000`;
3. configurar o Legacy com `SICODE_UNIT=sp`, `SICODE_IDENTITY_MODE=provisioning` e client secret de teste;
4. criar Organization, User e membership no CORE de teste;
5. executar:

```text
docker exec ecosystem-sicode-core-1 php artisan core:legacy-sp:provision-user {user_id} {organization_id}
```

6. no Legacy, validar `companies`, `core_organization_links`, `users` e `core_identity_links`.

Nao executar contra ES. Nao executar contra banco produtivo ou dump restaurado sem autorizacao explicita.
