# Secrets Management

## Objetivo

Definir tratamento de secrets em desenvolvimento, runtime, CI e containers.

## Quando esta skill e obrigatoria

Use ao lidar com `.env`, client secrets, tokens, senhas, chaves privadas, containers, CI/CD ou configuracao sensivel.

## Fontes normativas

- `docs/skills/security/cryptography.md`
- `docs/skills/security/secure-logging.md`

## Regras obrigatorias

- Secrets nao entram no Git.
- Secrets nao entram em logs.
- Secrets nao devem ficar em Docker layers.
- `.env` e configuracao de runtime, nao contrato de dominio.
- CI deve receber secrets pelo mecanismo seguro da plataforma.
- Client secrets futuros devem ter hash/armazenamento seguro e rotacao.

## Padroes recomendados

- Use nomes de env claros e escopados.
- Diferencie secret de configuracao publica.
- Forneca exemplos sem valor real.

## Padroes proibidos

- Commitar `.env` real.
- Usar secret real em teste automatizado.
- Imprimir config completa.
- Reusar secret entre aplicacoes.

## Processo de execucao

1. Classifique cada variavel.
2. Remova valores reais de exemplos.
3. Garanta injecao segura em runtime/CI.
4. Revise logs e imagens.

## Checklist de conclusao

- Nada sensivel versionado.
- Logs limpos.
- Rotacao considerada.
- Escopo do secret claro.

## Quando interromper e propor ADR

- Novo secret compartilhado entre sistemas.
- Mudanca de estrategia de armazenamento de secrets.

