# Processamento de Ciclo de Vida Temporal de Identidade e Acesso no SICODE CORE

Data: 21/07/2026

## Objetivo

Este documento especifica a solução arquitetural para o processamento explícito, idempotente e auditável da expiração temporal de **contratos institucionais** (`contracts`) e **acessos individuais de usuários** (`application_accesses`) no SICODE CORE.

## Problema Resolvido

No SICODE CORE, a autorização efetiva de entrada (`EvaluateApplicationEntry`) avalia a vigência temporal em tempo real comparando o instante atual ou de referência com os campos `starts_at` e `ends_at` dos registros ativos.

No entanto, registros cujas datas finais (`ends_at`) já foram ultrapassadas permaneceriam fisicamente com `status = 'active'` no banco de dados até que ocorra um processamento explícito de expiração.

Esta funcionalidade fornece:

1. **Transição de estado persistida**: alteração do estado de `active` para `expired` de contratos e acessos cuja data limite expirou;
2. **Auditabilidade explícita**: emissão atômica de eventos de auditoria imutáveis (`CONTRACT_EXPIRED` e `APPLICATION_ACCESS_EXPIRED`) com ator sistêmico (`actor_type = 'SYSTEM'`, `actor_id = null`);
3. **Idempotência**: garantia de que execuções repetidas ou reprocessamentos não re-alterem registros nem gerem auditorias duplicadas;
4. **Execução determinística e agendada**: capacidade de execução em lote com parâmetro opcional de instante de referência (`--at=`) e modo de simulação (`--dry-run`).

## Separação entre Tempo Real e Estado Persistido

- **Última Linha de Defesa (Tempo Real)**: `EvaluateApplicationEntry` continua avaliando `starts_at <= at` e (`ends_at IS NULL` ou `ends_at >= at`). Acessos com vigência vencida são **rejeitados em tempo real**, mesmo que o job de lote ainda não tenha sido executado.
- **Governança de Estado Persistido**: O processamento temporal em lote converte registros `status = 'active'` com `ends_at <= referenceAt` para `status = 'expired'`, garantindo consistência no banco e na trilha de auditoria.

## Regras de Elegibilidade e Transição

### Contratos (`contracts`)

- **Elegível se**: `status = 'active'`, `ends_at IS NOT NULL` e `ends_at <= referenceAt`.
- **Transição**: `status` de `active` -> `expired`.
- **Registros ignorados**: contratos com `status` em `draft`, `suspended`, `ended` ou `expired`, ou cujo `ends_at` seja nulo ou futuro.
- **Auditoria**: Emite `CoreAuditAction::ContractExpired` com o ator `SYSTEM`.

### Acessos Individuais (`application_accesses`)

- **Elegível se**: `status = 'active'`, `ends_at IS NOT NULL` e `ends_at <= referenceAt`.
- **Transição**: `status` de `active` -> `expired`.
- **Registros ignorados**: acessos com `status` em `suspended`, `revoked` ou `expired`, ou cujo `ends_at` seja nulo ou futuro.
- **Auditoria**: Emite `CoreAuditAction::ApplicationAccessExpired` com o ator `SYSTEM`.

## Concorrência e Idempotência

- As atualizações ocorrem dentro de transações de banco de dados (`DB::transaction`).
- É utilizada seleção com trava de linha (`lockForUpdate()`) para garantir que modificações concorrentes não criem condição de corrida.
- Se o registro deixar de estar ativo durante o processamento (ex.: suspenso ou revogado por outra ação), ele é ignorado e contabilizado como `ignoredCount`.
- Execuções subsequentes do mesmo instante de referência encontram 0 registros elegíveis e retornam sem emitir auditorias duplicadas.

## Eventos de Auditoria

- `CONTRACT_EXPIRED`:
  - `subject_type`: `CONTRACT`
  - `actor_type`: `SYSTEM` (`actor_id = null`)
  - `details`: `organization_id`, `previous_status`, `new_status`, `starts_at`, `ends_at`, `effective_at`.
- `APPLICATION_ACCESS_EXPIRED`:
  - `subject_type`: `APPLICATION_ACCESS`
  - `actor_type`: `SYSTEM` (`actor_id = null`)
  - `application_id` e `context_id` preservados conforme a concessão.
  - `details`: `user_id`, `application_id`, `context_id`, `previous_status`, `new_status`, `starts_at`, `ends_at`, `effective_at`.

Segredos, credenciais ou dados pessoais completos não são persistidos nos detalhes de auditoria.

## Modo Dry-Run e Comando Artisan

Comando:

```bash
php artisan core:process-expired-accesses {--dry-run} {--at=}
```

- `--dry-run`: calcula a elegibilidade sem alterar banco nem emitir auditoria.
- `--at=`: permite passar uma data/hora no formato ISO8601 (ex.: `2026-07-21T18:00:00Z`) para testes determinísticos ou simulações retroativas.

Saída objetiva:

```text
dry_run=false
reference_at=2026-07-21T17:56:56Z
contracts_eligible=2
contracts_processed=2
contracts_ignored=0
accesses_eligible=5
accesses_processed=5
accesses_ignored=0
```

## Agendamento (`Schedule`)

No arquivo `routes/console.php`:

```php
Schedule::command('core:process-expired-accesses')
    ->hourly()
    ->withoutOverlapping()
    ->onOneServer();
```

*Justificativa da Frequência*: A execução de hora em hora proporciona governança quase em tempo real, transicionando contratos e acessos expirados sem sobrecarregar o banco de dados. `withoutOverlapping()` impede execuções paralelas concorrentes.

## Itens Explicitamente Adiados

Conforme escopo restrito da arquitetura, os seguintes itens **NÃO** fazem parte desta entrega:
- Revogação em cascata ao suspender/desativar organizações;
- Encerramento automático de acessos vinculados a um contrato encerrado;
- Revogação automática de `ContractApplicationGrant`;
- Notificações por e-mail/webhook de expiração.
