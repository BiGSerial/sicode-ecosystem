# Fronteiras de autorizacao entre CORE e aplicacoes

Este documento formaliza onde uma regra deve existir.

## Regra central

CORE decide entrada no ecossistema e nas aplicacoes.

Aplicacoes decidem operacao interna.

## Matriz de decisao

| Pergunta | Se sim | Se nao |
| --- | --- | --- |
| A regra continua fazendo sentido se a aplicacao desaparecer? | Pode pertencer ao CORE | Pertence a aplicacao |
| A regra fala sobre identidade, organizacao, contrato ou entrada? | CORE | Aplicacao |
| A regra fala sobre workflow, papel interno ou entidade de dominio? | Aplicacao | Avaliar |
| A regra precisa ser entendida por varias aplicacoes como politica institucional? | CORE ou ADR | Aplicacao local |
| A regra exige dados internos de uma aplicacao? | Aplicacao | CORE pode avaliar |

## Exemplos

| Regra | Dono | Motivo |
| --- | --- | --- |
| Usuario ativo pode acessar SICODESK | CORE | Direito de entrada |
| Usuario pode ser operador SICODESK | SICODESK | Papel interno |
| Usuario pode acessar SICODE SP | CORE | Entrada por contexto |
| Usuario pode aprovar viabilidade | SICODE | Workflow do dominio |
| Organizacao possui contrato ativo para SICODE ES | CORE | Contrato institucional |
| Usuario pode editar atividade especifica | SICODE | Entidade de dominio |

## Processo obrigatorio para novas regras

Antes de criar permissao, tabela ou endpoint de autorizacao:

1. Identificar a pergunta de autorizacao.
2. Identificar o dominio proprietario.
3. Verificar se a regra depende de contrato, organizacao, aplicacao ou contexto.
4. Verificar se a regra depende de workflow ou entidade interna.
5. Consultar ADRs existentes.
6. Registrar ADR se a regra alterar a fronteira CORE/aplicacao.

## Antipadroes proibidos

PROIBIDO: criar permissoes operacionais de aplicacao no CORE.

PROIBIDO: criar autorizacao local que contradiga bloqueio global de identidade.

PROIBIDO: conceder entrada a aplicacao ignorando contrato quando a aplicacao exige contrato.

PROIBIDO: usar papel local como substituto de ApplicationAccess.

PROIBIDO: usar ApplicationAccess como substituto de papel local.

