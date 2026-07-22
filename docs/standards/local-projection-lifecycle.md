# Padrão: ciclo de vida de projeções locais (provisioning vs. reconciliation)

Data: 2026-07-22

Status: Normativo. Implementação de referência:
`docs/architecture/core-to-legacy-sp-provisioning.md`,
`docs/architecture/legacy-sp-provisioning.md`.

## Dois modos, nunca simultâneos na mesma unidade

Toda aplicação/contexto integrado ao HUB declara exatamente um modo de
identidade via `SICODE_IDENTITY_MODE`:

- **`provisioning`**: o CORE materializa (cria) a projeção local —
  organização/empresa e usuário — via contrato técnico HTTP
  backend-to-backend dedicado (`core:legacy-sp:provision-organization`,
  `core:legacy-sp:provision-user` no Legacy SP). Usado quando a
  aplicação/contexto não tem base de usuários local pré-existente
  confiável.
- **`reconciliation`**: a aplicação já tem usuários/empresas locais
  (histórico próprio); o CORE nunca cria identidade ali, apenas
  autentica/vincula (via Launch) a um usuário local que já existe, usando
  as estratégias de transição do ADR-002 (pré-link, autovínculo
  controlado, vínculo manual).

Uma unidade/contexto configurada para `reconciliation` deve **recusar
subir** se `provisioning` for habilitado por engano — essa é uma das
checagens do `RuntimeIsolationGuard`
(`sicode.units.{unit}.provisioning_allowed`, ver
`docs/standards/hub-integrated-application-runtime.md#guards-de-boot`).
Hoje isso vale para o Legacy ES: `provisioning_allowed = false`.

## Provisioning nunca é Launch

Provisioning e Launch são contratos técnicos independentes:

- provisioning cria projeções e vínculos locais (`companies`, `users`,
  `core_organization_links`, `core_identity_links`);
- Launch troca um código efêmero e cria **sessão** local, nunca projeção;
- provisioning não emite `application_launches`, não chama
  `IssueApplicationLaunch`, não avalia `ApplicationEntry`;
- Launch para uma unidade em modo `provisioning` continua exigindo que o
  vínculo já exista localmente — o primeiro acesso de um usuário depende de
  provisioning ter rodado antes, não do próprio Launch.

Esta relação já estava documentada corretamente em
`docs/architecture/core-to-legacy-sp-provisioning.md` (seção "Relação com
Launch") antes desta tarefa; nenhuma correção foi necessária ali. Ver
`docs/standards/core-launch-consumer.md` para o padrão do lado Launch.

## Postura de segurança do provisioning

- organização/usuário CORE suspenso ou desabilitado nunca é enviado como
  ativo;
- membership suspensa, encerrada, futura ou ausente bloqueia o
  provisionamento;
- comandos exigem IDs explícitos, retornam exit code diferente de zero para
  rejeição/conflito/indisponibilidade, e nunca imprimem secret;
- suspensão/reativação remota fica fora do escopo do provisioning técnico
  inicial (é responsabilidade do fluxo administrativo local).

## Não confundir com dado histórico/migrado

Uma unidade/contexto pode conter dados operacionais anteriores à adoção do
CORE (histórico de produção, migração de sistema legado). Esse dado **não**
é criado por provisioning nem por Launch — é estado herdado, e deve ser
tratado e documentado como tal, nunca assumido implicitamente como
resultado do fluxo CORE. Ver
`docs/reports/legacy-sp-unexpected-data-investigation-2026-07-22.md` para um
caso real dessa distinção sendo investigada.

## Checklist ao decidir o modo de uma nova aplicação/contexto

1. A aplicação/contexto já tem base de usuários/empresas local confiável e
   auditável? Se sim → candidato a `reconciliation`.
2. A aplicação/contexto é nova ou sua base local não é confiável o
   suficiente para reconciliar automaticamente? Se sim → candidato a
   `provisioning`.
3. Documentar a decisão e o motivo no ADR/PR de onboarding da aplicação
   (ver `docs/templates/hub-application-onboarding-checklist.md`).
4. Configurar `sicode.units.{unit}.provisioning_allowed` (ou equivalente)
   coerente com a decisão, e confirmar que o guard de boot recusa o modo
   oposto.
5. Cobrir teste explícito: unidade em `reconciliation` recusa
   `SICODE_IDENTITY_MODE=provisioning` no boot.

## Referências de implementação

- `apps/sicode-core/app/Http/Controllers/...` (endpoints de provisioning)
- `apps/sicode-legacy/app/Console/Commands/...` (`provision-organization`,
  `provision-user`)
- `apps/sicode-legacy/config/sicode.php` (`units.{unit}.provisioning_allowed`)
- `apps/sicode-legacy/app/Support/RuntimeIsolationGuard.php`
