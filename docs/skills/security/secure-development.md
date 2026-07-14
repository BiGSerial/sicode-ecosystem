# Secure Development

## Objetivo

Definir padroes gerais de seguranca para implementacao.

## Quando esta skill e obrigatoria

Use em qualquer codigo que manipule identidade, autorizacao, entrada externa, dados sensiveis, arquivos, APIs, logs ou integracao.

## Fontes normativas

- `docs/architecture/core-identity-access-canon.md`
- `docs/skills/backend/authorization.md`
- `docs/skills/security/secure-logging.md`

## Regras obrigatorias

- Deny by default.
- Use allowlist de mass assignment.
- Nao exponha atributos sensiveis em serializacao.
- IDs externos nao sao autoridade global.
- Proteja contra enumeracao de usuarios, organizations e external identities.
- Operacoes de seguranca devem ser transacionais quando alterarem mais de um estado.
- Registre eventos auditaveis sem vazar segredo.

## Padroes recomendados

- Rate limiting em endpoints sensiveis.
- Mensagens de erro neutras para autenticacao/identidade.
- Menor privilegio para jobs, tokens e clients.

## Padroes proibidos

- Retornar stack trace ou dados sensiveis ao usuario.
- Logar token, senha, cookie ou Authorization.
- Confiar em dado de client para autorizacao.

## Processo de execucao

1. Identifique dados sensiveis.
2. Defina autorizacao e rate limit.
3. Revise serializacao/logging.
4. Teste caminhos negados.

## Checklist de conclusao

- Deny path coberto.
- Dados sensiveis protegidos.
- Logs redigidos.
- Mass assignment revisado.

## Quando interromper e propor ADR

- Nova autoridade de seguranca.
- Novo protocolo.
- Novo tipo de segredo persistido.

