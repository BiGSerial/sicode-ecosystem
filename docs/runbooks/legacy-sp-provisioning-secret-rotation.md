# Runbook: Rotacao do Secret de Provisioning Legacy SP

Data: 2026-07-21

## Escopo

Este runbook cobre o secret tecnico usado pelo CORE para consumir:

```text
POST /api/core/provisioning/organizations
POST /api/core/provisioning/users
```

Nao incluir secrets reais neste documento, em commits, tickets ou logs.

## Rotacao planejada

1. Gerar novo secret com gerador criptograficamente seguro.
2. Cadastrar o novo secret na configuracao segura do Legacy SP em `CORE_PROVISIONING_CLIENT_SECRETS`.
3. Manter o secret anterior e o novo em janela de sobreposicao, se o formato JSON map do Legacy permitir.
4. Atualizar o runtime seguro do CORE com `LEGACY_SP_PROVISIONING_CLIENT_SECRET`.
5. Limpar cache de configuracao, se aplicavel.
6. Executar dry-run CORE:

```text
php artisan core:legacy-sp:provision-organization {organization_id} --dry-run
php artisan core:legacy-sp:provision-user {user_id} {organization_id} --dry-run
```

7. Executar uma tentativa controlada em ambiente de teste.
8. Confirmar no CORE que `legacy_provisioning_operations` registra outcome e attempt count sem secret.
9. Confirmar no CORE que `core_audit_events.details` nao contem secret.
10. Confirmar no Legacy que auditoria local nao contem `client_secret`.
11. Remover o secret anterior do Legacy depois da janela de sobreposicao.
12. Registrar evidencia operacional sem copiar valores secretos.

## Comprometimento suspeito

1. Remover imediatamente o secret suspeito do Legacy SP.
2. Gerar e cadastrar novo secret.
3. Atualizar o CORE.
4. Revisar logs CORE e Legacy por tentativas `authentication.rejected` e categorias equivalentes.
5. Revisar `legacy_provisioning_operations` por volume anormal, outcomes `rejected`, `conflict` ou `unavailable`.
6. Rotacionar credenciais de transporte relacionadas se houver indicio de vazamento mais amplo.
7. Registrar incidente sem incluir o secret.

## Evidencia de nao vazamento

Validar:

- nenhum `.env` real versionado;
- `.env.example` sem valor real;
- `legacy_provisioning_operations` sem request completo, headers ou secret;
- `core_audit_events.details` sem chaves contendo `secret`, `token`, `password`, `authorization` ou `cookie`;
- logs Legacy sem payload bruto;
- exceptions publicas com mensagens neutras.

## Limitacao atual

O contrato Legacy atual autentica por `client_identifier` e `client_secret` no body JSON. Isso e mantido por compatibilidade. Evolucao recomendada: mover a autenticacao tecnica para header dedicado ou mecanismo equivalente, com sanitizacao de middleware, traces e proxies revisada antes da troca.
