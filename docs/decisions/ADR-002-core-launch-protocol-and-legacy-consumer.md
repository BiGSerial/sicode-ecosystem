# ADR-002: Protocolo de lancamento CORE para consumidores Legacy

Data: 2026-07-15

Status: Aceita

## Contexto

O SICODE CORE e autoridade canonica de identidade, autenticacao e direito de entrada no ecossistema. O Hub CORE ja apresenta aplicacoes permitidas por `ApplicationEntry`, mas ainda nao implementa navegacao autenticada para aplicacoes consumidoras.

O SICODE Legacy ES/SP e consumidor prioritario do protocolo de lancamento. Ele deve continuar executando como Laravel 10 com Livewire 2, usuarios locais e autenticacao por sessao Laravel local. A compatibilidade nao pode exigir upgrade do Legacy para Laravel 13 ou Livewire 4, nem compartilhar Models, migrations, componentes Livewire, tabelas ou classes internas entre CORE e Legacy.

Esta decisao define o contrato arquitetural do lancamento CORE -> Legacy. Ela nao implementa endpoint, migration, controller, client HTTP ou camada Legacy.

## Decisao

O lancamento de aplicacao pelo CORE usara um fluxo de codigo de lancamento de uso unico, curto prazo e troca backend-to-backend, compativel com evolucao futura para OAuth 2.0 / OpenID Connect.

O navegador nunca deve transportar identidade canonica suficiente para autenticar o usuario na aplicacao consumidora. A URL de callback pode conter somente parametros tecnicos, como `code` e `state`. A identidade minima do CORE deve ser entregue apenas apos troca backend-to-backend autenticada entre Legacy e CORE.

Livewire nao participa do protocolo de autenticacao ou lancamento. No Legacy, Livewire 2 continua operando normalmente depois que a sessao Laravel local ja estiver estabelecida.

## Legacy Laravel 10 Consumer Flow

```text
CORE Hub
-> launch authorization
-> legacy callback
-> backend exchange
-> resolve CORE subject
-> link to legacy user
-> Laravel 10 session
-> session regeneration
-> internal SICODE route
-> normal Blade/Livewire 2 operation
```

Fluxo normativo:

1. O usuario autenticado no CORE seleciona uma entrada permitida no Hub.
2. O CORE reavalia `ApplicationEntry` no backend para aplicacao, cliente e contexto operacional.
3. O CORE emite um `launch_code` de uso unico, curto prazo e vinculado a cliente, aplicacao, contexto, redirect/callback registrado e `state`.
4. O navegador e redirecionado para o callback Legacy registrado com `code` e `state`.
5. O callback Legacy valida presenca, formato, origem esperada e consistencia tecnica dos parametros. No lancamento iniciado pelo CORE Hub, o `state` e uma correlacao vinculada ao codigo no CORE; a validacao forte ocorre quando o Legacy envia o mesmo `state` na troca backend-to-backend e o CORE rejeita divergencias. Em fluxos futuros iniciados pela aplicacao consumidora, o Legacy tambem deve validar `state` contra valor local previamente armazenado.
6. O Legacy troca o codigo com o CORE em canal backend-to-backend autenticado, enviando `code`, `state`, identificador do cliente e callback esperado.
7. O CORE consome atomicamente o codigo e valida uso unico, validade temporal, cliente, aplicacao, contexto, callback e `state`.
8. O CORE retorna identidade minima estavel: `issuer`, `core_subject`, aplicacao, contexto, `launch_id`, instante de emissao e expiracao. Claims de apresentacao, quando existirem, nao sao autoridade para vinculo local.
9. O Legacy resolve `core_subject` para usuario local por uma camada anticorrupcao propria.
10. O Legacy estabelece sua propria sessao Laravel 10 para o usuario local resolvido.
11. O Legacy regenera a sessao local antes de redirecionar.
12. O Legacy redireciona apenas para rota interna segura e allowlisted do SICODE.
13. Blade e Livewire 2 operam com o usuario local autenticado, sem conhecer o protocolo CORE.

## Contrato de seguranca do codigo de lancamento

O `launch_code` deve ser:

- opaco para o navegador;
- de uso unico;
- curto prazo;
- consumido atomicamente;
- vinculado a `ApplicationClient`, aplicacao, contexto operacional, callback registrado e `state`;
- inutilizavel por outro cliente ou aplicacao;
- incapaz de alterar usuario local sem passar pela resolucao Legacy.

O CORE deve negar por padrao qualquer troca com cliente, callback, aplicacao, contexto ou `state` divergente.

O Legacy nao deve aceitar identidade, papel, permissao, usuario local ou status operacional vindo da query string ou de payload nao validado pela troca backend-to-backend.

## Payload minimo de identidade CORE

O payload de troca deve expor somente o necessario para a aplicacao consumidora iniciar sua sessao local:

- `iss`: issuer estavel do CORE;
- `core_subject`: identificador canonico estavel do usuario CORE;
- `application`: identificador canonico da aplicacao;
- `context`: contexto operacional autorizado, como ES ou SP;
- `launch_id`: correlacao tecnica auditavel;
- `issued_at` e `expires_at`;
- `state` ou confirmacao equivalente para validar a correlacao enviada no callback.

O payload nao deve conter `legacy_user_id` como autoridade, permissao operacional local ou instrucao para trocar usuario local. Email, nome e atributos de exibicao podem existir futuramente apenas como claims informativas ou de reconciliacao controlada; depois do vinculo persistido, autenticacao local deve usar `core_subject`.

## Camada anticorrupcao Legacy

O Legacy deve isolar a integracao em uma camada propria, conceitualmente `app/CoreIntegration`. O nome final pode seguir a convencao do codigo importado, desde que a fronteira seja preservada.

Responsabilidades da camada:

- client HTTP backend-to-backend para CORE;
- DTO imutavel de identidade de lancamento;
- acao de consumo do codigo;
- repositorio de vinculos `core_subject` -> usuario Legacy;
- resolucao de usuario local a partir do `core_subject`;
- estabelecimento da sessao Laravel 10 local;
- erros de integracao com mensagens neutras;
- auditoria local por allowlist.

Controllers Legacy devem ser finos e chamar essa camada. Livewire 2 e Blade nao devem parsear payload CORE, consumir codigo, decidir vinculo de identidade ou estabelecer sessao. O Legacy nao deve importar Models, migrations, services ou enums do CORE.

## Vinculo local de identidade

O Legacy pode manter uma estrutura local conceitual `core_identity_links` para vincular o sujeito canonico do CORE a um usuario local historico.

Campos conceituais recomendados:

- identificador local do vinculo;
- `core_issuer`;
- `core_subject`;
- `legacy_user_id`;
- contexto Legacy quando o runtime/banco nao o tornar implicito;
- `linked_at`;
- `last_used_at`;
- status local do vinculo, como ativo ou revogado;
- metadados minimos de auditoria.

Cardinalidade e invariantes:

- um `core_subject` ativo deve apontar para no maximo um usuario local por runtime/contexto Legacy;
- um usuario local ativo deve apontar para no maximo um `core_subject` por runtime/contexto Legacy;
- ES e SP sao contextos independentes e podem ter vinculos separados;
- vinculo ativo duplicado deve bloquear autenticacao e exigir resolucao administrativa;
- alteracao de vinculo deve ocorrer por revogacao e novo vinculo auditado, nao por sobrescrita silenciosa;
- `last_used_at` so deve ser atualizado apos troca backend-to-backend e sessao local bem-sucedidas.

Esta ADR nao cria migration. A migration Legacy deve ser proposta em tarefa propria.

## Estrategias de transicao

### A. Pre-link / migracao controlada

Cria vinculos antes do lancamento produtivo por reconciliacao auditada.

Vantagens: deterministico, reduz risco de associacao indevida, permite tratar duplicidades, usuarios inativos, contas compartilhadas e divergencias ES/SP antes do uso.

Custos: exige preparo operacional, inventario confiavel e saneamento previo.

### B. Link automatico controlado no primeiro lancamento

Permite criar vinculo no primeiro lancamento somente quando houver atributo previamente validado, confiavel, unico naquele runtime Legacy e associado a um unico usuario local ativo.

Regras minimas:

- nunca usar email bruto como autoridade permanente;
- nunca autovincular quando houver email alterado, duplicado, normalizacao divergente, usuario inativo, conta tecnica, conta compartilhada ou base divergente;
- persistir o vinculo com `core_subject` apos a primeira resolucao;
- futuras autenticacoes devem usar o vinculo estavel, nao o atributo de descoberta;
- registrar auditoria local.

### C. Vinculo manual / administrativo

Exige decisao humana para usuarios ambiguos, ausentes, inativos, compartilhados, tecnicos ou com divergencia de base.

Vantagens: menor risco de identidade incorreta.

Custos: maior carga operacional e fila de atendimento.

### Recomendacao para Legacy ES/SP

Para Legacy ES e Legacy SP, a estrategia recomendada e hibrida:

1. usar pre-link/migracao controlada para a maior parte dos usuarios elegiveis;
2. enviar ambiguidades para vinculo manual/administrativo;
3. permitir link automatico controlado apenas para coortes de baixo risco, com atributo previamente validado, unicidade comprovada no runtime Legacy, usuario local ativo e auditoria.

ES e SP devem ser tratados separadamente. Um acesso autorizado ao SP nao implica acesso ao ES, e o mesmo `core_subject` pode possuir vinculos locais distintos em cada ambiente quando a pessoa possuir direito de entrada em ambos.

## Modelo de ameacas e respostas

| Cenario | Resposta obrigatoria |
| --- | --- |
| Codigo valido consumido pelo app errado | Rejeitar na troca porque o codigo e vinculado a cliente, aplicacao, contexto e callback. |
| `core_subject` inexistente no Legacy | Negar sessao local e encaminhar para fluxo manual ou autovinculo controlado quando elegivel. |
| Vinculo duplicado | Bloquear autenticacao, auditar e exigir resolucao administrativa. |
| Usuario Legacy inativo | Negar sessao local mesmo que o usuario CORE esteja ativo. |
| Usuario CORE ativo vinculado a usuario Legacy bloqueado | Negar sessao local; o estado operacional local continua soberano para disponibilidade da conta Legacy. |
| Email divergente apos vinculo | Ignorar email para autenticacao; usar `core_subject` vinculado. Atualizacao de atributo deve ser fluxo separado. |
| Tentativa de trocar usuario por payload | Ignorar campos nao autoritativos; resolver somente por `core_subject` e vinculo local. |
| Replay de callback | Rejeitar porque o codigo e de uso unico, curto prazo e consumido atomicamente. |
| Callback aberto em duas abas | Primeira troca valida consome o codigo; a segunda falha com erro neutro sem alterar sessao. |
| Falha do CORE apos redirect | Nao estabelecer sessao; mostrar erro seguro e permitir novo lancamento. |
| Timeout na troca backend-to-backend | Nao estabelecer sessao; auditar timeout sem logar segredo; usuario deve reiniciar lancamento. |
| CORE temporariamente indisponivel | Novos lancamentos falham de forma segura; sessoes Legacy ja existentes seguem sua politica local. |
| Sessao Legacy existente com outro `core_subject` | Nao sobrepor identidades; invalidar sessao anterior antes de iniciar a nova sessao apos troca e vinculo bem-sucedidos. |
| Usuario ja autenticado localmente antes da integracao | Manter origem de autenticacao na sessao; lancamento CORE para outro usuario exige troca explicita por invalidacao de sessao. |
| Coexistencia entre login local e login CORE | Permitir temporariamente com metadado de origem, sem misturar identidades nem usar login local como autoridade canonica futura. |

## Coexistencia temporaria de autenticacao

Durante a transicao, o Legacy pode manter login local e login iniciado pelo CORE.

Padrao recomendado:

- usar o mesmo guard `web` do Laravel 10 quando ambos terminarem no mesmo Model local de usuario;
- gravar na sessao metadados como `auth_source`, `core_subject`, `core_identity_link_id` e contexto;
- tratar logout como encerramento da sessao local inteira, independentemente da origem;
- quando a sessao local existente pertencer ao mesmo usuario/vinculo, regenerar a sessao e atualizar metadados;
- quando a sessao local existente pertencer a outro usuario, invalidar antes de iniciar a nova sessao;
- nao manter dois usuarios locais autenticados no mesmo navegador/sessao;
- nao exigir remocao imediata do login local, mas planejar desativacao por coorte apos cobertura de vinculos, criterio de rollback e suporte administrativo.

Um guard separado so deve ser criado se uma decisao tecnica futura demonstrar necessidade real de distinguir politicas por origem. Mesmo nesse caso, a aplicacao deve impedir identidades simultaneas conflitantes.

## Sequencia futura de implementacao

Tarefas agenticas independentes recomendadas:

1. Inventariar guards, login, logout, middleware e pontos de sessao do Legacy Laravel 10.
2. Definir interfaces e DTOs da camada `CoreIntegration` no Legacy, sem HTTP real.
3. Definir configuracao Legacy para endpoint CORE, client id, segredo e callback registrado.
4. Propor migration Legacy para `core_identity_links` com constraints e auditoria.
5. Implementar repositorio de vinculo e resolucao local por `core_subject`.
6. Implementar endpoint CORE de emissao/consumo de `launch_code`, sem acoplar ao Hub visual inicialmente.
7. Implementar client backend-to-backend Legacy para troca do codigo.
8. Implementar callback controller Legacy fino, delegando para `CoreIntegration`.
9. Implementar estabelecimento de sessao Laravel 10 com regeneracao e metadados de origem.
10. Cobrir testes de codigo usado, app errado, timeout, usuario inativo, vinculo duplicado e conflito de sessao.
11. Executar piloto ES com coorte pre-linkada e fila manual para ambiguidades.
12. Repetir piloto SP separadamente.
13. Planejar desativacao gradual do login local por coorte, com rollback documentado.

## Consequencias

O CORE ganha um protocolo arquitetural de lancamento sem impor stack nova ao Legacy.

O Legacy preserva seu usuario local, sua sessao Laravel 10 e sua operacao Blade/Livewire 2, mas deixa de tratar login local como destino permanente para usuarios migrados.

O vinculo por `core_subject` impede que email, ID Legacy ou payload de callback virem identidade global.

A implementacao exigira tarefas posteriores no CORE e no Legacy, incluindo endpoint de codigo, client HTTP, migration local, testes de seguranca e plano operacional ES/SP.
