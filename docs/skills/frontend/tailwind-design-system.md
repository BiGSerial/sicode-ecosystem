# Tailwind Design System

## Objetivo

Definir uso normativo do Tailwind sem criar estilos arbitrarios ou duplicados.

## Quando esta skill e obrigatoria

Use ao criar classes Tailwind, theme, tokens, utilities ou responsividade.

## Fontes normativas

- `docs/design-system/foundations.md`
- `docs/design-system/reference/sicode-core-hub-modelo.html`
- `packages/design-system/theme.css`
- `docs/skills/frontend/design-frontend.md`

## Regras obrigatorias

- Tokens de cor, fonte, escala tipografica, spacing, radius e sombra devem consumir `packages/design-system/theme.css`.
- Valores arbitrarios so podem ser usados uma vez e com justificativa; se repetirem, viram token.
- Temas Sistema, Claro e Escuro ja possuem suporte fundacional em `packages/design-system/theme.css`.
- Componentes devem usar tokens semanticos para temas; nao espalhe hexadecimais ou extensas variantes `dark:*` quando existir token correspondente.
- Classes devem refletir composicao de componente, nao improviso por tela.

## Padroes recomendados

- Use Tailwind v4 com tokens expostos por `@theme` no arquivo oficial.
- Use classes semanticas geradas pelos tokens quando existirem, como `text-body`, `text-label`, `p-card-padding` e cores `text-*`/`bg-*` oficiais.
- Mantenha densidade operacional consistente.
- Responsividade deve ser definida por comportamento real, nao por breakpoints aleatorios.

## Padroes proibidos

- Repetir hex/classes arbitrarias.
- Copiar classes Tailwind do mock normativo diretamente para producao.
- Criar versoes independentes de componente para claro/escuro quando tokens resolvem a diferenca.
- Criar utilities globais sem caso recorrente.
- Usar Tailwind para mascarar componente mal modelado.

## Processo de execucao

1. Importe `packages/design-system/theme.css` no CSS da aplicacao.
2. Monte componente com classes previsiveis.
3. Extraia padrao recorrente.
4. Teste em mobile e desktop.

## Checklist de conclusao

- Sem valores magicos recorrentes.
- Tokens oficiais usados.
- Layout responsivo validado.

## Quando interromper e propor ADR

- Mudanca de design system.
- Novo tema oficial.
- Mudanca na estrategia de temas ou novos tokens de tema.
