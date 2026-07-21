# Legacy SP Provisioning Controlado

Data: 2026-07-21

## Objetivo

Definir o primeiro vertical slice de provisionamento controlado no SICODE Legacy SP para materializar projecoes locais de organizacao e usuario a partir do CORE, sem acoplar ao login local nem ao Application Launch.

## Fronteira

Provisioning:

- cria e atualiza projecoes locais minimas em `companies` e `users`;
- cria vinculos em `core_organization_links` e `core_identity_links`;
- nao autentica usuario;
- nao abre sessao;
- nao executa callback de launch;
- nao concede acesso operacional por si so.

Launch:

- continua em `GET /core/launch/callback` + exchange backend-to-backend;
- exige links previamente provisionados;
- falha controladamente quando links nao existem (`IdentityLinkRequired`, `OrganizationLinkRequired`).

## Diferenca ES/SP

- ES (`SICODE_UNIT=es`) rejeita endpoints de provisioning.
- SP em `identity_mode=reconciliation` rejeita endpoints de provisioning.
- SP em `identity_mode=provisioning` aceita endpoints autenticados.

## Endpoints

Contrato HTTP versionado por payload:

- `POST /api/core/provisioning/organizations`
- `POST /api/core/provisioning/users`

Versao do contrato:

- campo obrigatorio `contract_version`;
- valor atual: `2026-07-21`.

Consumidor CORE implementado:

- cliente tecnico em `apps/sicode-core/app/LegacyProvisioning/LegacySpProvisioningClient.php`;
- configuracao em `apps/sicode-core/config/legacy_provisioning.php`;
- documento de consumo em `docs/architecture/core-to-legacy-sp-provisioning.md`.

## Autenticacao de client

Autenticacao tecnica separada do launch e separada de usuario:

- request inclui `client_identifier` e `client_secret`;
- servidor valida contra `CORE_PROVISIONING_CLIENT_SECRETS`;
- comparacao com `hash_equals`;
- segredo nao e persistido, nao e auditado em claro e nao retorna em resposta.

Configuracao:

- `CORE_PROVISIONING_CLIENT_SECRETS` (JSON map);
- `CORE_PROVISIONING_CONTRACT_VERSION`;
- `CORE_PROVISIONING_REQUEST_TIMEOUT_SECONDS`;
- `CORE_PROVISIONING_LOCK_TIMEOUT_SECONDS`;
- `CORE_PROVISIONING_BLOCK_BROWSER_REQUESTS`;
- `CORE_PROVISIONING_PLACEHOLDER_EMAIL_DOMAIN`.

## Regras de seguranca

- rota sob middleware `api` (sem sessao web);
- middleware `core.provisioning.no_browser` rejeita requests com sinais de navegador;
- valida `Content-Type` JSON;
- rate limit tecnico `throttle:core-provisioning`;
- respostas publicas genericas (`Provisioning request rejected.`);
- sem retorno de senha, hash, secret ou payload sensivel.

## Payload de organizacao

Entrada minima:

- `client_identifier`;
- `client_secret`;
- `contract_version`;
- `idempotency_key`;
- `core_issuer`;
- `core_organization_id` (UUID CORE);
- `name`;
- `status` (`active|suspended`).

Saida:

- `result`: `created|already_provisioned|updated|conflict|rejected`;
- `organization.core_organization_id`;
- `organization.company_id` (ID local).

## Provisionamento de organizacao

Fluxo:

1. valida runtime SP + `identity_mode=provisioning`;
2. autentica client tecnico;
3. valida contrato e payload;
4. adquire lock transacional por chave logica;
5. procura `core_organization_links` por `core_issuer + core_organization_id + context`;
6. quando existe link ativo: retorna `already_provisioned` ou `updated` (nome permitido);
7. quando nao existe link: cria `companies` minima e cria `core_organization_links`;
8. comita em transacao unica;
9. retorna resultado categorico.

## Idempotencia de organizacao

Garantias combinadas:

- lock de concorrencia por chave logica (`GET_LOCK`);
- unicidade de `core_organization_links` ativa por contexto;
- retry com mesmo `core_organization_id` nao duplica link;
- retry com payload identico retorna `already_provisioned`.

## Conflitos de organizacao

Tratados como `result=conflict`:

- link duplicado ativo;
- link nao ativo para mesma chave;
- company vinculada indisponivel (soft deleted);
- nome de company existente sem link ativo (sem auto-link silencioso).

## Payload de usuario

Entrada minima:

- `client_identifier`;
- `client_secret`;
- `contract_version`;
- `idempotency_key`;
- `core_issuer`;
- `core_subject` (UUID CORE);
- `core_organization_id`;
- `name`;
- `email` opcional;
- `status` (`active|suspended`).

Saida:

- `result`: `created|already_provisioned|updated|conflict|rejected`;
- `user.core_subject`;
- `user.core_organization_id`;
- `user.user_id` (ID local);
- `user.company_id` (ID local).

## Provisionamento de usuario

Fluxo:

1. valida runtime e modo;
2. autentica client tecnico;
3. valida payload;
4. resolve `core_organization_links` ativo previamente provisionado;
5. adquire lock de concorrencia por sujeito;
6. procura `core_identity_links`;
7. quando ja vinculado: retorna `already_provisioned` ou `updated` (campos permitidos);
8. quando nao vinculado: cria `users` local e cria `core_identity_links`;
9. seta `users.company_id` para `companies.id` local resolvido;
10. comita em transacao unica.

## Politica de password local

- senha CORE e hash CORE nunca sao sincronizados;
- quando cria usuario local, gera senha aleatoria local de alta entropia;
- hash e gerado pelo cast `hashed` do Model Legacy;
- senha nunca retorna no payload.

## Politica de status

Slice inicial conservador:

- `status=active`: permitido;
- `status=suspended`: rejeitado (`rejected`) por falta de equivalencia segura imediata no modelo Legacy sem ampliar escopo.

Operacoes de suspensao/reativacao ficam como etapa futura.

## Conflitos de usuario

Tratados como `result=conflict`:

- link de identidade duplicado;
- link nao ativo;
- usuario local vinculado indisponivel;
- usuario vinculado em company diferente da autorizada;
- email em uso por usuario sem link (sem auto-link por email);
- email em uso por outro usuario no update.

## Transacoes e retries

- organization/user executam transacao unica por request;
- lock de concorrencia evita corrida para a mesma chave logica;
- constraints de unicidade dos links protegem invariantes finais;
- retry idempotente nao deve criar residuos de link duplicado.

## Auditoria

Eventos locais em log com allowlist (sem segredos):

- `organization.requested`;
- `organization.completed`;
- `organization.conflict`;
- `organization.rejected`;
- `user.requested`;
- `user.completed`;
- `user.conflict`;
- `user.rejected`;
- `authentication.rejected`.

Nao registrar:

- `client_secret`;
- senha ou hash;
- payload bruto completo;
- headers de autenticacao.

## Campos locais e invariantes preservados

- `companies.id` permanece identificador local;
- `users.id` permanece identificador local;
- UUID CORE nao substitui PK local;
- `core_organization_id` persiste apenas em `core_organization_links`;
- `core_subject` persiste apenas em `core_identity_links`.

## Operacoes futuras (fora deste slice)

- `SuspendLegacyOrganization`;
- `ReactivateLegacyOrganization`;
- `SuspendLegacyUser`;
- `ReactivateLegacyUser`;
- `UpdateLegacyOrganizationProjection`;
- `UpdateLegacyUserProjection`.
