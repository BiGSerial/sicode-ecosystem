# SICODE Ecosystem

Repositório de integração, infraestrutura e orquestração do SICODE.
Desde 2026-07-23, CORE e Legacy vivem em repositórios independentes; este
repositório os consome como diretórios irmãos.

```
sicode-ecosystem/     (este repo)
├── compose.yaml       # orquestração local: CORE + Legacy (repos irmãos) + SICODESK (embutido) + infra
├── infra/              # Caddy, Postgres, runtime PHP compartilhado (SICODESK)
├── docs/                # ADRs, padrões do HUB, padrão Redis, matriz de compatibilidade
├── scripts/e2e/          # E2E CORE ↔ Legacy
└── .github/workflows/     # CI de integração (sp-clean-ci.yml)

sicode-core/           (repositório irmão — /home/will/code/sicode-core)
sicode-legacy/         (repositório irmão — /home/will/code/sicode-legacy)
```

Ver `docs/inventory/repository-split-ownership.md` para o mapa completo
de ownership da separação e `docs/architecture/component-version-compatibility.md`
para a matriz de versões/compatibilidade entre os três componentes.

## Uso local

Clone os repositórios irmãos ao lado deste:

```bash
git clone <sicode-core>   ../sicode-core
git clone <sicode-legacy> ../sicode-legacy
```

Depois:

```bash
make build
make up
make health
```

`compose.yaml` já aponta por padrão para `../sicode-core` e
`../sicode-legacy`. Para apontar para outro caminho, ou para consumir uma
imagem versionada em vez de buildar localmente, sobrescreva as variáveis
`SICODE_CORE_*`/`SICODE_LEGACY_*` — ver os comentários de cada serviço em
`compose.yaml`.

## Comandos principais

```bash
make core-quality                  # composer validate, Pint, PHPStan, testes CORE
make legacy-test-matrix            # testes ES + SP
make core-runtime-isolation-test   # guard + Redis físico do CORE
make legacy-runtime-isolation-test # guard + Redis físico do Legacy
make legacy-sp-clean-e2e           # E2E CORE → Legacy SP (provisioning, Launch, lifecycle)
make legacy-es-smoke               # smoke read-only contra o banco ES real
make sp-clean-ci-local             # reproduz localmente o workflow sp-clean-ci.yml
```

Lista completa de alvos: `Makefile` na raiz.

## Documentação

- `docs/standards/` — padrões normativos (HUB, Redis, lifecycle de projeções).
- `docs/architecture/` — modelos de domínio e ADRs relacionados.
- `docs/decisions/` — ADRs.
- `docs/development/local-execution.md` — execução local detalhada.
- `AGENTS.md` — instruções para agentes trabalhando neste monorepo.

## Licença

Uso interno — BiGSerial.
