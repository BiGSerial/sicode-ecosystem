# Contexto obrigatorio para agentes

Todo agente que atuar no SICODE Ecosystem deve considerar estes documentos como leitura obrigatoria antes de alterar identidade, autenticacao, autorizacao, contratos, organizacoes, integracao Legacy ou SICODESK:

- `docs/architecture/core-identity-access-canon.md`
- `docs/architecture/core-identity-domain-model.md`
- `docs/architecture/core-identity-access-physical-model.md`
- `docs/architecture/legacy-core-integration.md`
- `docs/architecture/legacy-to-core-transition-map.md`
- `docs/architecture/core-application-authorization-boundaries.md`
- `docs/decisions/ADR-001-core-identity-authority-and-legacy-transition.md`

Evidencia tecnica Legacy:

- `docs/inventory/legacy/`

O inventario Legacy e evidencia factual do estado existente. Ele nao define arquitetura canonica. O mapa `docs/architecture/legacy-to-core-transition-map.md` e obrigatorio para alteracoes relacionadas a compatibilidade Legacy.

O modelo fisico `docs/architecture/core-identity-access-physical-model.md` e obrigatorio antes de criar migrations do dominio CORE.

## Skills normativas

O sistema normativo de skills esta em `docs/skills/`.

Antes de alterar codigo ou documentacao tecnica, o agente deve consultar `AGENTS.md`, identificar as skills aplicaveis, ler cada skill relevante e seguir sua orientacao operacional. Skills nao substituem ADRs nem arquitetura; elas explicam como implementar dentro das decisoes aprovadas.

O indice das skills esta em `docs/skills/README.md`.

## Design system

A fundacao visual normativa esta em `docs/design-system/`.

Os tokens oficiais iniciais estao em `packages/design-system/theme.css` e devem ser consumidos por CORE, SICODESK e futuras aplicacoes frontend. Tarefas de frontend devem consultar `docs/design-system/README.md` e as skills de `docs/skills/frontend/`.

O mock visual normativo esta em `docs/design-system/reference/sicode-core-hub-modelo.html`. Ele demonstra composicao e comportamento visual, mas nao e codigo de producao. HTML/classes do mock nao devem ser copiados diretamente sem validar contratos do design system e skills aplicaveis.

## Estrutura executavel

O monorepo executavel usa:

- `apps/sicode-core`: Laravel 13, Livewire 4, PHP 8.4, PostgreSQL.
- `apps/sicodesk`: Laravel 13, Livewire 4, PHP 8.4, PostgreSQL.
- `apps/sicode-legacy`: reservado para importacao futura do Legacy real, Laravel 10/Livewire 2.

Use `docs/development/local-execution.md` e o `Makefile` para comandos locais.

## Regras obrigatorias

OBRIGATORIO: o CORE e autoridade canonica de identidade e acesso.

PROIBIDO: usar IDs locais do Legacy como identidade global.

PROIBIDO: acessar banco, Models, migrations ou classes internas do CORE a partir de aplicacoes consumidoras.

OBRIGATORIO: permissao operacional permanece na aplicacao proprietaria do dominio.

OBRIGATORIO: Legacy ES e Legacy SP sao contextos de dados e autorizacao separados.

OBRIGATORIO: SICODESK usa identidade CORE para usuarios humanos do ecossistema e mantem permissoes operacionais locais.

PROIBIDO: criar autenticacao paralela sem ADR.

PROIBIDO: criar tabelas conceitualmente sobrepostas sem analisar o modelo canonico.

OBRIGATORIO: interromper implementacao e registrar conflito quando codigo, documentacao e canon divergirem.

OBRIGATORIO: propor ADR quando uma mudanca alterar decisao fundadora.

PROIBIDO: refazer o inventario Legacy sem motivo tecnico documentado. Quando houver nova evidencia, registre a divergencia e atualize o mapa de transicao ou proponha ADR conforme o impacto.
