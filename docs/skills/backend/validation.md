# Validation

## Objetivo

Separar validacao de entrada, invariantes de dominio, constraints de banco e regras transacionais.

## Quando esta skill e obrigatoria

Use em Form Requests, Livewire validation, Actions, services, migrations e fluxos de persistencia.

## Fontes normativas

- `docs/skills/database/database-design.md`
- `docs/skills/backend/laravel-development.md`

## Regras obrigatorias

- Validacao estrutural de entrada nao substitui invariante de dominio.
- Constraint de banco protege integridade estrutural.
- Regra transacional deve estar na camada de aplicacao apropriada.
- FormRequest nao deve concentrar regra de negocio complexa.
- Mensagens de erro nao devem vazar dados sensiveis.

## Padroes recomendados

- Normalize dados antes de validar unicidade case-insensitive.
- Valide DTO/input na borda e revalide invariantes no caso de uso.
- Escreva testes para regras que causaram bug.

## Padroes proibidos

- Confiar em frontend para regra critica.
- Duplicar regra divergente em varias camadas.
- Remover constraint porque a aplicacao ja valida.

## Processo de execucao

1. Classifique cada regra.
2. Coloque a regra na camada correta.
3. Garanta mensagem apropriada.
4. Teste falha e sucesso.

## Checklist de conclusao

- Entrada, dominio, banco e transacao separados.
- Nao ha regra critica apenas no client.
- Constraints e testes refletem invariantes.

## Quando interromper e propor ADR

- Nova invariante canonica.
- Mudanca de autoridade do dado.
- Validacao exige alterar modelo fisico.

