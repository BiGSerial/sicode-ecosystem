# Architecture Change

## Objetivo

Definir quando uma mudanca exige ADR ou atualizacao arquitetural antes de codigo.

## Quando esta skill e obrigatoria

Use quando a tarefa tocar identidade, autenticacao, autorizacao, persistencia canonica, integracao, stack, protocolos ou fronteiras de aplicacao.

## Fontes normativas

- `docs/decisions/ADR-001-core-identity-authority-and-legacy-transition.md`
- `docs/architecture/core-identity-access-canon.md`
- `docs/skills/README.md`

## Regras obrigatorias

- ADR e obrigatorio para mudanca de decisao fundadora.
- Skills nao podem alterar arquitetura.
- Implementacao deve parar quando houver conflito documental.
- Divergencia entre codigo e canon deve ser registrada.

## Padroes recomendados

- ADR deve descrever contexto, decisao, alternativas e consequencias.
- Mudancas menores podem atualizar documento arquitetural sem ADR se nao alterarem decisao fundadora.

## Padroes proibidos

- Cross-database sem ADR.
- Novo protocolo sem ADR.
- Nova autoridade de dados sem ADR.
- Nova forma de autorizacao global sem ADR.
- Mudanca estrutural de stack sem ADR.

## Processo de execucao

1. Compare tarefa com canon.
2. Classifique se altera decisao.
3. Se alterar, pare e proponha ADR.
4. Se nao alterar, siga skill aplicavel e documente ajuste.

## Checklist de conclusao

- Necessidade de ADR avaliada.
- Conflitos documentados.
- Mudanca implementada somente dentro da arquitetura aprovada.

## Quando interromper e propor ADR

- Identidade/autenticacao/autorizacao mudam de dono.
- Novo protocolo.
- Estrategia de ID muda.
- Fronteira de aplicacao muda.
- Stack estrutural muda.

