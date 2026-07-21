# Runbook: Rotação de Secret de Cliente de Aplicação (`client_secret`)

Data: 21/07/2026  
Status: Operacional / Normativo  

---

## 1. Objetivo

Este runbook especifica os procedimentos operacionais para a **rotação segura de credenciais de cliente** (`client_secret`) de aplicações consumidoras integradas ao SICODE CORE.

A rotação de `client_secret` DEVE ocorrer nas seguintes situações:
1. Rotação periódica de segurança (ex: a cada 90 ou 180 dias);
2. Suspeita ou confirmação de comprometimento do segredo de cliente;
3. Mudança de equipe ou encerramento de contrato de operadores com acesso às credenciais;
4. Onboarding de um novo ambiente (Staging / Produção).

---

## 2. Pré-requisitos Operacionais
- Acesso com privilégios administrativos ao SICODE CORE (console ou ferramentas de gestão).
- Acesso às variáveis de ambiente do servidor onde a aplicação consumidora está implantada.
- Janela de manutenção programada (para rotação síncrona) ou suporte a janela de carência.

---

## 3. Passo a Passo da Rotação Operacional

### Passo 1 — Geração da Nova Credencial de Alta Entropia
Gerar um novo segredo randômico de 64 a 128 caracteres utilizando gerador de entropia criptográfica (ex: `openssl rand -hex 32` ou `bin2hex(random_bytes(32))`).

```bash
# Exemplo de geração de segredo de 64 caracteres hexadecimais
openssl rand -hex 32
```

### Passo 2 — Atualização da Hash no SICODE CORE
No banco de dados do CORE, o segredo NUNCA é armazenado em texto plano. O CORE armazena o hash seguro (`client_secret_hash`).
- Atualizar a coluna `client_secret_hash` do registro em `application_clients` para a hash do novo segredo.
- Registrar o evento de auditoria no CORE informando o ator responsável e o motivo da rotação.

### Passo 3 — Atualização na Aplicação Consumidora
- Atualizar a variável de ambiente `CORE_CLIENT_SECRET` no servidor/container da aplicação consumidora.
- Reiniciar o processo da aplicação consumidora (ou recarregar o cache de configurações) para que o novo segredo passe a ser utilizado.

### Passo 4 — Validação pós-rotação
- Realizar um teste de lançamento de aplicação vindo do CORE Hub.
- Verificar se a chamada `/api/v1/launch/exchange` retorna HTTP 200 OK e o payload de identidade é recebido normalmente.
- Verificar os logs da aplicação consumidora confirmando a ausência de erros `401 Launch exchange rejected`.

---

## 4. Plano de Rollback em Caso de Falha
Se a aplicação consumidora passar a falhar com HTTP 401 após a atualização:
1. Reverter a variável `CORE_CLIENT_SECRET` no consumidor para o segredo anterior (caso o hash no CORE ainda não tenha sido alterado).
2. Se o hash no CORE já tiver sido atualizado, re-gerar a hash no CORE apontando para o segredo operacional conhecido ou re-atualizar o segredo no consumidor.
3. Investigar nos logs do CORE o motivo da divergência de hash.
