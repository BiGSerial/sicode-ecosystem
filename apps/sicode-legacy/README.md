# SICODE Legacy

Este diretorio e reservado para a importacao futura do codigo real do SICODE Legacy.

## Stack esperada

| Aplicacao | Laravel | Livewire | PHP | Banco |
| --- | ---: | ---: | --- | --- |
| SICODE Legacy | 10 | 2 | compativel com o legado | conforme legado |

## Regras de integracao

- Nao gerar uma aplicacao Laravel 10 vazia para simular o Legacy.
- Nao inventar dependencias, migrations, Models ou modulos.
- Nao atualizar o Legacy para Laravel 13 ou Livewire 4 como efeito colateral.
- Nao importar componentes Livewire 4.
- Nao reutilizar migrations do CORE.
- Preservar inicialmente o comportamento observado no inventario Legacy.

## Execucao futura

O Legacy deve ser executado como uma unica base de codigo com instancias separadas:

- `legacy-es`
- `legacy-sp`

Cada instancia deve possuir:

- `APP_NAME` proprio;
- `APP_URL` propria;
- `APP_KEY` propria;
- `DB_DATABASE` proprio;
- `CACHE_PREFIX` proprio;
- `SESSION_COOKIE` proprio;
- filas e schedules isolados;
- storage persistente proprio;
- `bootstrap/cache` proprio;
- logs proprios.

Nao compartilhar entre ES e SP:

- `APP_KEY`;
- sessao;
- cookies;
- cache;
- filas;
- storage mutavel;
- banco de dados.

## Status atual

Codigo Legacy ainda nao esta presente neste workspace. A importacao sera uma tarefa posterior.

