# SICODE Legacy Snapshot — Banco Histórico de Regressão

## Identidade

| Atributo | Valor |
| --- | --- |
| Service App | `sicode-legacy-snapshot` (profile: `snapshot`) |
| Service MariaDB | `sicode-legacy-snapshot-mariadb` (profile: `snapshot`) |
| Database | `sicode_legacy` |
| Volume MariaDB | `ecosystem_sicode-legacy-mariadb-data` (**preservado**) |
| Volume Storage App | `ecosystem_sicode-legacy-sp-storage-app` (legado) |
| Volume Storage Logs | `ecosystem_sicode-legacy-sp-storage-logs` (legado) |
| Porta HTTP | 8085 |
| Porta MariaDB | 3313 |
| Redis DBs | 8-11 |
| Redis Prefix | `sicode:legacy:snapshot:` |
| Cookie de Sessão | `sicode_snapshot_session` |
| Storage Prefix | `legacy/snapshot` |
| `SICODE_UNIT` | `sp` |
| `SICODE_IDENTITY_MODE` | `reconciliation` |
| `SICODE_ISOLATION_GUARD_ENABLED` | `false` |
| CORE Client | `sicode-legacy-snapshot-local` |

## Propósito

Ambiente de regressão e compatibilidade. Não é o SP canônico.

- Não recebe Launch oficial do CORE Hub.
- Não executa provisioning.
- Não participa do ciclo de desenvolvimento incremental.
- Serve para verificar comportamento de código contra dados históricos
  (quando existirem no volume).

## Estado atual do volume (2026-07-23)

O volume `ecosystem_sicode-legacy-mariadb-data` foi limpo em sessão anterior
(2026-07-22 21:33). Contém apenas schema (migrations) + 2 project_review_items.
Os dados históricos originais (8,5M linhas) não são recuperáveis.

Ver `docs/reports/legacy-sp-historical-snapshot-preservation.md` para
detalhes completos.

## Uso

```bash
# Subir snapshot (não sobe com docker compose up padrão)
make legacy-snapshot-up

# Inspecionar banco
make legacy-snapshot-inspect

# Parar
make legacy-snapshot-down
```

## Restaurar dados históricos (quando disponíveis)

Se existir um dump futuro para restaurar no snapshot:

```bash
# NUNCA restaurar no SP Clean
# Restaurar APENAS no snapshot:
gunzip -c /path/to/dump.sql.gz | \
  docker exec -i ecosystem-sicode-legacy-snapshot-mariadb-1 \
  mariadb -usicode_legacy -plegacy_dev_password sicode_legacy
```

## Guards aplicados

- `SICODE_ISOLATION_GUARD_ENABLED=false` — guard desabilitado (não é runtime canônico)
- `SICODE_IDENTITY_MODE=reconciliation` — provisioning bloqueado por configuração
- CORE Client diferente do SP oficial — impede receber Launch oficial
- Cookie diferente (`sicode_snapshot_session`) — evita colisão com SP Clean

## O que NÃO fazer com o snapshot

- Não fazer `docker volume rm ecosystem_sicode-legacy-mariadb-data`
- Não apontar `sicode-legacy` (SP Clean) para este volume
- Não executar provisioning neste ambiente
- Não usar este ambiente como referência para desenvolvimento SP
