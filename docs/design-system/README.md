# SICODE Design System

Este diretorio e a fonte normativa da fundacao visual do SICODE Ecosystem.

## Status

A referencia visual normativa esta em `docs/design-system/reference/sicode-core-hub-modelo.html`. O arquivo versionado de origem mantido no repositorio e `docs/design-system/reference/sicode-core-hub-modelo-normativo-v3-temas.html`.

O mock e referencia visual de composicao, densidade, estados e comportamento. Ele nao e codigo de producao e suas classes/HTML nao devem ser copiadas diretamente sem validar os contratos deste design system e as skills aplicaveis.

As telas `welcome.blade.php` geradas pelo Laravel existem, mas nao sao identidade visual SICODE.

Os tokens versionados em `packages/design-system/theme.css` sao a fundacao oficial inicial para interfaces corporativas do SICODE.

## Documentos

- `foundations.md`: tokens, cores, tipografia, spacing, radius, sombras e estados.
- `components.md`: especificacao visual inicial dos componentes fundamentais.
- `layouts.md`: shells, paginas, tabelas, formularios e hub.
- `accessibility.md`: contraste, foco, teclado e padroes acessiveis.
- `reference/sicode-core-hub-modelo.html`: mock visual normativo, sem papel de codigo de producao.

## Consumo tecnico

Aplicacoes Tailwind v4 devem importar:

```css
@import '../../../../packages/design-system/theme.css';
```

O caminho pode variar conforme a localizacao do arquivo CSS consumidor.

O arquivo `packages/design-system/theme.css` e a fonte unica para tokens consumiveis por maquina e por CSS:

- variaveis `--sicode-*` para CSS comum e componentes;
- bloco `@theme` para expor tokens ao Tailwind v4;
- cores, tipografia, spacing, radius, sombras e foco.

Nao crie JSON paralelo de tokens enquanto nao houver consumidor real.

## Regras

- Use tokens semanticos, nao classes arbitrarias repetidas.
- Nao crie nova paleta por tela ou por aplicacao.
- SICODESK e futuras aplicacoes devem reutilizar a mesma fundacao visual.
- Os temas suportados sao Sistema, Claro e Escuro.
- Componentes devem consumir tokens semanticos; nao crie versoes separadas por tema quando tokens resolvem a diferenca.
- Nao espalhe valores de cor ou extensas variacoes `dark:*` pelos componentes quando houver token semantico.
- Status de dominio devem mapear para intencoes visuais, nao virar tokens globais.

## Temas

O suporte fundacional de temas reside em `packages/design-system/theme.css`.

- Claro: valores padrao em `:root` ou `html[data-theme="light"]`.
- Escuro: `html[data-theme="dark"]`.
- Sistema: `html[data-theme-mode="system"]`, seguindo `prefers-color-scheme`.

A preferencia explicita do usuario pode sobrescrever a preferencia do sistema. A implementacao da persistencia da preferencia pertence a aplicacao, mas deve apenas alternar atributos de tema e continuar consumindo tokens semanticos.

## Catalogo visual futuro

Uma pagina interna de referencia visual pode ser criada depois como catalogo de componentes ou pattern library. Ela deve ser restrita a ambiente de desenvolvimento ou a perfil tecnico autorizado, nao deve ser rota publica e nao deve introduzir Storybook ou ferramenta externa sem decisao arquitetural.
