# ADR-001: CORE como autoridade canonica de identidade e acesso

Status: Aceita

Data: 2026-07-13

## Contexto

O SICODE Ecosystem sera composto inicialmente por SICODE CORE, SICODE Legacy ES, SICODE Legacy SP e SICODESK. Futuramente incluira o SICODE 2.0.

O Legacy cresceu com regras distribuidas de identidade, empresa, contrato e autorizacao. Foram identificados, como requisitos de compatibilidade, ao menos tres modelos coexistentes de vinculo empresarial:

- `users.company_id`
- `company_user`
- `employees -> contracts`

Esses modelos nao sao canonicos para o novo ecossistema. Eles representam fontes de dados, regras historicas e restricoes de migracao.

No momento desta decisao, o repositorio nao possuia codigo, migrations, Models, ADRs anteriores ou inventario documental local. Esta decisao estabelece a primeira base normativa do projeto.

Nota posterior: o inventario factual Legacy foi adicionado em `docs/inventory/legacy/legacy-identity-company-contract-authorization-inventory.md`, e o mapa normativo de transicao foi formalizado em `docs/architecture/legacy-to-core-transition-map.md`. Esses documentos refinam a estrategia de transicao sem alterar a decisao fundadora deste ADR.

## Decisao

O SICODE CORE e a autoridade canonica de identidade humana, autenticacao e direito de entrada nas aplicacoes do ecossistema.

Aplicacoes consumidoras nao sao proprietarias da identidade global. IDs locais existentes no Legacy, SICODESK ou futuras aplicacoes sao referencias externas, nao identidades globais.

Toda identidade humana global deve possuir um identificador canonico estavel no CORE. O identificador RECOMENDADO e UUID.

O CORE decide:

- quem e o usuario;
- se a identidade esta ativa, bloqueada ou encerrada;
- quais organizacoes possuem vinculo com o usuario;
- quais contratos institucionais estao ativos;
- quais aplicacoes, clientes e contextos operacionais podem ser acessados;
- se o usuario tem direito de entrada em uma aplicacao.

Cada aplicacao decide suas autorizacoes internas:

- papeis operacionais;
- permissoes de dominio;
- regras de workflow;
- autorizacao sobre entidades especificas;
- efeitos de negocio internos.

Exemplo correto:

- CORE: usuario pode acessar `SICODE Legacy SP`.
- SICODE: usuario pode aprovar viabilidade.

Exemplo proibido:

- CORE armazenar permissao operacional `viability.approve`.

## Estrategia de transicao Legacy

O Legacy deve receber uma camada removivel de integracao com o CORE. O nome canonico proposto e `LegacyCoreIdentityBridge`.

Essa camada deve permitir:

1. reconhecer usuarios Legacy ja vinculados a uma identidade CORE;
2. autenticar pelo CORE quando a identidade canonica ja existir e o runtime suportar isso;
3. migrar usuarios Legacy progressivamente;
4. registrar vinculo entre identidade CORE e identidade local Legacy;
5. interromper a dependencia de senha Legacy apos migracao controlada;
6. preservar IDs e foreign keys historicas no banco Legacy.

O Legacy ES e o Legacy SP sao contextos de dados distintos. Mesmo que usem a mesma base de codigo, devem operar com bancos, storage, configuracoes, clientes de autenticacao e autorizacoes independentes.

## Consequencias

- O CORE nao pode expor seu banco como contrato de integracao.
- Aplicacoes devem integrar por protocolos e contratos documentados, nao por Models, migrations, classes PHP ou tabelas internas do CORE.
- O CORE deve ser preparado para OAuth 2.0, OpenID Connect, Authorization Code Flow com PKCE, tokens assinados, issuer, audience, subject estavel, JWKS e rotacao de chaves.
- Implementacoes iniciais podem ser faseadas, mas nao podem contradizer esses contratos.
- O SICODE 2.0 deve consumir o CORE diretamente e nao herdar estruturas internas do Legacy.

## Alternativas rejeitadas

### Usar IDs Legacy como identidade global

Rejeitada. IDs podem colidir entre ES e SP e preservam uma modelagem local como se fosse identidade canonica.

### Fazer aplicacoes acessarem o banco CORE diretamente

Rejeitada. Isso acopla tecnologias, quebra independencia entre Laravel, Node.js, Java, Go e outras stacks, e transforma schema interno em API publica acidental.

### Centralizar todas as permissoes no CORE

Rejeitada. Permissoes operacionais pertencem ao dominio da aplicacao. O CORE centraliza direito de entrada, nao regras internas de negocio.

### Reconstruir o banco Legacy durante a transicao

Rejeitada. O historico do Legacy deve ser preservado. A transicao deve acontecer por ponte de identidade e projecoes locais controladas.

## Excecoes

Qualquer mudanca nas fronteiras entre CORE e aplicacoes, no modelo de identidade canonica, no protocolo de autenticacao ou na estrategia de transicao Legacy exige novo ADR.
