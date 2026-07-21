# Guia de Testes de Contrato para Aplicações Consumidoras

Data: 21/07/2026  
Status: Normativo / Orientação Técnica  

---

## 1. Objetivo

Este documento orienta os desenvolvedores de aplicações consumidoras sobre como estruturar **testes de contrato (Contract Tests)** e **testes de resiliência** para validar a integração com o SICODE CORE sem depender de chamadas HTTP reais em ambiente de desenvolvimento local.

---

## 2. Testes de Contrato da Troca Backend-to-Backend

A aplicação consumidora DEVE manter testes automatizados que garantam:

1. **Envio Correto dos Parâmetros no Exchange**:
   - Envio dos campos JSON: `client_identifier`, `client_secret`, `code`, `state`.
   - Cabeçalho `Content-Type: application/json` e `Accept: application/json`.

2. **Deserialização do Payload de Resposta (`200 OK`)**:
   - O teste deve simular o retorno JSON do CORE:
     ```json
     {
       "iss": "https://sicode.sistemas.gov.br",
       "core_subject": "9b1deb4d-3b7d-4bad-9bdd-2b0d7b3dcb6d",
       "core_organization_id": "8f2a1b3c-4d5e-6f7a-8b9c-0d1e2f3a4b5c",
       "application": "sicodesk",
       "context": "sp",
       "launch_id": "123e4567-e89b-12d3-a456-426614174000",
       "issued_at": "2026-07-21T18:00:00.000000Z",
       "expires_at": "2026-07-21T18:01:00.000000Z",
       "state": "b7d8e9f0a1b2c3d4e5f6"
     }
     ```
   - O consumidor deve verificar se os campos obrigatórios `iss`, `core_subject`, `application`, `launch_id` são extraídos com sucesso.

3. **Tratamento de Campos Opcionais Ausentes**:
   - O teste deve simular a ausência dos campos opcionais `context` e `core_organization_id` (quando a aplicação for neutra de contexto/organização) e validar que a aplicação processa o login sem lançar exceções não tratadas.

---

## 3. Testes de Casos de Borda e Falha de Segurança

1. **Teste de Rejeição por Replay (Launch Code Reutilizado)**:
   - Simular resposta HTTP `422 Unprocessable Entity` com payload `{"message": "Launch exchange rejected."}`.
   - Garantir que o consumidor exibe mensagem de erro neutra e **não cria** a sessão local.

2. **Teste de Rejeição por Credencial Inválida**:
   - Simular resposta HTTP `401 Unauthorized` com payload `{"message": "Launch exchange rejected."}`.
   - Garantir que o consumidor rejeita a entrada e registra log de erro interno.

3. **Teste de Timeout de Rede (Resiliência Fail-Closed)**:
   - Simular exceção de timeout de socket/conexão HTTP na chamada de exchange.
   - Garantir que o consumidor falha de forma segura (negação por padrão) sem conceder acesso ao usuário nem travar o processo.

---

## 4. Testes de Projeção Local de Identidade

1. **Criação Inicial de Vínculo**:
   - Verificar se na primeira autenticação com um novo `core_subject`, a tabela de projeção (ex: `core_identity_links`) cria o registro vinculando-o a um usuário local.
2. **Re-autenticação Idempotente**:
   - Verificar se autenticações subsequentes com o mesmo `core_subject` apenas atualizam `last_seen_at` sem duplicar linhas na tabela de projeção.
