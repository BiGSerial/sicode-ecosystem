# Legacy SP Schema Archive (antigo "Snapshot")

> **Renomeado de "Snapshot" para "Schema Archive" em 2026-07-23.** O nome
> antigo sugeria um snapshot histórico de dados restaurável, o que nunca
> foi verdade neste ambiente — ver "Classificação" abaixo. O service Docker
> (`sicode-legacy-snapshot`, profile `snapshot`) e o volume
> (`ecosystem_sicode-legacy-mariadb-data`) **não foram renomeados**; esta é
> uma reclassificação conceitual/documental, não uma migração de infra.

## Classificação

Este ambiente **não é** um snapshot histórico de dados. Explicitamente:

- **Dados históricos indisponíveis** — os ~8,5M de linhas originais não são
  recuperáveis (ver `docs/reports/legacy-sp-historical-snapshot-preservation.md`).
- **O volume contém somente schema** (migrations) + 2 registros residuais
  de `project_review_items`, nada além disso.
- **Não serve para regressão baseada em massa de dados** — não há volume de
  dados real para validar comportamento sob carga/escala.
- **Não serve como backup** — não é alimentado por nenhum processo de
  backup do SP Clean nem de qualquer outro runtime.
- **Não serve para restauração operacional** — não deve ser usado como
  fonte de restore em caso de incidente no SP Clean.

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

Os identificadores técnicos (nome do service, prefixo Redis, cookie) mantêm
a palavra "snapshot" porque descrevem infraestrutura já provisionada — só a
classificação conceitual e a documentação mudam de nome.

## Propósito

Ambiente de compatibilidade de schema. Não é o SP canônico.

- Não recebe Launch oficial do CORE Hub.
- Não executa provisioning.
- Não participa do ciclo de desenvolvimento incremental.
- Serve apenas para validar que migrations/schema aplicam de forma limpa
  contra um banco MariaDB com o histórico de migrations original — **não**
  para testar comportamento contra volume de dados real.

## Estado atual do volume (2026-07-23)

O volume `ecosystem_sicode-legacy-mariadb-data` foi limpo em sessão anterior
(2026-07-22 21:33). Contém apenas schema (migrations) + 2 project_review_items.
Os dados históricos originais (8,5M linhas) não são recuperáveis.

Ver `docs/reports/legacy-sp-historical-snapshot-preservation.md` para
detalhes completos.

## Uso

```bash
# Subir o schema archive (não sobe com docker compose up padrão)
make legacy-snapshot-up

# Inspecionar banco
make legacy-snapshot-inspect

# Parar
make legacy-snapshot-down
```

## Restauração de dados históricos — fora de escopo

Este ambiente não é um alvo suportado de restore operacional. Se um dump
histórico futuro precisar ser inspecionado, isso deve ser feito num
ambiente descartável isolado, não tratado como uma capacidade suportada
deste schema archive. Em nenhuma hipótese um dump deve ser restaurado no
SP Clean (`sicode-legacy` / banco `sicode_sp`).

## Guards aplicados

- `SICODE_ISOLATION_GUARD_ENABLED=false` — guard desabilitado (não é runtime canônico)
- `SICODE_IDENTITY_MODE=reconciliation` — provisioning bloqueado por configuração
- CORE Client diferente do SP oficial — impede receber Launch oficial
- Cookie diferente (`sicode_snapshot_session`) — evita colisão com SP Clean

## O que NÃO fazer com o schema archive

- Não fazer `docker volume rm ecosystem_sicode-legacy-mariadb-data`
- Não apontar `sicode-legacy` (SP Clean) para este volume
- Não executar provisioning neste ambiente
- Não usar este ambiente como referência para desenvolvimento SP
- Não tratar este ambiente como backup ou fonte de restore do SP Clean
