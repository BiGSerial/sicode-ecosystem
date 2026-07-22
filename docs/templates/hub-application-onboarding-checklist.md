# Checklist de onboarding: nova aplicação/contexto integrado ao HUB

Data: 2026-07-22

Use este checklist ao integrar uma aplicação nova (ou um novo
contexto/unidade de uma aplicação existente) ao SICODE HUB. Ele não cria
nenhuma aplicação — é o roteiro a seguir quando alguém for criar uma.

Documentos normativos referenciados: `docs/standards/hub-integrated-application-runtime.md`,
`docs/standards/redis-isolation.md`, `docs/standards/core-launch-consumer.md`,
`docs/standards/local-projection-lifecycle.md`.

## 1. Cadastro no CORE

- [ ] `Application` cadastrada no CORE com identificador canônico estável.
- [ ] `ApplicationContext` criado para cada contexto operacional (ex.: ES,
      SP), se a aplicação tiver mais de um.
- [ ] `ApplicationEntry` configurada com a política de autorização de
      entrada correta (organização/contrato exigido ou não).

## 2. Client e secrets

- [ ] `ApplicationClient` criado com `redirect_uris` HTTPS (ou `http://
      localhost` só em dev) próprios — nunca reaproveitado de outra
      aplicação/contexto.
- [ ] `CORE_LAUNCH_CLIENT_SECRET` gerado e armazenado como secret (nunca em
      arquivo versionado, exceto default de dev claramente identificado).
- [ ] `APP_KEY` da nova aplicação/contexto é único — nunca igual ao de
      outra unidade da mesma aplicação.

## 3. Callback

- [ ] Rota de callback implementada, fina, delegando para a camada
      anticorrupção (`CoreIntegration` ou equivalente).
- [ ] Callback validado contra o catálogo do CORE — nenhum destino aceito
      via parâmetro de request.

## 4. Runtime: Redis

- [ ] `CACHE_DRIVER=redis`, `SESSION_DRIVER=redis`, `QUEUE_CONNECTION=redis`.
- [ ] `REDIS_PREFIX=sicode:{aplicacao}:{contexto}:` definido e único.
- [ ] Conexões lógicas separadas por finalidade (cache/session/queue/lock),
      cada uma com seu próprio `options.prefix` e (recomendado) seu próprio
      número de DB — ver `docs/standards/redis-isolation.md`.
- [ ] `SESSION_COOKIE` único por contexto (`sicode_{contexto}_session`).
- [ ] Extensão PhpRedis instalada na imagem do container
      (`docker-php-ext-enable redis`), validada com `php -m` / `php --ri
      redis`.

## 5. Storage

- [ ] `storage/app` e `storage/logs` em volume nomeado próprio por
      aplicação/contexto — nunca compartilhado, mesmo quando o
      código-fonte é compartilhado via bind mount.
- [ ] Entrypoint garante a estrutura de diretórios do Laravel em um volume
      novo/vazio (`storage/app/public`, `storage/framework/*`,
      `storage/logs`).

## 6. Banco de dados

- [ ] Banco local dedicado (schema/instância não compartilhado com outra
      aplicação/contexto).
- [ ] `SICODE_EXPECTED_DATABASE` (ou equivalente) configurado e validado
      pelo guard de boot.
- [ ] Migrations não ramificam schema/comportamento por
      unidade/contexto — coberto por teste.

## 7. Provisioning ou reconciliation

- [ ] Modo de identidade decidido e documentado (ver
      `docs/standards/local-projection-lifecycle.md`).
- [ ] `provisioning_allowed` (ou equivalente) configurado coerente com a
      decisão.
- [ ] Teste cobrindo que o modo oposto é recusado no boot.

## 8. Middleware / CurrentOrganizationContext

- [ ] Middleware resolve a organização/empresa efetiva da sessão a partir
      de `core_organization_links`, nunca por inferência (e-mail, nome,
      parâmetro de request).
- [ ] Divergência entre vínculo local pré-existente e organização
      autorizada pelo Launch é rejeitada explicitamente.

## 9. Logout

- [ ] Logout encerra a sessão local inteira, independentemente da origem
      de autenticação (local vs. CORE).
- [ ] Logout de uma unidade/contexto não afeta sessão de outra unidade
      (cookies e stores de sessão são fisicamente distintos — validado por
      teste).

## 10. Guards de boot

- [ ] `RuntimeIsolationGuard` (ou implementação equivalente) habilitado nos
      containers reais via flag explícita, desabilitado por padrão em
      testes.
- [ ] Guard cobre: banco divergente, prefixo Redis divergente, cookie
      divergente, storage prefix divergente, contexto CORE divergente,
      provisioning indevido.

## 11. Auditoria

- [ ] Eventos de auditoria da aplicação nunca registram secret, token bruto
      ou payload de request não validado.
- [ ] Eventos mínimos cobertos: entrada autorizada, entrada rejeitada,
      vínculo criado/revogado, provisioning executado/rejeitado.

## 12. Testes obrigatórios

- [ ] Guard de boot (config inválida recusa subir) — sem I/O real.
- [ ] Isolamento físico de Redis (cache/sessão/fila/lock) contra Redis
      real, gated por flag de opt-in.
- [ ] Contexto CORE (Launch) validado contra a unidade configurada.
- [ ] Provisioning/reconciliation não se misturam.
- [ ] Storage de uma unidade não aparece em outra.

## 13. Observabilidade

- [ ] `healthcheck` de container testa a aplicação respondendo (não só o
      processo vivo).
- [ ] Dependências (banco, Redis) usam `condition: service_healthy`.
- [ ] Logs operacionais isolados por unidade/contexto (ver item 5).

## 14. Deploy

- [ ] Imagem com versão de base fixada.
- [ ] Migração de runtime (se substituindo um runtime existente) segue a
      sequência seiscena: parar containers → preservar uploads → limpar só
      cache/sessão/views antigos → configurar Redis → subir → validar
      healthchecks → validar isolamento → nunca apagar dado operacional
      sem autorização explícita.
- [ ] `make {app}-runtime-smoke` (ou equivalente) validado antes de
      considerar o onboarding concluído.
