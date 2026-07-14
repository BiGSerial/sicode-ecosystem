# Code Review

## Objetivo

Definir checklist normativo de revisao de codigo e documentacao.

## Quando esta skill e obrigatoria

Use ao revisar PR, diff, patch ou entrega de agente.

## Fontes normativas

- `docs/skills/README.md`
- todas as skills aplicaveis ao diff.

## Regras obrigatorias

- Priorize bugs, riscos, regressões, falhas de segurança e testes ausentes.
- Verifique aderencia ao canon, ADRs e skills.
- Revise autorizacao, banco, logging e boundaries quando tocados.
- Revise frontend e acessibilidade quando UI for tocada.
- Findings devem ter arquivo/linha quando possivel.

## Padroes recomendados

- Ordene por severidade.
- Separe findings de resumo.
- Declare riscos residuais.

## Padroes proibidos

- Review baseado em gosto pessoal.
- Aprovar sem testar ou sem declarar que nao testou.
- Ignorar mudanca arquitetural escondida.

## Processo de execucao

1. Identifique escopo do diff.
2. Liste skills aplicaveis.
3. Procure violações de canon/boundary.
4. Revise testes e segurança.
5. Entregue findings objetivos.

## Checklist de conclusao

- Findings priorizados.
- Skills consideradas.
- Test gaps apontados.
- Sem preferencias subjetivas como bloqueio.

## Quando interromper e propor ADR

- Diff altera decisao fundadora.
- Nova arquitetura aparece sem ADR.

