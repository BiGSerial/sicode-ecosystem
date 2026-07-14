# Application Boundaries

## Objetivo

Garantir que CORE, Legacy, SICODESK, SICODE 2.0 e futuras aplicacoes mantenham fronteiras de responsabilidade explicitas.

## Quando esta skill e obrigatoria

Use em qualquer integracao entre aplicacoes, autenticacao, autorizacao, acesso a dados, sincronizacao, API, evento ou compartilhamento de estado.

## Fontes normativas

- `docs/architecture/core-application-authorization-boundaries.md`
- `docs/architecture/core-identity-access-canon.md`
- `docs/architecture/legacy-core-integration.md`
- `docs/architecture/legacy-to-core-transition-map.md`
- `docs/decisions/ADR-001-core-identity-authority-and-legacy-transition.md`

## Regras obrigatorias

- CORE decide identidade, autenticacao canonica e direito de entrada.
- Aplicacoes decidem regras operacionais internas.
- Integre por contratos documentados, nunca por banco, Model, migration ou classe interna de outro sistema.
- Acesso cross-database entre aplicacoes e CORE e proibido.
- SICODESK e SICODE 2.0 nao podem conhecer estruturas Legacy.

## Padroes recomendados

- Use a pergunta: se a aplicacao desaparecer, essa autorizacao ainda faz sentido no ecossistema?
- Mantenha bridges de transicao removiveis.
- Nomeie contexts operacionais explicitamente, como ES/SP.

## Padroes proibidos

- `viability.approve` no CORE.
- Compartilhar Eloquent Models entre aplicacoes.
- Ler tabelas internas do CORE por aplicacao consumidora.
- Criar autenticao paralela permanente.

## Processo de execucao

1. Identifique os sistemas envolvidos.
2. Classifique a regra como entrada CORE ou operacao local.
3. Defina contrato externo necessario.
4. Verifique se ha dependencia Legacy indevida.
5. Registre qualquer excecao.

## Checklist de conclusao

- Fronteira de responsabilidade documentada.
- Nenhum acesso direto a banco externo.
- Nenhuma permissao operacional vazou para o CORE.
- A solucao continua valida apos aposentadoria do Legacy.

## Quando interromper e propor ADR

- Novo protocolo de integracao.
- Novo compartilhamento de dados entre aplicacoes.
- Mudanca de dono de uma regra.
- Necessidade de cross-database.

