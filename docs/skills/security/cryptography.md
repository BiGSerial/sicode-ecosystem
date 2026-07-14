# Cryptography

## Objetivo

Impedir criptografia improvisada e definir uso correto de primitivas aprovadas.

## Quando esta skill e obrigatoria

Use ao armazenar senha, token, segredo, assinatura, HMAC, dado criptografado ou chave.

## Fontes normativas

- `docs/skills/security/secure-development.md`
- `docs/skills/security/secrets-management.md`

## Regras obrigatorias

- Nao invente algoritmo, formato ou protocolo criptografico.
- Hash, HMAC e encryption resolvem problemas diferentes.
- Senhas devem usar hashing proprio para senha via framework aprovado.
- Segredos de client nunca devem ser texto puro.
- Chaves precisam de estrategia de rotacao e versionamento quando persistidas.

## Padroes recomendados

- Use primitivas do framework/plataforma.
- Armazene apenas hash/verificador quando possivel.
- Inclua `key_id`/versao quando houver criptografia persistente.

## Padroes proibidos

- Base64 como "criptografia".
- Hash simples para segredo reutilizavel.
- Compartilhar secrets entre aplicacoes.
- Criar formato proprio de token.

## Processo de execucao

1. Classifique o dado: senha, segredo, assinatura ou dado criptografavel.
2. Escolha primitiva aprovada.
3. Defina rotacao/versionamento.
4. Garanta que logs e dumps nao exponham valor.

## Checklist de conclusao

- Primitiva correta.
- Sem segredo em claro.
- Rotacao considerada.
- Testes nao dependem de segredo real.

## Quando interromper e propor ADR

- Novo esquema criptografico.
- Novo tipo de credencial persistida.
- Mudanca de protocolo de token.

