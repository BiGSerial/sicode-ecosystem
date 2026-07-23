# Runtime multiunidade do SICODE Legacy

Data: 2026-07-20 (atualizado 2026-07-23)

Este documento define a fundacao local para executar uma unica base de codigo do SICODE Legacy em tres contextos isolados: ES, SP Clean e Snapshot.

## Tres contextos (atualizado 2026-07-23)

| Contexto | Service | DB | Volume | Propósito |
| --- | --- | --- | --- | --- |
| Legacy ES | `sicode-legacy-es` | `sicode` (tools_mariadb) | externo | Dados históricos reais ES |
| Legacy SP Clean | `sicode-legacy` | `sicode_sp` | `sp-clean-data` | Desenvolvimento SP canônico |
| Legacy Snapshot | `sicode-legacy-snapshot` | `sicode_legacy` | `mariadb-data` | Regressão/compatibilidade |

O Snapshot não recebe Launch oficial, não executa provisioning e não representa o SP canônico. Ver `docs/deployment/legacy-sp-clean-instance.md` e `docs/deployment/legacy-snapshot-instance.md`.



## Decisao operacional

O Legacy continua sendo Laravel 10 com Livewire 2 e banco MariaDB proprio por instancia. ES e SP compartilham codigo, mas nao compartilham runtime, banco, storage, cliente CORE nem contexto de aplicacao.

Cada processo Legacy deve declarar obrigatoriamente:

- `SICODE_UNIT`: `es` ou `sp`;
- `SICODE_IDENTITY_MODE`: `reconciliation` ou `provisioning`;
- `SICODE_INSTANCE_CODE` e `SICODE_INSTANCE_NAME`;
- `CORE_LAUNCH_CLIENT_IDENTIFIER`;
- `CORE_LAUNCH_CLIENT_SECRET`;
- `CORE_LAUNCH_REDIRECT_URI`;
- `CORE_LAUNCH_CONTEXT`: `ES` ou `SP`;
- parametros locais de storage, como `SICODE_STORAGE_PREFIX`.

`config/sicode.php` e a fonte local cacheavel dessa configuracao. O codigo de aplicacao deve consumir `CurrentUnit`, `UnitCapabilities` e contratos vinculados ao container, nao `env()` diretamente.

## Identidade e organizacao

O Launch CORE deve retornar o identificador estavel da organizacao autorizada apenas na troca backend-to-backend. O navegador continua recebendo somente parametros tecnicos como `code` e `state`.

O fluxo efetivo de empresa e:

```text
CORE organization
-> core_organization_links
-> companies.id local
-> CurrentCompanyContext
-> tabelas operacionais Legacy
```

`core_identity_links` e `core_organization_links` permanecem separados. Usuario vinculado ao CORE nao implica organizacao vinculada. Quando nao existir vinculo organizacional, o fluxo falha com erro controlado equivalente a `OrganizationLinkRequired`.

`users.company_id` nao e fallback do Launch CORE. Divergencia entre `users.company_id` e a empresa autorizada pelo Launch e rejeitada pela politica inicial, sem alterar a empresa principal do usuario.

## Contexto do Launch

O `context` recebido no exchange deve corresponder simultaneamente a:

- contexto configurado para a unidade em `sicode.units.<unit>.core_context`;
- `sicode.core.expected_context`;
- `core_integration.context`, mantido por compatibilidade.

Qualquer divergencia rejeita o Launch com mensagem publica generica e erro de dominio local `CoreLaunchContextMismatch`.

## Capacidades

`UnitCapability` e `UnitCapabilities` formam uma allowlist local de capacidades por unidade. Elas indicam se uma instancia possui determinado comportamento operacional disponivel, mas nao substituem Gates, Policies, roles ou regras de acesso do Legacy.

Capacidades desconhecidas sao rejeitadas no bootstrap da abstracao para impedir flags livres ou digitadas incorretamente.

## Hierarquia de diferencas ES/SP

A ordem aprovada para diferencas entre ES e SP e:

1. Configuracao por instancia.
2. Capacidade por unidade.
3. Policy ou Gate existente.
4. Strategy injetada por contrato local.
5. Workflow especifico e documentado.
6. Modulo separado, somente quando o dominio exigir.

Nao criar branches condicionais espalhados por controller, Livewire ou migration para regras regionais.

## Schema, banco e storage

O schema-base do Legacy e o mesmo para ES e SP. Migrations nao devem condicionar estrutura por `SICODE_UNIT`, `CurrentUnit` ou config de unidade.

Cada instancia deve apontar para banco e storage proprios. O runtime multiunidade nao migra arquivos existentes nem provisiona buckets/diretórios de producao.

## Provisionamento SP

Provisionamento de organizacoes e usuarios SP e um contrato separado do Launch/login:

- `ProvisionLegacyOrganization`;
- `ProvisionLegacyUser`;
- `SuspendLegacyUser`;
- `ReactivateLegacyUser`.

Esses contratos ainda nao implementam sincronizacao operacional. O modo de identidade (`reconciliation` ou `provisioning`) e configuracao explicita e nao deve ser inferido apenas por ES/SP.

## Inventario de condicionais

Condicionais regionais permitidas nesta fundacao:

- leitura de `SICODE_UNIT` apenas por `config/sicode.php`;
- resolucao de `CurrentUnit`;
- selecao centralizada de bindings por `UnitServiceProvider`;
- validacao de contexto CORE por `CoreLaunchContextValidator`;
- allowlist de capacidades por `UnitCapabilities`.

Condicionais regionais nao permitidas:

- migrations que criem schema diferente por unidade;
- persistir UUID CORE em `company_id`;
- inferir organizacao por email, dominio, nome da empresa, `users.company_id` ou parametro do navegador;
- espalhar `if ES/SP` em controllers, services ou componentes Livewire para decidir regra de dominio.
