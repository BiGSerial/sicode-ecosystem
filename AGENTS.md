# SICODE Ecosystem - instrucoes para agentes

Antes de alterar qualquer area de identidade, autenticacao, autorizacao, organizacoes, contratos, integracao Legacy, SICODESK ou SICODE 2.0, leia:

- `docs/agent/project-context.md`
- `docs/architecture/core-identity-access-canon.md`
- `docs/architecture/core-identity-access-physical-model.md` antes de criar migrations CORE
- `docs/architecture/legacy-to-core-transition-map.md` para qualquer compatibilidade Legacy
- `docs/skills/README.md`
- `docs/development/local-execution.md` para execucao local e topologia do monorepo
- `docs/design-system/README.md` antes de criar ou alterar frontend
- `docs/design-system/reference/sicode-core-hub-modelo.html` quando alterar composicao visual, layout, formularios, tabelas, feedback, navegacao, modais, drawers, toasts ou estados de interface

O canon arquitetural e normativo. Agentes nao podem reinterpretar suas regras silenciosamente.

Topologia executavel:

- `apps/sicode-core`: Laravel 13, Livewire 4, PHP 8.4, PostgreSQL.
- `apps/sicodesk`: Laravel 13, Livewire 4, PHP 8.4, PostgreSQL.
- `apps/sicode-legacy`: reservado para o Legacy real; Laravel 10, Livewire 2 e PHP compativel com o codigo importado.

Nao misture dependencias entre apps. Nao aplique padroes Laravel 13/Livewire 4 ao Legacy.

O inventario em `docs/inventory/legacy/` e evidencia tecnica do Legacy, nao arquitetura canonica. Nao refaca o inventario sem motivo tecnico documentado.

Antes de alterar codigo ou documentacao tecnica, identifique as skills aplicaveis em `docs/skills/`, leia cada skill relevante e liste as skills utilizadas na saida final. Skills obrigatorias por area:

- arquitetura: `docs/skills/architecture/domain-modeling.md`, `docs/skills/architecture/application-boundaries.md`
- banco/migrations: `docs/skills/database/database-design.md`, `docs/skills/database/laravel-migrations.md`
- backend Laravel: `docs/skills/backend/laravel-development.md`, `docs/skills/backend/authorization.md`, `docs/skills/backend/validation.md`
- frontend: `docs/skills/frontend/design-frontend.md`, `docs/skills/frontend/tailwind-design-system.md`, `docs/skills/frontend/blade-components.md`, `docs/skills/frontend/livewire-development.md`, `docs/skills/frontend/accessibility.md`
- seguranca: `docs/skills/security/secure-development.md`, `docs/skills/security/cryptography.md`, `docs/skills/security/secrets-management.md`, `docs/skills/security/secure-logging.md`
- testes: `docs/skills/testing/testing-strategy.md`, `docs/skills/testing/database-testing.md`
- workflow: `docs/skills/workflow/task-execution.md`, `docs/skills/workflow/code-review.md`, `docs/skills/workflow/architecture-change.md`
- gestao de roadmap/Project/issues: `docs/skills/workflow/project-management.md`

Nao crie padrao local concorrente quando existir skill normativa. Se uma skill conflitar com canon, ADR ou modelo aprovado, interrompa a implementacao e registre o conflito.

Se uma tarefa conflitar com o canon, interrompa a implementacao, registre a divergencia e proponha ADR.
