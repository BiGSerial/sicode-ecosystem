# Investigação: dados inesperados no volume SICODE Legacy SP

Data: 2026-07-22

## Contexto

O usuário reportou que a instância "SICODE Legacy SP" deveria estar vazia de
dados (populada apenas incrementalmente, à medida que fosse sendo usada) e
encontrou dados inesperados ao testar o acesso. A hipótese inicial era de
vazamento de isolamento entre a instância ES e a SP.

O diagnóstico de isolamento (`docs/architecture/legacy-multi-unit-runtime.md`,
`docs/decisions/ADR-003-sicode-legacy-multi-unit-runtime.md` e a bateria de
testes em `tests/Feature/RedisRuntimeIsolationTest.php` /
`tests/Unit/RuntimeIsolationGuardTest.php`) confirma que SP e ES rodam em
containers e bancos MariaDB fisicamente distintos, sem nenhum caminho de
código que permita um processo escrever no banco do outro. Um vazamento
cross-instância está descartado como causa.

## Evidência coletada

Comandos executados contra `ecosystem-sicode-legacy-mariadb-1` (banco
`sicode_legacy`, usado exclusivamente pela unidade SP):

```sql
SELECT MIN(CREATE_TIME), MAX(CREATE_TIME), COUNT(*), SUM(TABLE_ROWS)
FROM information_schema.TABLES WHERE TABLE_SCHEMA='sicode_legacy' AND TABLE_ROWS>0;
-- 2026-07-20 16:04:08 | 2026-07-20 16:08:02 | 119 tabelas | 8.573.974 linhas

SELECT MIN(created_at), MAX(created_at) FROM users;
-- 2023-08-23 19:47:46 | 2026-07-22 15:04:39

SELECT MIN(created_at), MAX(created_at) FROM companies;
-- 2023-08-23 19:57:16 | 2026-07-22 15:04:39
```

Tabelas com maior volume (todas com `CREATE_TIME`/`UPDATE_TIME` dentro da
mesma janela de ~4 minutos em 2026-07-20): `wpas` (1.609.778 linhas),
`audits` (1.161.747), `operations_bkp_20251014` (904.802),
`notetimelines` (843.901), `historic_notes` (797.052), `notes` (684.795),
`productions` (391.240), `orders_bkp_20251014` (385.616),
`operations` (343.476), `files` (220.373), entre outras — 119 tabelas
não vazias no total.

`migrations`: 226 linhas, batches 1–94, todas aplicadas em
`2026-07-20 16:05:51`.

`core_identity_links`: 7 linhas · `core_organization_links`: 10 linhas,
ambas criadas em `2026-07-20 16:08:02` — logo após o bloco de dados em massa.

Nenhum arquivo de dump com nome/data batendo exatamente com essa janela foi
encontrado em `/home/will/code/tools/database/bkp/` ou
`/home/will/code/tools/database/backups/` (os dumps disponíveis são de
`sicode_prod-20260127`, `20260210`, `20260422`, `20260601` e um backup ES de
`20260722`). O histórico de shell disponível (`~/.bash_history`, 2000
linhas) não retém nenhum comando de restore/mysqldump/gunzip contra
`sicode_legacy` nessa data — está fora da janela retida ou foi executado por
outro processo/sessão sem registro no shell local.

## Classificação

| Origem candidata | Veredito | Evidência |
| --- | --- | --- |
| Migration/schema padrão | **Confirmado** para as 226 linhas de `migrations` | `php artisan migrate` rodado em lote único (batches 1–94) às 16:05:51 |
| Fixture E2E (`legacy:e2e:sp-fixtures`) | **Descartado** | Fixtures E2E são escopadas por prefixo e guardadas por `SICODE_E2E_ALLOWED`; volume (340 users, 8,5M linhas) é ordens de grandeza maior que qualquer fixture de teste |
| Provisioning técnico (CORE → Legacy SP) | **Parcial** — só explica `core_identity_links`/`core_organization_links` (17 linhas) | Provisioning cria uma organização/usuário por chamada de comando; não explica 340 usuários nem tabelas operacionais em massa |
| Launch (troca CORE) | **Descartado** | Confirmado (Parte 1) que Launch nunca cria usuário/empresa, apenas resolve vínculo existente e cria sessão |
| Vazamento cross-instância (ES → SP) | **Descartado** | Bancos fisicamente distintos, sem caminho de código compartilhado (ver testes de isolamento) |
| **Restauração em massa de dump pré-existente** | **Causa mais provável** | Padrão `CREATE_TIME`≈`UPDATE_TIME` em 119 tabelas dentro de uma janela de ~4 min é a assinatura típica de `mysql sicode_legacy < dump.sql` (DROP+CREATE+INSERT por tabela do mysqldump), não de uso orgânico da aplicação. `created_at` de `users`/`companies` remonta a 2023-08-23, incompatível com dados gerados localmente em julho de 2026 |
| Origem exata do dump | **Desconhecida** | Nenhum arquivo de dump disponível bate exatamente com a janela 2026-07-20 16:04–16:08; pode ter sido um pipe direto entre containers (sem arquivo intermediário) executado por uma sessão/agente anterior |

## Conclusão

Os dados encontrados no SP **não são um vazamento de isolamento entre ES e
SP** e **não foram criados pelo Launch**. O padrão de evidências indica uma
**restauração em massa de um banco Legacy pré-existente** (com histórico de
aplicação desde 2023) feita em 2026-07-20 entre 16:04 e 16:08, provavelmente
para popular o ambiente local com dados realistas — não algo produzido pelo
fluxo normal de provisioning incremental que o usuário esperava.

Há também evidência de atividade orgânica legítima após o restore: usuários
com `created_at` em 2026-07-22 15:04:39 (mesmo dia desta investigação),
consistente com testes reais feitos via provisioning/CORE nas últimas horas,
sobre a base já restaurada.

## Recomendação

1. Confirmar com quem executou a sessão anterior (ou revisar logs/histórico
   fora do host local, se existirem) qual foi a origem exata do restore.
2. Decidir explicitamente se o SP local deve manter esse snapshot histórico
   (ambiente "realista") ou ser resetado para um banco vazio populado apenas
   por provisioning — **nenhuma ação de reset foi tomada nesta tarefa**;
   o volume `ecosystem_sicode-legacy-mariadb-data` permanece intacto.
3. Se a decisão for resetar, usar `make legacy-runtime-clear-ephemeral`
   (cache/sessão/views) não afeta este banco — um reset de dados
   operacionais exige uma ação separada e explicitamente autorizada,
   fora do escopo desta tarefa.
