# Layouts

## Shell autenticado

Estrutura esperada:

- sidebar para navegacao principal em desktop;
- topbar para busca, contexto e usuario;
- area de conteudo com fundo `background`;
- superficies internas `surface`;
- largura de conteudo adaptada ao tipo de pagina.

Nao use landing page como primeira tela de ferramenta operacional.

## Sidebar

Largura desktop recomendada: 256px. Itens usam label, icone opcional e estado ativo visivel. Grupos devem ser curtos e previsiveis.

## Topbar

Altura recomendada: 56px ou 64px. Deve conter contexto, acoes globais e usuario sem competir com o conteudo.

## Area de conteudo

Padding padrao: `px-6 py-5` em desktop, `px-4 py-4` em telas estreitas.

Largura maxima depende do tipo de fluxo:

- listagem e tabela operacional: pode ocupar a largura disponivel;
- formulario: limite recomendado entre `720px` e `960px`;
- detalhe com metadados laterais: grid responsivo com conteudo principal mais largo;
- autenticacao: painel simples entre `360px` e `440px`.

## Paginas de listagem

Ordem recomendada:

1. PageHeader;
2. filtros/toolbar;
3. tabela/lista;
4. paginacao;
5. estados empty/loading/error.

## Paginas de formulario

Use largura controlada. Agrupe campos por secao. Acoes ficam no final e, em formularios longos, podem usar barra fixa com cuidado.

Padrao estrutural:

1. PageHeader com titulo e contexto curto;
2. secoes nomeadas quando houver mais de um grupo conceitual;
3. campos com label, ajuda opcional e erro no proprio contexto;
4. acoes primarias/secundarias no final;
5. acoes destrutivas visualmente separadas;
6. foco no primeiro erro apos validacao.

## Paginas de detalhe

Use resumo superior, metadados e secoes. Acoes destrutivas ficam separadas das acoes principais.

## Dashboards

Dashboards devem priorizar decisao operacional. Cards de metrica usam `metric`, legenda curta e comparativo quando relevante.

## Autenticacao

Layout simples, centrado, sem marketing. Deve usar Mulish, tokens oficiais, labels reais e feedback acessivel.

## Hub de aplicacoes

O hub deve mostrar aplicacoes autorizadas, contexto quando houver, estado e acao clara. Nao deve expor permissao operacional local.

Cada item deve informar nome da aplicacao, descricao curta operacional, estado visual quando aplicavel e acao principal. O hub nao deve parecer landing page promocional.

## Tabelas densas

Use tabela no desktop para dados tabulares. Cards podem ser usados em mobile, mas nao devem substituir tabela operacional densa em desktop.

Padroes:

- filtros acima da tabela;
- ordenacao visivel;
- truncamento com tooltip acessivel quando necessario;
- colunas fixas somente quando justificadas;
- acoes criticas visiveis ou em menu com alternativa acessivel;
- estado vazio com proxima acao.

Responsividade:

- desktop: manter tabela sem quebrar semantica tabular;
- notebook: permitir scroll horizontal contido quando houver muitas colunas;
- tablet/mobile: priorizar colunas essenciais ou lista resumida com acesso ao detalhe;
- acoes criticas continuam alcançaveis por teclado e toque.
