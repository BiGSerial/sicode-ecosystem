# Task Execution

## Objetivo

Definir o fluxo obrigatorio de execucao de tarefas por agentes.

## Quando esta skill e obrigatoria

Use em toda tarefa de desenvolvimento, documentacao, review ou analise tecnica.

## Fontes normativas

- `AGENTS.md`
- `docs/agent/project-context.md`
- `docs/skills/README.md`

## Regras obrigatorias

- Leia `AGENTS.md`.
- Identifique dominio e skills aplicaveis.
- Leia ADRs e arquitetura relevantes.
- Analise codigo/documentacao existente antes de editar.
- Declare e mantenha escopo.
- Antes de codificar, liste mentalmente ou na resposta as skills aplicaveis ao dominio da tarefa.
- Nao antecipe tarefa futura.
- Registre conflitos.
- Na saida final, informe as skills efetivamente utilizadas quando a tarefa alterar codigo ou documentacao tecnica.

## Padroes recomendados

- Trabalhe em passos pequenos.
- Teste incrementalmente.
- Revise diff antes de concluir.
- Atualize documentacao quando o contrato mudar.

## Padroes proibidos

- Implementar sem ler skill aplicavel.
- Criar padrao local concorrente.
- Corrigir fora do escopo sem necessidade.
- Ignorar worktree sujo.

## Processo de execucao

1. Ler contexto.
2. Identificar dominio.
3. Selecionar skills.
4. Consultar ADR/arquitetura.
5. Analisar artefatos existentes.
6. Executar escopo.
7. Validar.
8. Revisar diff.
9. Informar skills usadas e resultado.

## Checklist de conclusao

- Skills aplicaveis consultadas.
- Escopo respeitado.
- Validações executadas ou limitação informada.
- Diff revisado.

## Quando interromper e propor ADR

- Tarefa contradiz canon.
- Mudanca exige nova decisao arquitetural.
- Evidencia existente invalida premissa da tarefa.
