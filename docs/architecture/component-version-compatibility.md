# Matriz de compatibilidade entre componentes

Data: 2026-07-23

Status: Normativo. Registrado no momento da separação de `sicode-core` e
`sicode-legacy` do monorepo `sicode-ecosystem` (ver
`docs/inventory/repository-split-ownership.md`).

## Versões iniciais

| Componente | Versão | Repositório |
| --- | --- | --- |
| `sicode-ecosystem` | `0.1.0` | monorepo de integração (este repo) |
| `sicode-core` | `0.1.0` | `/home/will/code/sicode-core` local; remoto futuro `BiGSerial/sicode-core` |
| `sicode-legacy` | `0.1.0` | `/home/will/code/sicode-legacy` local; remoto futuro `BiGSerial/sicode-legacy` |

Nenhuma tag Git foi criada ainda em nenhum dos três repositórios — ver
"Comandos para criar os remotes" no relatório da tarefa de separação.

## Contratos e compatibilidade

| Contrato | Definido em | Emissor | Consumidor | Compatível com |
| --- | --- | --- | --- | --- |
| Application Launch | `docs/inherited-from-ecosystem/architecture/core-application-launch-protocol.md` (CORE) / `docs/inherited-from-ecosystem/architecture/legacy-core-integration.md` (Legacy) — fonte canônica: este repo, `docs/architecture/core-application-launch-protocol.md` | `sicode-core` 0.1.0 | `sicode-legacy` 0.1.0 | `sicode-core >=0.1.0` + `sicode-legacy >=0.1.0` |
| Provisioning SP | `docs/architecture/core-to-legacy-sp-provisioning.md` (este repo) | `sicode-core` 0.1.0 (client) | `sicode-legacy` 0.1.0 (endpoint SP) | `sicode-core >=0.1.0` + `sicode-legacy >=0.1.0` |
| Lifecycle de projeções (provisioning/reconciliation) | `docs/standards/local-projection-lifecycle.md` (este repo) | `sicode-core` 0.1.0 | `sicode-legacy` 0.1.0 | `sicode-core >=0.1.0` + `sicode-legacy >=0.1.0` |
| Redis runtime standard | `docs/standards/redis-isolation.md` (este repo) | — (padrão, não emissor/consumidor) | `sicode-core`, `sicode-legacy` | `sicode-core >=0.1.0` + `sicode-legacy >=0.1.0` — ambos implementam o padrão de forma independente, sem acoplamento de versão entre si |

Enquanto os três componentes estiverem na série `0.1.x`, qualquer
combinação entre eles é considerada compatível — não há breaking change
registrado ainda nos contratos acima. Quando um contrato mudar de forma
incompatível, a versão do componente que o define deve subir (minor ou
major conforme o caso) e esta tabela deve ser atualizada com o requisito
mínimo de versão do lado consumidor.

## Modo de transição embedded / external

Ver `compose.yaml` (comentários nos serviços `sicode-core`/`sicode-legacy`/
`sicode-legacy-es`/`sicode-legacy-snapshot`) e `Makefile`.

- **`embedded`** (default até a Etapa 23 da separação ser concluída):
  `docker compose build` usa `apps/sicode-core`/`apps/sicode-legacy` deste
  próprio repositório como contexto de build. É o comportamento histórico,
  preservado como rollback.
- **`external`**: `docker compose build` usa os repositórios irmãos
  (`../sicode-core`, `../sicode-legacy`) como contexto de build. Ativado
  via:

  ```bash
  export SICODE_CORE_BUILD_CONTEXT=../sicode-core
  export SICODE_CORE_SOURCE_DIR=../sicode-core
  export SICODE_CORE_DOCKERFILE=infra/docker/Dockerfile
  export SICODE_LEGACY_BUILD_CONTEXT=../sicode-legacy
  export SICODE_LEGACY_SOURCE_DIR=../sicode-legacy
  export SICODE_LEGACY_DOCKERFILE=infra/docker/Dockerfile
  ```

  Ou, para consumir uma imagem já publicada em vez de buildar localmente:
  defina `SICODE_CORE_IMAGE`/`SICODE_CORE_TAG` e
  `SICODE_LEGACY_IMAGE`/`SICODE_LEGACY_SP_TAG`/`SICODE_LEGACY_ES_TAG`,
  faça `docker pull` da imagem correspondente, e rode `docker compose up`
  **sem** `--build` — o Compose reaproveita a imagem local já marcada com
  aquela tag em vez de reconstruir.

**Prazo de remoção do modo `embedded`**: quando a Etapa 23 da tarefa de
separação for concluída (remoção de `apps/sicode-core`/`apps/sicode-legacy`
deste repositório), o modo `embedded` deixa de ter contexto de build válido
e os defaults de `compose.yaml` passam a apontar para `external`
diretamente. **Critério de remoção**: todos os alvos `make` da bateria de
validação (`core-quality`, `legacy-test-matrix`,
`core-runtime-isolation-test`, `legacy-runtime-isolation-test`,
`legacy-sp-clean-e2e`, `legacy-es-smoke`, `sp-clean-ci-local`) precisam
passar 100% usando exclusivamente os repositórios irmãos antes da remoção.

## Documentos relacionados

- `docs/inventory/repository-split-ownership.md` — mapa completo de
  ownership da separação.
- `docs/standards/hub-integrated-application-runtime.md` — padrão de
  runtime HUB.
- `docs/standards/redis-isolation.md` — padrão Redis.
