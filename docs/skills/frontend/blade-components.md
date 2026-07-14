# Blade Components

## Objetivo

Definir quando e como criar componentes Blade reutilizaveis.

## Quando esta skill e obrigatoria

Use ao criar ou alterar componentes Blade, layouts, partials e fragmentos visuais repetidos.

## Fontes normativas

- `docs/design-system/components.md`
- `docs/design-system/foundations.md`
- `docs/design-system/reference/sicode-core-hub-modelo.html`
- `docs/skills/frontend/design-frontend.md`
- `docs/skills/frontend/accessibility.md`

## Regras obrigatorias

- Crie componente quando houver repeticao real, contrato visual claro ou estado compartilhado.
- Separe componente visual de componente de dominio.
- Props devem ser explicitas e pequenas.
- Variants devem ser finitas e documentadas no proprio componente.
- Slots devem ser usados para conteudo variavel, nao para esconder API indefinida.
- Variants visuais devem corresponder as variantes documentadas em `docs/design-system/components.md`.
- Componentes devem consumir tokens semanticos e preservar temas Sistema/Claro/Escuro.

## Padroes recomendados

- Componentes devem expor estados: disabled, loading, active, error quando aplicavel.
- Preserve acessibilidade por padrao.
- Nomeie pelo papel visual ou de dominio, nao pela tela atual.

## Padroes proibidos

- Copiar blocos visuais entre telas.
- Copiar HTML/classes do mock normativo diretamente para producao.
- Props booleanas demais criando matriz imprevisivel.
- Componente que acessa regra de negocio indevida.

## Processo de execucao

1. Identifique repeticao ou contrato.
2. Defina props, slots e variants.
3. Inclua estados e acessibilidade.
4. Substitua duplicacoes relevantes.

## Checklist de conclusao

- API clara.
- Estados cobertos.
- Sem duplicacao visual relevante.
- Acessivel.

## Quando interromper e propor ADR

- Novo padrao visual oficial.
- Componente altera fluxo de autorizacao ou dominio.
