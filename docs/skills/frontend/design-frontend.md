# Design Frontend

## Objetivo

Garantir que telas do SICODE sigam identidade visual consistente, acessivel e verificavel.

## Quando esta skill e obrigatoria

Use antes de criar ou alterar telas, layouts, componentes visuais, estados de UI, fluxos interativos ou design system.

## Fontes normativas

- `docs/design-system/README.md`
- `docs/design-system/foundations.md`
- `docs/design-system/components.md`
- `docs/design-system/layouts.md`
- `docs/design-system/accessibility.md`
- `docs/design-system/reference/sicode-core-hub-modelo.html`
- `docs/skills/frontend/tailwind-design-system.md`
- `docs/skills/frontend/blade-components.md`
- `docs/skills/frontend/livewire-development.md`
- `docs/skills/frontend/accessibility.md`

## Evidencia visual disponivel

Neste repositorio existem telas `welcome.blade.php` geradas pelo skeleton Laravel. Elas nao sao referencia visual SICODE e nao devem ser copiadas para telas finais.

A referencia visual normativa esta em `docs/design-system/reference/sicode-core-hub-modelo.html`. Ela e mock de composicao e comportamento visual, nao codigo de producao. A fundacao visual oficial esta versionada em `docs/design-system/` e `packages/design-system/theme.css`.

## Regras obrigatorias

- Use Mulish quando a stack visual estiver configurada.
- Nao invente paleta, hexadecimais, tokens ou identidade visual propria.
- Use os tokens centralizados em `packages/design-system/theme.css`.
- Consulte o mock normativo quando houver composicao visual, layout, formularios, tabelas, feedback, navegacao, modais, drawers, toasts ou estados de interface.
- Nao copie HTML/classes Tailwind do mock diretamente para producao sem validar contratos do design system.
- Nao use o visual padrao do Laravel como identidade do SICODE.
- Suporte de tema deve respeitar Sistema, Claro e Escuro via tokens semanticos.
- Toda tela deve prever estados loading, empty, error, success e warning quando aplicaveis.
- Valide hierarquia, contraste, responsividade e acessibilidade.
- Novos padroes repetiveis devem entrar no design system antes de serem copiados.

## Padroes recomendados

- Interfaces operacionais devem ser densas, claras e focadas em tarefa.
- Use hierarquia tipografica contida, espaçamento consistente e estados de interacao visiveis.
- Use icones apenas quando melhorarem reconhecimento de acao.
- Prefira componentes reutilizaveis a repeticao visual.

## Padroes proibidos

- Inventar identidade visual por tela.
- Cores arbitrarias repetidas.
- Gradientes/decoracoes sem funcao operacional.
- Texto que explique obviedades da interface em vez de resolver UX.
- Estados inacessiveis por teclado.

## Processo de execucao

1. Consulte `docs/design-system/`.
2. Consulte `docs/design-system/reference/sicode-core-hub-modelo.html` quando a tarefa envolver composicao visual.
3. Use tokens de `packages/design-system/theme.css`.
4. Defina layout, estados e responsividade.
5. Valide contraste e acessibilidade.
6. Reuse ou crie componente com API clara.

## Checklist de conclusao

- Mulish/Tailwind respeitados quando configurados.
- Nenhuma paleta inventada.
- Temas Sistema/Claro/Escuro preservados por tokens.
- Estados de interface cobertos.
- Responsivo e acessivel.

## Quando interromper e propor ADR

- Nova identidade visual.
- Nova paleta oficial.
- Mudanca estrutural de stack frontend.
