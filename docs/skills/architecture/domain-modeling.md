# Domain Modeling

## Objetivo

Garantir que novos conceitos sejam modelados por significado de dominio, autoridade do dado e fronteira arquitetural, nao por conveniencia de tabela, nome legado ou preferencia do agente.

## Quando esta skill e obrigatoria

Use ao criar ou alterar entidades, aggregates, value objects, projecoes, dados derivados, tabelas, Models ou contratos de dominio.

## Fontes normativas

- `docs/architecture/core-identity-access-canon.md`
- `docs/architecture/core-identity-domain-model.md`
- `docs/architecture/core-identity-access-physical-model.md`
- `docs/architecture/legacy-to-core-transition-map.md`
- `docs/decisions/ADR-001-core-identity-authority-and-legacy-transition.md`

## Regras obrigatorias

- Identifique o dominio proprietario antes de modelar.
- Declare a autoridade de cada dado: CORE, aplicacao, derivado ou transicao.
- Separe entidade com identidade propria de value object, projecao e dado derivado.
- Verifique se o conceito ja existe com outro nome antes de criar algo novo.
- Modele por semantica, nao por nome de tabela Legacy.
- Para identidade/acesso CORE, siga o modelo fisico aprovado.

## Padroes recomendados

- Use aggregates pequenos, com invariantes claras.
- Use projecao local somente quando a autoridade permanecer em outro dominio.
- Prefira nomes canonicos globais para conceitos globais: `User`, `Organization`, `Contract`, `Application`.

## Padroes proibidos

- Criar conceito sobreposto sem analise.
- Promover `users.company_id`, `company_user` ou `employees -> contracts` a modelo canonico.
- Usar ID local Legacy como identidade global.
- Criar permissao operacional no CORE.

## Processo de execucao

1. Leia canon, ADR e modelo de dominio.
2. Defina problema de dominio em uma frase.
3. Identifique autoridade e lifecycle.
4. Classifique entidade, value object, projecao ou derivado.
5. Verifique conflitos com documentos existentes.
6. Documente divergencias antes de implementar.

## Checklist de conclusao

- O proprietario do dado esta claro.
- Nao ha duplicidade conceitual.
- O lifecycle esta definido ou conscientemente adiado.
- A modelagem continua valida sem Legacy.

## Quando interromper e propor ADR

- Nova autoridade de dados.
- Mudanca de fronteira CORE/aplicacao.
- Novo identificador global.
- Conceito que contradiz canon ou modelo fisico.

