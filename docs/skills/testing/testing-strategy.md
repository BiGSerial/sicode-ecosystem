# Testing Strategy

## Objetivo

Definir estrategia de testes para manter regressao, autorizacao e invariantes sob controle.

## Quando esta skill e obrigatoria

Use ao implementar funcionalidade, corrigir bug, alterar dominio, autorizacao, banco, integracao ou UI interativa.

## Fontes normativas

- `docs/skills/testing/database-testing.md`
- skills de backend, database, security e frontend aplicaveis.

## Regras obrigatorias

- Bug corrigido deve receber teste de regressao quando tecnicamente reproduzivel.
- Regras de autorizacao devem ter teste permitido e negado.
- Invariantes de dominio devem ser testadas.
- Contratos de integracao devem ser testados no limite do sistema.
- Teste deve acompanhar o risco e o blast radius.

## Padroes recomendados

- Unidade para regra pura.
- Feature para fluxo HTTP/Livewire.
- Integracao para banco, fila, evento ou sistema externo.
- Regressao focada no bug real.

## Padroes proibidos

- Teste que apenas confirma mock.
- Ignorar caso negado de autorizacao.
- Trocar teste de banco PostgreSQL por SQLite quando comportamento PostgreSQL importa.

## Processo de execucao

1. Identifique risco.
2. Escolha nivel de teste.
3. Cubra sucesso, falha e autorizacao.
4. Execute suite relevante.

## Checklist de conclusao

- Regressao coberta quando aplicavel.
- Autorizacao testada.
- Invariantes testadas.
- Comandos executados documentados.

## Quando interromper e propor ADR

- Feature exige estrategia de teste nova.
- Ambiente atual nao permite validar comportamento critico.

