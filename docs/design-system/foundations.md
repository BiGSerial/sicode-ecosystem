# Foundations

## Origem e decisao

A referencia visual normativa esta em `docs/design-system/reference/sicode-core-hub-modelo.html`. A fundacao abaixo formaliza os contratos em tokens consumiveis, sem tratar o mock como codigo de producao.

Tokens consumiveis: `packages/design-system/theme.css`.

## Cores

| Token | Valor | Finalidade | Uso permitido | Uso proibido |
| --- | --- | --- | --- | --- |
| `background` | `#f3f5fa` | Fundo geral da aplicacao | Shells e areas externas | Texto direto sem superficie |
| `surface` | `#ffffff` | Superficie principal | Cards, formularios, tabelas | Hero/ornamento |
| `surface-muted` | `#e9edfc` | Fundo secundario | Headers, filtros, linhas sutis | Botao primario |
| `surface-elevated` | `#ffffff` | Superficie elevada | Modal, dropdown, popover | Painel aninhado sem necessidade |
| `text` | `#1b2547` | Texto principal | Conteudo e labels fortes | Texto sobre cor escura |
| `text-muted` | `#4e5878` | Texto secundario | Ajuda, metadados | Labels criticos |
| `text-subtle` | `#7c86a6` | Texto de baixa enfase | Captions, placeholders auxiliares | Texto pequeno essencial |
| `text-inverse` | `#ffffff` | Texto em fundo escuro/solido | Botoes primarios, badges fortes | Superficies claras |
| `text-disabled` | `#9aa2bc` | Texto desabilitado | Controles inativos | Conteudo ativo |
| `border` | `#dce1ee` | Borda padrao | Inputs, cards, tabelas | Separador de foco |
| `border-strong` | `#b8c0d9` | Borda enfatizada | Divisorias fortes | Ornamentacao recorrente |
| `border-focus` | `#263cc8` | Foco | Focus visible | Estado decorativo |
| `primary` | `#263cc8` | Acao principal | CTA e foco de fluxo | Status de negocio |
| `primary-hover` | `#1d2fa0` | Hover primario | Hover/pressed leve | Texto |
| `primary-active` | `#162476` | Pressed primario | Active/selected | Fundo extenso |
| `primary-subtle` | `#e9edfc` | Fundo primario sutil | Selected suave | Texto branco |
| `secondary` | `#212e5e` | Acao secundaria ou enfase institucional | Acoes complementares | Sucesso de dominio |
| `success` | `#0e7a3d` | Intencao positiva | Status, alertas, feedback | Botao primario global |
| `warning` | `#925b00` | Atencao | Alertas e status pendente | Erro destrutivo |
| `danger` | `#b3261e` | Negativo/destrutivo | Erros, bloqueios, destruicao | Alerta informativo |
| `info` | `#1d5fb8` | Informativo | Avisos neutros e guidance | CTA principal |
| `neutral` | `#475569` | Estado neutro | Draft, arquivado, metadados | Erro/sucesso |

Cada intencao possui variantes `*-subtle`, `*-foreground` e `*-subtle-foreground` no CSS.

## Contraste

Combinações obrigatorias:

- `text` sobre `surface` ou `background`: AA para texto normal.
- `text-muted` sobre `surface`: AA para texto normal.
- `primary-foreground` sobre `primary`: AA.
- `success-foreground` sobre `success`: AA.
- `warning-foreground` sobre `warning`: AA.
- `danger-foreground` sobre `danger`: AA.
- `info-foreground` sobre `info`: AA.
- Em superficies sutis, use sempre `*-subtle-foreground`.

Nao use branco automaticamente sobre cores sutis.

Combinações documentadas:

| Contexto | Texto | Fundo | Regra |
| --- | --- | --- | --- |
| Conteudo principal | `text` | `surface` ou `background` | Permitido para qualquer texto |
| Texto secundario | `text-muted` | `surface` | Permitido para ajuda/metadados |
| Texto sutil | `text-subtle` | `surface` | Somente conteudo nao essencial ou grande |
| Texto desabilitado | `text-disabled` | `surface` | Somente controle inativo |
| Link | `primary` | `surface` | Deve ter underline ou indicador no hover/focus |
| Controle primario | `primary-foreground` | `primary` | Permitido |
| Controle secundario | `secondary-foreground` | `secondary` | Permitido |
| Sucesso solido | `success-foreground` | `success` | Permitido |
| Atencao solida | `warning-foreground` | `warning` | Permitido |
| Perigo solido | `danger-foreground` | `danger` | Permitido |
| Informativo solido | `info-foreground` | `info` | Permitido |
| Estados sutis | `*-subtle-foreground` | `*-subtle` | Obrigatorio |

Combinações proibidas:

- `text-inverse` sobre `surface`, `surface-muted` ou qualquer token `*-subtle`;
- `text-subtle` para erros, labels obrigatorias ou texto pequeno essencial;
- cores solidas de status como fundo extenso de pagina;
- texto branco sobre warning claro ou superficies sutis futuras.

## Tipografia

Fonte: Mulish.

Fallback: `ui-sans-serif, system-ui, sans-serif`.

Pesos permitidos:

- 400 regular;
- 500 medium;
- 600 semibold;
- 700 bold somente para metricas ou enfase rara.

Escala semantica:

| Papel | Tamanho | Line-height | Peso |
| --- | ---: | ---: | ---: |
| display | 32px | 40px | 700 |
| heading-1 | 28px | 36px | 700 |
| heading-2 | 22px | 30px | 600 |
| heading-3 | 18px | 26px | 600 |
| body | 14px | 22px | 400 |
| body-small | 13px | 20px | 400 |
| label | 13px | 18px | 600 |
| caption | 12px | 16px | 500 |
| metric | 30px | 36px | 700 |
| table | 13px | 18px | 400 |

Letter spacing deve permanecer `0`, salvo texto uppercase curto em captions (`0.02em` maximo).

## Espacamento

Use a escala Tailwind base com estes papeis. Os mesmos papeis existem como tokens em `packages/design-system/theme.css`.

- pagina desktop: `px-6 py-5`;
- pagina compacta: `px-4 py-4`;
- secao: `gap-4` ou `gap-6`;
- formulario: `gap-4`;
- tabela compacta: `px-3 py-2`;
- card: `p-4` ou `p-5`;
- modal: `p-6`;
- toolbar: `gap-2`.
- sidebar desktop: `16rem`;
- topbar: `3.5rem` ou `4rem` quando houver muitos controles globais.

Densidades:

| Densidade | Uso | Padding base |
| --- | --- | --- |
| Confortavel | formularios, detalhe, cards de resumo | `p-4`, `gap-4` |
| Compacta | tabelas, listas operacionais, filtros densos | `px-3 py-1.5` ou `px-3 py-2` |

Valores recorrentes fora da escala devem virar token ou ser removidos.

## Radius, bordas e sombras

- Radius padrao: `md` (`0.375rem`).
- Cards e inputs: `md`.
- Modais e drawers: `lg`.
- Badges: `sm`.
- Evite radius acima de `xl` em superficies operacionais.
- Borda padrao: 1px `border`.
- Foco: `border-focus` + `--sicode-focus-ring`.
- Sombra `sicode-sm`: card sutil.
- Sombra `sicode-md`: dropdown/modal pequeno.
- Sombra `sicode-lg`: modal/drawer.

Sombras nao substituem borda em interfaces densas.

## Estados interativos

Todo componente interativo deve especificar:

- default;
- hover;
- active;
- focus-visible;
- disabled;
- loading;
- selected;
- invalid;
- read-only quando aplicavel.

Estado nao pode depender apenas de uma mudanca sutil de cor.

## Intencoes de status

O design system define intencoes visuais, nao estados de dominio:

| Intencao | Uso | Exemplos de mapeamento |
| --- | --- | --- |
| `neutral` | estado sem julgamento operacional | draft, archived |
| `info` | andamento ou informacao | processing, synced |
| `success` | positivo/concluido | active, completed |
| `warning` | exige atencao | pending, suspended |
| `danger` | negativo, bloqueado ou destrutivo | blocked, failed, revoked |
| `disabled` | indisponivel/inativo | disabled, unavailable |

A aplicacao decide o significado operacional. O nucleo visual nao deve ganhar token para cada status de negocio.

## Temas

O SICODE Ecosystem possui suporte fundacional a tres modos:

- Sistema: `html[data-theme-mode="system"]`, seguindo `prefers-color-scheme`;
- Claro: valores padrao de `:root` ou preferencia explicita clara;
- Escuro: `html[data-theme="dark"]`.

Componentes devem consumir tokens semanticos. E proibido criar componentes paralelos para claro/escuro quando a diferenca puder ser resolvida por token. Tambem e proibido espalhar hexadecimais ou extensas variantes `dark:*` quando existir token oficial correspondente.

Novos tokens ou alteracoes na estrategia de temas devem ser formalizados neste design system antes de uso em producao.
