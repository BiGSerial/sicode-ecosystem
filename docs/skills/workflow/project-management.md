# Project Management

## Objetivo

Definir o fluxo obrigatorio para localizar, validar, atualizar e encerrar issues no GitHub Project do SICODE Ecosystem.

## Quando esta skill e obrigatoria

Use sempre que a tarefa envolver:

- localizar issue correspondente a uma entrega concluida;
- alterar status de issue ou item no GitHub Project;
- fechar issue, sub-issue ou macroentrega;
- preencher ou corrigir campos `Start date`, `Target date`, `Assignees`, `Status`, `Workstream`, `Phase`, `Priority` ou `Onda de Entrega`;
- registrar baixa, evidencia, data ou detalhe de aceite;
- reorganizar roadmap, macros, capacidades ou sub-issues.

## Fontes normativas

- `AGENTS.md`
- `docs/skills/README.md`
- `docs/reports/projectv2-7-current-scope-2026-07-15.md`
- GitHub Project `BiGSerial` numero `7`: `SICODE Ecosystem | Foundation & SP Starter`

## Regras obrigatorias

- Antes de fechar qualquer issue, leia o corpo da issue e identifique objetivo, escopo, criterios de aceite, entregavel e fora de escopo.
- Feche somente a issue correspondente ao escopo efetivamente entregue.
- Nao feche issue por semelhanca de titulo quando o criterio de aceite nao foi satisfeito.
- Nao feche macroentrega enquanto existir sub-issue vinculada aberta.
- Macroentrega e planejamento. Se a entrega real terminar antes, ajuste as datas da macro para refletir o adiantamento real quando o usuario solicitar baixa conclusiva.
- Sub-issue e evidencia executiva de entrega. Ao fechar, preencha `Start date` e `Target date` no Project com a data real de execucao/conclusao quando nao houver intervalo operacional mais preciso.
- Comentario na issue nao substitui campo do Project. Datas visiveis no roadmap precisam ser gravadas nos campos `Start date` e `Target date`.
- Sempre atribua a issue ao usuario responsavel solicitado antes de fechar. Quando o usuario disser "meu usuario", use `BiGSerial`, salvo instrucao contraria.
- Sempre registre comentario de baixa com data ISO, escopo concluido, evidencias objetivas, validacoes executadas e commit/artefato relacionado quando existir.
- Se a evidencia for parcial, nao feche a issue. Comente a situacao ou mantenha status coerente, como `In Progress`.
- Se uma sub-issue foi fechada, reavalie a macro pai apenas depois de consultar todas as sub-issues.
- Se todas as sub-issues de uma macro estiverem `Done`/fechadas e o objetivo da macro estiver satisfeito, feche a macro, ajuste datas e registre baixa consolidada.

## Processo obrigatorio de baixa

1. Identificar a entrega concluida no codigo, documentacao, teste, evidencia externa ou aceite do usuario.
2. Consultar o Project `#7` e listar candidatas por titulo, workstream, fase e corpo da issue.
3. Ler cada issue candidata antes de alterar status.
4. Mapear exatamente qual issue ou sub-issue corresponde ao escopo entregue.
5. Verificar criterios de aceite e fora de escopo.
6. Verificar se existem sub-issues vinculadas abertas quando a candidata for macro.
7. Atualizar assignee, se ausente.
8. Preencher campos do Project:
   - `Status`;
   - `Start date`;
   - `Target date`;
   - `Workstream`, `Phase`, `Priority` e `Onda de Entrega` se estiverem vazios e a classificacao for evidente pelo Planner.
9. Adicionar comentario de baixa com:
   - data da baixa;
   - escopo concluido;
   - evidencias;
   - testes/validacoes;
   - commit, PR, documento ou artefato relacionado;
   - limitacoes ou itens fora de escopo.
10. Fechar a issue correspondente.
11. Reconsultar o Project e confirmar que status, assignee e datas ficaram visiveis.
12. Informar ao usuario quais issues foram fechadas e quais nao foram fechadas por falta de correspondencia ou evidencia.

## Verificacao de macroentrega

Para macroentregas:

1. Identifique sub-issues vinculadas pelo relacionamento do GitHub ou pela estrutura do Project.
2. Confirme que todas as sub-issues estao fechadas ou `Done`.
3. Confirme que o criterio de aceite da macro foi satisfeito como conjunto.
4. Ajuste `Start date` e `Target date` da macro para o intervalo real consolidado quando a macro foi entregue antes do planejamento.
5. Feche a macro somente depois desses quatro passos.

## Padroes recomendados

- Use os numeros das issues na resposta final.
- Preserve diferenca entre macro planejada, capacidade funcional e sub-issue tecnica.
- Prefira data ISO (`YYYY-MM-DD`) nos comentarios e campos.
- Ao antecipar prazo, registre explicitamente que o planejamento foi adiantado por entrega concluida antes da data alvo.
- Mantenha issues de outras frentes abertas quando o trabalho foi CORE-only, SICODESK-only, SP-DATA-only ou INFRA-only.

## Padroes proibidos

- Fechar macro com sub-issue aberta.
- Fechar issue apenas porque o commit parece relacionado.
- Usar comentario como substituto de `Start date` ou `Target date`.
- Alterar datas de roadmap sem explicar se sao planejamento ou execucao real.
- Fechar item de outra frente para "aproveitar" entrega parcial.
- Marcar `Done` quando a validacao do criterio de aceite nao foi executada.

## Checklist de conclusao

- Issue correspondente lida.
- Criterios de aceite conferidos.
- Fora de escopo respeitado.
- Sub-issues da macro conferidas.
- Assignee preenchido.
- `Status` preenchido.
- `Start date` preenchido.
- `Target date` preenchido.
- Comentario de baixa com data e evidencias criado.
- Estado final reconsultado no Project.

## Quando interromper

- Quando nao for possivel identificar a issue correspondente.
- Quando a issue candidata tiver criterios de aceite mais amplos que a entrega.
- Quando a macro tiver sub-issues abertas.
- Quando a evidencia depender de validacao externa ainda nao executada.
- Quando o fechamento puder distorcer o roadmap executivo.
