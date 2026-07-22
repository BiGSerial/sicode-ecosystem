# Padrão: implementação de um consumidor do CORE Application Launch Protocol

Data: 2026-07-22

Status: Normativo. Protocolo completo em
`docs/decisions/ADR-002-core-launch-protocol-and-legacy-consumer.md` e
`docs/architecture/core-application-launch-protocol.md`. Este documento é o
checklist normativo do lado consumidor, distilado da implementação real do
SICODE Legacy.

## O que o Launch é (e não é)

O Launch troca um código de uso único, curto prazo, por uma identidade
mínima do CORE (`core_subject`, `core_organization_id` quando aplicável) e
autoriza o consumidor a **criar uma sessão local** para um usuário que já
existe localmente (por vínculo prévio ou reconciliação controlada).

**O Launch nunca cria usuário ou empresa/organização no consumidor.** Isso é
responsabilidade exclusiva de provisioning (contrato técnico separado, ver
`docs/standards/local-projection-lifecycle.md`) ou de reconciliação
controlada dentro da própria camada anticorrupção do consumidor. Qualquer
documentação ou implementação que atribua criação de identidade ao Launch
está incorreta e deve ser corrigida — foi verificado nesta tarefa que a
documentação atual do SICODE Legacy (ADR-002,
`core-application-launch-protocol.md`, `legacy-multi-unit-runtime.md`,
`core-to-legacy-sp-provisioning.md`) já está correta nesse ponto.

## Camada anticorrupção obrigatória

Toda aplicação consumidora isola a integração CORE em uma camada própria
(`app/CoreIntegration` no Legacy). Controllers ficam finos e delegam. A
camada:

- fala com o CORE via client HTTP backend-to-backend
  (`CoreLaunchExchangeClient` no Legacy);
- resolve `core_subject` → usuário local via `core_identity_links`
  (`CoreIdentityLinkResolver`);
- resolve `core_organization_id` + contexto → empresa local via
  `core_organization_links` (`CoreOrganizationLinkResolver`);
- valida que o contexto retornado pelo CORE bate com a unidade configurada
  do runtime (`CoreLaunchContextValidator` — ver também o guard de boot em
  `docs/standards/hub-integrated-application-runtime.md#guards-de-boot`,
  que é uma segunda camada de defesa no nível de configuração, não de
  request);
- estabelece a sessão Laravel local e regenera o ID de sessão antes de
  redirecionar;
- nunca aceita identidade, papel, permissão ou empresa vinda de query
  string ou payload não validado pela troca backend-to-backend.

## Regras que não podem ser reimplementadas de forma diferente

- o navegador só transporta `code` e `state` — nunca `core_subject`,
  e-mail, papel ou qualquer claim de identidade;
- o callback usado no redirect vem exclusivamente do catálogo
  `application_clients.redirect_uris` do CORE, nunca de parâmetro de
  request;
- a troca é backend-to-backend, autenticada por secret de cliente
  (`CORE_LAUNCH_CLIENT_SECRET`), nunca logada em texto puro;
- o código é de uso único — replay é rejeitado porque `consumed_at` já
  estará preenchido;
- divergência entre `users.company_id` local e a empresa autorizada pelo
  Launch é rejeitada explicitamente, nunca resolvida silenciosamente.

## Checklist ao integrar uma nova aplicação/contexto como consumidor

1. Cadastrar a aplicação e o(s) `ApplicationContext` no CORE.
2. Criar `ApplicationClient` com `redirect_uris` e secret próprios por
   aplicação/contexto (nunca reaproveitar client de outro contexto).
3. Configurar no consumidor: `CORE_LAUNCH_EXCHANGE_URL`,
   `CORE_LAUNCH_CLIENT_IDENTIFIER`, `CORE_LAUNCH_CLIENT_SECRET`,
   `CORE_LAUNCH_REDIRECT_URI`, `CORE_LAUNCH_CONTEXT`.
4. Implementar/reutilizar a camada anticorrupção (`CoreIntegration`).
5. Implementar `core_identity_links` e, se a aplicação tiver conceito de
   organização/empresa local, `core_organization_links`.
6. Decidir a estratégia de vínculo (pré-link / autovínculo controlado /
   manual — ver ADR-002, seção "Estratégias de transição") e documentar a
   escolha.
7. Cobrir testes: código usado, app errado, timeout, usuário/empresa
   inativos, vínculo duplicado, conflito de sessão, contexto CORE
   divergente da unidade configurada.
8. Habilitar o guard de boot de isolamento de runtime para a
   aplicação/contexto (ver `RuntimeIsolationGuard` como referência).

## Referências de implementação

- `apps/sicode-legacy/app/CoreIntegration/*`
- `apps/sicode-legacy/app/Http/Controllers/CoreLaunchCallbackController.php`
- `apps/sicode-core/app/ApplicationLaunch/*`
- `apps/sicode-legacy/tests/Feature/CoreLaunchUnitContextTest.php`
- `apps/sicode-legacy/tests/Feature/CoreLaunchConsumerTest.php`
