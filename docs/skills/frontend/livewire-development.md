# Livewire Development

## Objetivo

Definir o papel de componentes Livewire em interfaces interativas do CORE.

## Quando esta skill e obrigatoria

Use ao criar ou alterar componente Livewire, eventos, polling, SSE, validacao interativa, queries ou paginacao.

## Fontes normativas

- `docs/skills/backend/laravel-development.md`
- `docs/skills/backend/validation.md`
- `docs/skills/frontend/design-frontend.md`

## Regras obrigatorias

- SICODE CORE e SICODESK usam Livewire 4.
- SICODE Legacy permanece em Livewire 2 quando o codigo real for importado.
- Nao importe componentes Livewire 4 para o Legacy.
- Nao adapte CORE/SICODESK a APIs Livewire 2 por compatibilidade com Legacy.
- Verifique a versao efetiva do Livewire no projeto antes de usar APIs especificas.
- Estado publico deve ser minimo e serializavel.
- Componente nao deve concentrar regra de dominio.
- Persistencia deve ser delegada a Action/Application Service quando nao for trivial.
- Queries devem ser paginadas quando listas puderem crescer.
- Loading/error states devem existir para acoes assíncronas.

## Padroes recomendados

- Eventos Livewire devem representar interacao clara.
- Polling deve ter intervalo e motivo documentados.
- SSE pode ser usado quando houver requisito arquitetural de tempo real.
- Separe componentes grandes em unidades menores por responsabilidade.

## Padroes proibidos

- Componente gigante com regra de negocio, autorizacao e query complexa juntas.
- Propriedades publicas com dados sensiveis.
- Validacao somente no frontend.
- Polling sem necessidade.

## Processo de execucao

1. Verifique versao/dependencias.
2. Defina responsabilidade do componente.
3. Mantenha estado minimo.
4. Delegue regra.
5. Teste interacao e autorizacao.

## Checklist de conclusao

- Estado publico revisado.
- Regra de dominio fora do componente.
- Loading/error/pagination cobertos.
- Autorizacao backend aplicada.

## Quando interromper e propor ADR

- Introducao de SSE/tempo real estrutural.
- Mudanca de stack interativa.
