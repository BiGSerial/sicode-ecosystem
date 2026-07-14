# Components

Este documento especifica comportamento visual inicial. Nao cria componentes Blade.

## Button

Variantes: `primary`, `secondary`, `neutral`, `danger`, `ghost`.

Tamanhos: `sm`, `md`, `lg`.

Estados: default, hover, active, focus-visible, disabled, loading.

Uso correto: uma acao primaria por area decisoria. Uso incorreto: usar `danger` para alerta nao destrutivo.

Regras:

- `primary`: acao principal do fluxo, usando `primary`, `primary-hover`, `primary-active` e `primary-foreground`;
- `secondary`: acao complementar forte, usando `secondary` e `secondary-foreground`;
- `neutral`: acao comum de baixa criticidade, usando `surface`, `border` e `text`;
- `danger`: acao destrutiva confirmada, usando `danger` e `danger-foreground`;
- `ghost`: acao terciaria em toolbar, com hover visivel e foco obrigatorio.

Loading deve manter largura estavel e indicar progresso com texto ou icone acessivel.

## IconButton

Use apenas com icone reconhecivel e label acessivel. Tamanho minimo de alvo: 36px. Tooltip recomendado para acoes nao obvias.

## Field, Input, Textarea, Select

Labels sao obrigatorias. Placeholder nao substitui label. Erros usam `danger`, texto descritivo e associacao ao campo.

Input padrao: superficie `surface`, borda `border`, foco `border-focus` + ring.

Larguras:

- campo curto: identificadores, UF, numero pequeno;
- campo medio: email, telefone, documento;
- campo longo: nome, endereco, descricao curta;
- largura total: textarea, busca e filtros quando o contexto pedir.

Estados: default, hover quando aplicavel, focus-visible, invalid, disabled, read-only e loading para selects remotos. Campos obrigatorios devem ser indicados no label ou texto adjacente, nao apenas por cor.

## Checkbox, Radio, Switch

Devem funcionar por teclado, ter label associado e estado checked/indeterminate quando aplicavel. Switch e apenas para configuracao booleana imediata.

## FormError

Use `danger-subtle` com texto `danger-subtle-foreground`. Deve indicar campo ou regiao afetada.

## Alert

Intencoes: info, success, warning, danger, neutral. Alertas nao devem ser dispensaveis se comunicam erro bloqueante.

Alertas devem ter titulo curto quando bloqueiam acao, mensagem objetiva e indicador que nao dependa apenas da cor.

## Badge e StatusBadge

Badge comunica metadado curto. StatusBadge usa intencoes visuais:

- `neutral`: draft, archived;
- `info`: processing, synced;
- `success`: active, completed;
- `warning`: pending, suspended;
- `danger`: blocked, failed, revoked;
- `disabled`: disabled, unavailable.

Status especifico de dominio nao entra no design system.

## Card

Use para item repetido ou container de formulario. Nao aninhe cards sem necessidade. Card padrao usa `surface`, borda `border`, radius `md`, sombra `sicode-sm` opcional.

## Table

Padrao para dados operacionais no desktop. Header usa `surface-muted`, texto `caption`, bordas horizontais. Celulas compactas usam `px-3 py-2`. Acoes devem ser acessiveis por teclado.

Requisitos:

- header com `scope` ou estrutura semantica equivalente;
- ordenacao visivel por texto/icone e estado atual acessivel;
- filtros acima da tabela, nao escondidos quando forem essenciais;
- selecao por checkbox com label acessivel;
- acoes de linha visiveis ou em menu acessivel;
- truncamento com titulo/tooltip acessivel quando houver perda de contexto;
- loading sem layout shift;
- empty state com proxima acao quando existir;
- no desktop, nao substituir dados tabulares por cards sem justificativa.

## Pagination

Inclua estado atual, anterior/proximo e informacao de quantidade quando disponivel. Nao dependa apenas de icone sem label.

## Tabs

Use para alternar vistas do mesmo contexto. Estado ativo deve usar texto forte, borda/fundo e `aria-current` ou semantica equivalente.

## Dropdown

Superficie `surface-elevated`, borda `border`, sombra `sicode-md`. Deve fechar com Escape e clique externo, e preservar foco.

## Modal

Use para decisao focada. Requer foco inicial, trap de foco, Escape, overlay, titulo e acao secundaria clara. Acoes destrutivas exigem texto especifico.

O foco inicial deve ir para o titulo ou primeiro campo relevante. Ao fechar, o foco retorna ao disparador. Modal sem titulo acessivel e proibido.

## Drawer

Use para detalhe lateral ou formulario auxiliar. Nao substitui pagina quando o fluxo e longo.

## Tooltip

Somente para ajuda curta. Nao coloque informacao essencial apenas em tooltip.

## Breadcrumb

Use em fluxos profundos. Ultimo item representa pagina atual e nao precisa ser link.

## PageHeader

Contem titulo, descricao curta opcional, breadcrumbs e acoes primarias/secundarias. Evite hero promocional em sistemas operacionais.

## EmptyState

Deve explicar estado e oferecer proxima acao quando houver. Use tom neutro, sem ilustracao obrigatoria.

## LoadingState e Skeleton

Use skeleton quando a estrutura e previsivel. Use loading textual apenas em acoes curtas. Evite layout shift.

## Toast

Use para feedback transitorio nao bloqueante. Erros bloqueantes devem aparecer perto do contexto afetado.

## Form actions

Formularios devem separar acao primaria, secundaria e destrutiva. Em erro de submissao, mova foco para o primeiro erro ou para um resumo acessivel. Em envio, use estado loading no botao principal e preserve os dados digitados.
