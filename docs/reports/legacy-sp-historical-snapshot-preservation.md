# Preservação do Snapshot Histórico do SICODE Legacy SP

Data: 2026-07-23

## Contexto

Este relatório registra o estado do banco de dados encontrado no volume
`ecosystem_sicode-legacy-mariadb-data` no momento em que a separação formal
dos ambientes SP Clean e Snapshot foi executada.

## Estado encontrado no volume (2026-07-23)

Comandos executados contra o container `ecosystem-sicode-legacy-mariadb-1`
(banco `sicode_legacy`, volume `ecosystem_sicode-legacy-mariadb-data`):

```sql
SELECT COUNT(*) AS tables
FROM information_schema.TABLES WHERE TABLE_SCHEMA='sicode_legacy';
-- 125 tabelas

SELECT COUNT(*) AS migrations, MAX(batch) AS max_batch FROM migrations;
-- 224 migrations, batch=1

SELECT MIN(CREATE_TIME), MAX(CREATE_TIME)
FROM information_schema.TABLES WHERE TABLE_SCHEMA='sicode_legacy';
-- 2026-07-22 21:33:35 | 2026-07-22 21:33:51

SELECT COUNT(*) FROM users;         -- 0
SELECT COUNT(*) FROM companies;     -- 0
SELECT COUNT(*) FROM core_identity_links;  -- 0
SELECT COUNT(*) FROM core_organization_links; -- 0
```

Tabelas com dados: `migrations` (224 linhas), `project_review_items` (2 linhas).

Tamanho total: 6,91 MB.

## Discrepância com relatório anterior

O relatório `legacy-sp-unexpected-data-investigation-2026-07-22.md` documentou
8.573.974 linhas em 119 tabelas com `CREATE_TIME` entre 2026-07-20 16:04 e
16:08. Esses dados **não estavam presentes** no volume quando esta tarefa foi
iniciada (2026-07-23).

A sessão de agente imediatamente anterior (timestamp 2026-07-22T21:36Z) executou
um reset do banco entre 21:33:35 e 21:33:51 (janela confirmada pelo `CREATE_TIME`
das tabelas no volume), provavelmente via `migrate:fresh` + `migrate` ou
recriação do volume. O histórico de shell disponível não retém os comandos
exatos daquela sessão.

## Classificação do volume atual

| Atributo | Valor |
| --- | --- |
| Volume Docker | `ecosystem_sicode-legacy-mariadb-data` |
| Service atual | `sicode-legacy-snapshot-mariadb` (profile: snapshot) |
| Database | `sicode_legacy` |
| Estado | Migrations aplicadas, sem dados operacionais |
| Migrations | 224 (batch=1, aplicadas em 2026-07-22 21:33:35) |
| Usuários | 0 |
| Empresas | 0 |
| Dados históricos | **Não presentes** (foram limpos em sessão anterior) |
| Finalidade | Regressão/compatibilidade (não é o SP canônico) |

## Snapshot não pode ser recuperado

O backup histórico de 8.573.974 linhas está irrecuperável neste contexto:

- Nenhum arquivo de dump foi encontrado em `/home/will/code/tools/database/bkp/`
  ou `backups/` com data 2026-07-20 16:04–16:08.
- O volume foi sobrescrito antes desta tarefa.
- O histórico de shell da sessão que limpou o volume não está disponível.

## Implicações arquiteturais

O volume `ecosystem_sicode-legacy-mariadb-data` é preservado como
`sicode-legacy-snapshot-mariadb` (profile `snapshot`). Ele atualmente contém
apenas o schema (migrations), funcionando como uma linha-base de regressão de
schema — **não** de dados operacionais históricos.

O ambiente SP canônico usa um volume e banco inteiramente novos:
`ecosystem_sicode-legacy-sp-clean-data` / `sicode_sp`.

## Ação tomada

- Volume `ecosystem_sicode-legacy-mariadb-data`: **preservado, não deletado**.
- Service renomeado conceptualmente para `sicode-legacy-snapshot-mariadb`.
- Novo service e volume criados para SP Clean (`sicode-legacy-sp-mariadb` /
  `sicode-legacy-sp-clean-data`).
- Nenhum dump ou restore executado nesta tarefa.
