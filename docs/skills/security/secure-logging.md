# Secure Logging

## Objetivo

Definir logging seguro por allowlist e redaction.

## Quando esta skill e obrigatoria

Use ao criar logs, exceptions, auditoria, traces, debug dumps ou contexto de erro.

## Fontes normativas

- `docs/skills/security/secure-development.md`

## Regras obrigatorias

- Logue por allowlist, nao por dump de request/model.
- Redija Authorization, cookies, password, token, secrets e chaves.
- PII deve ser minimizada.
- Exceptions devem preservar contexto tecnico sem expor segredo.
- Logs de seguranca devem conter contexto minimo para auditoria: ator, acao, alvo, resultado, timestamp/correlation id quando disponivel.

## Padroes recomendados

- Use IDs canonicos em vez de dados pessoais quando possivel.
- Separe evento auditavel de log tecnico.
- Redija payloads externos antes de logar.

## Padroes proibidos

- `logger($request->all())`.
- Logar headers completos.
- Logar token, cookie ou senha.
- Logar SQL com dados sensiveis em producao.

## Processo de execucao

1. Defina finalidade do log.
2. Monte allowlist.
3. Aplique redaction.
4. Verifique PII.

## Checklist de conclusao

- Sem segredo.
- Contexto suficiente.
- PII minimizada.
- Exceptions nao vazam dados.

## Quando interromper e propor ADR

- Novo requisito de auditoria regulatoria.
- Necessidade de armazenar payload sensivel.

