# Accessibility

## Padrao minimo

Interfaces SICODE devem mirar WCAG 2.2 AA para texto e controles.

## Contraste

- Texto principal deve usar `text` sobre `surface` ou `background`.
- Texto secundario usa `text-muted`; `text-subtle` nao deve ser usado para conteudo essencial pequeno.
- Texto em tokens solidos usa o respectivo `*-foreground`.
- Texto em tokens sutis usa o respectivo `*-subtle-foreground`.
- Links usam `primary` e devem ter indicador adicional no hover/focus.
- Bordas de controles usam `border`; foco usa `border-focus` e ring perceptivel.

Pares principais validados devem permanecer acima de WCAG AA para texto normal:

- `text`/`surface`;
- `text-muted`/`surface`;
- `primary-foreground`/`primary`;
- `success-foreground`/`success`;
- `warning-foreground`/`warning`;
- `danger-foreground`/`danger`;
- `info-foreground`/`info`;
- `*-subtle-foreground`/`*-subtle`.

## Foco

Todo controle interativo deve ter `focus-visible` perceptivel usando `border-focus` e ring equivalente a `--sicode-focus-ring`.

Nao remova outline sem substituto.

## Teclado

Todos os fluxos essenciais devem funcionar por teclado. Modais e dropdowns devem tratar Escape, ordem de foco e retorno de foco.

## Formularios

- Label associado e obrigatorio.
- Placeholder nao substitui label.
- Erro deve indicar campo e mensagem.
- Ao submeter com erro, foco deve ir ao primeiro erro ou resumo acessivel.
- Campo obrigatorio deve ser comunicado por texto ou atributo semantico, nao somente por asterisco colorido.
- Estado read-only deve ser distinguivel de disabled e manter texto legivel.

## Estados

Erro, sucesso, warning e selecionado nao podem depender apenas de cor. Use texto, icone, borda, peso ou outro indicador adicional.

Status de negocio devem ser apresentados como intencao visual mais label textual. Exemplo: um usuario bloqueado usa intencao `danger` e texto "Bloqueado"; a cor sozinha nao e suficiente.

## ARIA

Use HTML semantico primeiro. ARIA deve complementar, nao corrigir estrutura incorreta.

## Motion

Animacoes devem ser curtas e nao essenciais. Respeite preferencias de reducao de movimento quando houver animacao relevante.
