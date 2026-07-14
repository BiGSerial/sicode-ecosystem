# Authorization

## Objetivo

Garantir que autorizacao seja implementada no dominio correto, com checks auditaveis e sem permissões operacionais no CORE.

## Quando esta skill e obrigatoria

Use ao criar ou alterar Gates, Policies, middleware, access checks, permissões, bypasses ou regras de entrada.

## Fontes normativas

- `docs/architecture/core-application-authorization-boundaries.md`
- `docs/architecture/core-identity-access-canon.md`
- `docs/architecture/legacy-to-core-transition-map.md`

## Regras obrigatorias

- CORE controla direito de entrada em aplicacao/contexto.
- Aplicacao controla permissões operacionais.
- Policies devem ficar proximas do recurso quando a regra depende da entidade.
- Gates devem ser usados para capacidades amplas e nomeadas, nao como deposito de regras dispersas.
- Bypass administrativo deve ser explicito, minimo e auditavel.

## Padroes recomendados

- Centralize regras repetidas em Policy/Service apropriado.
- Teste autorizacao permitida e negada.
- Nomeie checks pela pergunta de negocio.

## Padroes proibidos

- Checks inline espalhados em Controllers, Livewire e Blade.
- `superadm` ou equivalente implicito sem auditoria.
- Permissões como `viability.approve` no CORE.
- UI esconder botao como unica protecao.

## Processo de execucao

1. Classifique CORE versus local.
2. Escolha Policy/Gate/middleware/service.
3. Implemente deny by default.
4. Teste caminhos permitido, negado e bypass se existir.
5. Registre conflito com canon se aparecer.

## Checklist de conclusao

- Dono da regra claro.
- Deny path testado.
- Nenhum check apenas visual.
- Bypass documentado e testado.

## Quando interromper e propor ADR

- Nova forma de autorizacao global.
- Regra operacional que parece atravessar aplicacoes.
- Bypass administrativo novo ou ampliado.

