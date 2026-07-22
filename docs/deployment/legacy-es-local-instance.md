# Instância Local Isolada SICODE Legacy ES

## 1. Topologia e Arquitetura

O SICODE Ecosystem suporta a execução simultânea e isolada de duas instâncias do SICODE Legacy sobre a mesma base de código (`apps/sicode-legacy`):

1. **SICODE Legacy SP (`sicode-legacy`)**:
   - Porta HTTP: `8083`
   - Banco de Dados: `sicode_legacy` (container `sicode-legacy-mariadb`, porta `3311`)
   - Unidade (`SICODE_UNIT`): `sp`
   - Modo de Identidade (`SICODE_IDENTITY_MODE`): `provisioning`
   - Cookie de Sessão: `sicode_sp_session`
   - Prefixo de Cache: `sicode_sp_`
   - Prefixo de Storage: `legacy/sp`

2. **SICODE Legacy ES (`sicode-legacy-es`)**:
   - Porta HTTP: `8084`
   - Banco de Dados: `sicode` (container MariaDB existente `tools_mariadb`, rede `database_default`, porta interna `3306`)
   - Unidade (`SICODE_UNIT`): `es`
   - Modo de Identidade (`SICODE_IDENTITY_MODE`): `reconciliation`
   - Cookie de Sessão: `sicode_es_session`
   - Prefixo de Cache: `sicode_es_`
   - Prefixo de Storage: `legacy/es`

---

## 2. Container e Conexão do Banco de Dados ES

- **Container do Banco**: `tools_mariadb`
- **Imagem**: `mariadb:11` (`11.8.5+maria~ubu2404`)
- **Rede Docker**: `database_default`
- **Volume de Dados**: `/home/will/code/tools/database/docker-data/mariadb`
- **Database**: `sicode`
- **Usuário**: `sicode`
- **Grants**: `GRANT ALL PRIVILEGES ON sicode.* TO 'sicode'@'%'`
- **Tamanho da Base**: ~3,9 GB (3.917,19 MB)
- **Charset / Collation**: `utf8mb4` / `utf8mb4_unicode_ci`

---

## 3. Isolamento de Runtime (ES vs SP)

| Parâmetro | Instância SP (`sicode-legacy`) | Instância ES (`sicode-legacy-es`) |
|---|---|---|
| Service Compose | `sicode-legacy` | `sicode-legacy-es` |
| Porta HTTP Host | `8083` | `8084` |
| Banco | `sicode_legacy` | `sicode` |
| Unidade (`SICODE_UNIT`) | `sp` | `es` |
| Modo Identidade | `provisioning` | `reconciliation` |
| Contexto CORE | `SP` | `ES` |
| Client Identifier | `sicode-legacy-sp-local` | `sicode-legacy-es-local` |
| Cookie de Sessão | `sicode_sp_session` | `sicode_es_session` |
| Prefixo de Cache | `sicode_sp_` | `sicode_es_` |
| Storage Prefix | `legacy/sp` | `legacy/es` |
| Provisioning API | Habilitado | Rejeitado (404/reconciliation) |

---

## 4. Segurança, Backup e Migrations

- **Dump de Segurança**: Realizado backup comprimido antes de qualquer alteração:
  - Caminho local: `/home/will/code/tools/database/backups/sicode_es_backup_20260722_1530.sql.gz`
  - Tamanho: 299 MB (comprimido)
  - SHA256: `69ab3cbc29d05d5b681f48a7be1d11260782b4e6193d731b5336916f7f204fab`
- **Migrations no Entrypoint**: Desabilitadas em ambas as instâncias.
- **Diferença de Schema Registrada**:
  - Base ES possui 247 migrations executadas (`migrations` count = 247).
  - Tabelas de integração CORE (`core_identity_links` e `core_organization_links`) não estão presentes no banco ES e exigirão migration manual quando o Launch ES for ativado.

---

## 5. Comandos de Operação (Makefile)

```bash
# Subir instância ES
make legacy-es-up

# Parar instância ES
make legacy-es-down

# Logs do ES
make legacy-es-logs

# Shell no container ES
make legacy-es-shell

# Smoke test somente leitura no ES
make legacy-es-smoke

# Inspeção do banco ES
make legacy-es-db-inspect

# Verificar presença de tabelas CORE no ES
make legacy-es-schema-diff
```

---

## 6. Schedulers e Workers

Por padrão, schedulers, cron jobs e workers assíncronos que mutacionem o banco ES permanecem desabilitados no ambiente local para evitar duplicação ou escrita acidental sobre dados legados.
