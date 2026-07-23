# SICODE Legacy SP Clean — Instância Canônica de São Paulo

## Identidade

| Atributo | Valor |
| --- | --- |
| Service | `sicode-legacy` |
| Database MariaDB | `sicode_sp` |
| Service MariaDB | `sicode-legacy-sp-mariadb` |
| Volume MariaDB | `ecosystem_sicode-legacy-sp-clean-data` |
| Volume Storage App | `ecosystem_sicode-legacy-sp-clean-storage-app` |
| Volume Storage Logs | `ecosystem_sicode-legacy-sp-clean-storage-logs` |
| Porta HTTP | 8083 |
| Porta MariaDB | 3312 |
| Redis DBs | 4 (default), 5 (cache), 6 (session), 7 (queue) |
| Redis Prefix | `sicode:legacy:sp:` |
| Cookie de Sessão | `sicode_sp_session` |
| Storage Prefix | `legacy/sp` |
| `SICODE_UNIT` | `sp` |
| `SICODE_IDENTITY_MODE` | `provisioning` |
| `SICODE_EXPECTED_DATABASE` | `sicode_sp` |
| `SICODE_ISOLATION_GUARD_ENABLED` | `true` |
| CORE Client | `sicode-legacy-sp-local` |
| CORE Context | `SP` |

## Propósito

Esta é a instância oficial de desenvolvimento e integração para São Paulo.
Toda criação de empresa, usuário, vínculo e sessão SP ocorre aqui.

## Bootstrap

```bash
# 1. Subir o banco e a aplicação
make legacy-sp-clean-up

# 2. Aplicar migrations (exige confirmação)
make legacy-sp-clean-migrate

# 3. Aplicar seeds técnicos
docker compose exec sicode-legacy php artisan db:seed --force

# 4. Verificar estado inicial
make legacy-sp-clean-smoke
```

## Seeds técnicos

Após migrations, nascem automaticamente:

| Tabela | Origem | Classificação |
| --- | --- | --- |
| `cancellation_categories` (5) | `CancellationCategorySeeder` | Schema obrigatório — categorias de cancelamento |
| `project_review_items` (2) | Migration `2026_03_16_120000` | Dados de domínio — itens normativos de revisão de projeto |

Nenhum dado de usuário, empresa ou histórico ES é criado automaticamente.

## Provisioning

Provisioning é executado via CORE (HTTP real):

```bash
# Usando o e2e completo:
make legacy-sp-clean-e2e

# Ou manualmente via artisan:
docker compose exec sicode-core php artisan \
  core:legacy-sp:provision-organization {organization_id}

docker compose exec sicode-core php artisan \
  core:legacy-sp:provision-user {user_id} {organization_id}
```

## Launch

ApplicationEntry → CORE Hub → callback → exchange → sessão Legacy SP.

O cookie é `sicode_sp_session`. A sessão fica no Redis DB 6.

## Lifecycle

```bash
docker compose exec sicode-core php artisan core:legacy-sp:suspend-user {id}
docker compose exec sicode-core php artisan core:legacy-sp:reactivate-user {id}
docker compose exec sicode-core php artisan core:legacy-sp:suspend-organization {id}
docker compose exec sicode-core php artisan core:legacy-sp:reactivate-organization {id}
```

## ADS

`SpAdsSubmissionPolicy` está registrada para `unit=sp`. Requer:
- Capability `ads.delivery` (configurada em `sicode.units.sp.capabilities`)
- `CoreOrganizationLink` ativo
- `WorkReport` não rejeitado
- Pelo menos uma `Order` ativa

## Guards

- `SICODE_ISOLATION_GUARD_ENABLED=true` — guard completo ativo no boot
- `SICODE_EXPECTED_DATABASE=sicode_sp` — rejeita qualquer DB diferente
- Guard novo: rejeita `SICODE_IDENTITY_MODE=provisioning` + DB `sicode_legacy`
- Sem dependência do volume snapshot

## Variáveis de ambiente locais (não versionadas)

Crie `.env.local` na raiz e defina:

```bash
LOCAL_LEGACY_SP_DATABASE_PASSWORD=sp_dev_password
LOCAL_LEGACY_SP_ROOT_PASSWORD=sp_root_password
SICODE_LEGACY_CORE_CLIENT_SECRET=local_legacy_core_secret
```

## Smoke test

```bash
make legacy-sp-clean-smoke
```

Saída esperada:
```
Unit: sp
IdentityMode: provisioning
Database: sicode_sp
Users: <N>
Companies: <N>
IdentityLinks: <N>
OrgLinks: <N>
Guard: sicode_sp
```
