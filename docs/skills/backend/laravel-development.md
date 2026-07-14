# Laravel Development

## Objetivo

Definir o padrao geral de implementacao Laravel do SICODE Ecosystem.

## Quando esta skill e obrigatoria

Use em Controllers, Models, Actions/Application Services, Jobs, Events, Exceptions, DTOs e fluxos de aplicacao.

## Fontes normativas

- `AGENTS.md`
- `docs/agent/project-context.md`
- skills de arquitetura, database, security e testing aplicaveis.

## Regras obrigatorias

- SICODE CORE e SICODESK usam Laravel 13 e PHP 8.4.
- SICODE Legacy permanece em Laravel 10 e PHP compativel com o codigo real importado.
- Nao aplique padroes Laravel 13 automaticamente ao Legacy.
- Nao rebaixe CORE ou SICODESK para padroes do Legacy.
- Use tipagem explicita em assinaturas novas.
- Controllers devem orquestrar entrada/saida, nao concentrar regra de negocio.
- Regra de aplicacao deve ir para Actions/Application Services quando exceder fluxo trivial.
- Models nao devem virar services globais.
- Transacoes devem envolver mudancas atomicas de estado.
- Exceptions devem distinguir erro de dominio, aplicacao e infraestrutura quando isso afetar comportamento.
- Models canonicos do CORE com UUID gerado por PostgreSQL devem estender `App\Models\CoreModel`; nao use `HasUuids`, `HasUlids` ou geracao de ID na aplicacao sem ADR.

## Padroes recomendados

- DTOs apenas quando reduzem acoplamento ou tornam contrato explicito.
- Jobs para trabalho assíncrono real, idempotente quando possivel.
- Events para fatos de dominio/aplicacao relevantes, nao para esconder fluxo procedural.
- Abstraia somente quando houver variacao real.

## Padroes proibidos

- Abstracoes especulativas.
- Regras criticas espalhadas em Controllers/Livewire/Blade.
- Acesso direto a banco de outro sistema.
- Mass assignment sem allowlist consciente.
- Misturar dependencias Laravel 13 e Laravel 10 em um unico `composer.json`.
- Criar autoload cruzado entre CORE, SICODESK e Legacy.

## Processo de execucao

1. Identifique camada responsavel.
2. Defina transacao e invariantes.
3. Implemente caso minimo.
4. Adicione testes proporcionais ao risco.
5. Revise diff por acoplamento e duplicacao.

## Checklist de conclusao

- Responsabilidades separadas.
- Tipos e exceptions coerentes.
- Transacoes nos pontos de consistencia.
- Nenhuma abstracao sem necessidade real.

## Quando interromper e propor ADR

- Nova camada arquitetural.
- Novo padrao de integracao.
- Mudanca estrutural na stack.
