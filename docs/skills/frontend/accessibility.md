# Accessibility

## Objetivo

Definir requisitos minimos de acessibilidade para interfaces SICODE.

## Quando esta skill e obrigatoria

Use em toda criacao ou alteracao de UI.

## Fontes normativas

- `docs/design-system/accessibility.md`
- `docs/design-system/foundations.md`
- `docs/design-system/reference/sicode-core-hub-modelo.html`
- `docs/skills/frontend/design-frontend.md`

## Regras obrigatorias

- Use HTML semantico antes de ARIA.
- Inputs precisam de label associado.
- Toda acao essencial deve funcionar por teclado.
- Estados de foco devem ser visiveis.
- Contraste deve seguir as combinacoes documentadas no design system.
- Contraste deve ser preservado nos temas Sistema, Claro e Escuro.
- Mensagens de erro devem estar associadas ao campo ou regiao afetada.

## Padroes recomendados

- Modais devem gerenciar foco e fechamento por teclado.
- Dropdowns devem ter estado, foco e fechamento previsiveis.
- Tabelas devem usar headers semanticos e texto claro.
- Feedback de sucesso/erro deve ser perceptivel sem depender apenas de cor.

## Padroes proibidos

- `div` clicavel sem semantica.
- Remover outline sem substituto.
- ARIA para compensar HTML incorreto.
- Informacao transmitida apenas por cor.

## Processo de execucao

1. Escolha elemento semantico.
2. Defina labels e relacoes.
3. Teste teclado.
4. Valide contraste e feedback.

## Checklist de conclusao

- Navegacao por teclado funciona.
- Labels existem.
- Foco visivel.
- Erros acessiveis.

## Quando interromper e propor ADR

- Novo componente complexo sem padrao de acessibilidade definido.
