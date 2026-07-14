# Skills normativas do SICODE Ecosystem

Skills sao instrucoes operacionais reutilizaveis para agentes humanos e de IA. Elas explicam como implementar dentro das decisoes aprovadas.

Skills nao substituem ADRs, canon arquitetural ou modelos aprovados.

## Precedencia documental

```text
ADR / canon arquitetural
        >
modelo arquitetural aprovado
        >
skill normativa
        >
padrao local de implementacao
        >
preferencia do agente/framework
```

Quando uma skill conflitar com ADR ou canon, o agente deve interromper a tarefa e propor ADR ou correcao documental. Uma skill nao pode alterar arquitetura.

## Arvore de skills

```text
docs/skills/
├── architecture/
│   ├── application-boundaries.md
│   └── domain-modeling.md
├── backend/
│   ├── authorization.md
│   ├── laravel-development.md
│   └── validation.md
├── database/
│   ├── database-design.md
│   └── laravel-migrations.md
├── frontend/
│   ├── accessibility.md
│   ├── blade-components.md
│   ├── design-frontend.md
│   ├── livewire-development.md
│   └── tailwind-design-system.md
├── security/
│   ├── cryptography.md
│   ├── secrets-management.md
│   ├── secure-development.md
│   └── secure-logging.md
├── testing/
│   ├── database-testing.md
│   └── testing-strategy.md
└── workflow/
    ├── architecture-change.md
    ├── code-review.md
    └── task-execution.md
```

## Quando consultar

- Tarefas de modelagem ou novo conceito: `architecture/domain-modeling.md`.
- Tarefas entre CORE, Legacy, SICODESK ou SICODE 2.0: `architecture/application-boundaries.md`.
- Persistencia e migrations: `database/database-design.md` e `database/laravel-migrations.md`.
- Implementacao Laravel: skills de `backend/`.
- Telas, UI, Blade, Tailwind, Livewire e acessibilidade: skills de `frontend/`.
- Seguranca, criptografia, secrets e logs: skills de `security/`.
- Testes: skills de `testing/`.
- Execucao de qualquer tarefa, review ou mudanca arquitetural: skills de `workflow/`.

