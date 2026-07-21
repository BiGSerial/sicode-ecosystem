# Checklist de Conformidade de Integração com o SICODE CORE

Data: 21/07/2026  
Status: Normativo  

Este checklist DEVE ser utilizado por revisores de código, arquitetos e equipes de homologação antes de aprovar a integração de qualquer aplicação consumidora com o SICODE CORE.

---

## 1. Cadastro e Gestão de Credenciais no CORE
- [ ] A aplicação possui `application_code` único e cadastrado na tabela `applications` do CORE.
- [ ] Todos os contextos necessários (`context_code`) estão registrados no CORE.
- [ ] O `client_identifier` foi provisionado especificamente para o ambiente de destino (Staging / Produção).
- [ ] O `client_secret` foi gerado com alta entropia (mínimo 64 caracteres) e está armazenado exclusivamente nas variáveis de ambiente do servidor.
- [ ] NENHUM `client_secret` está commitado em repositórios Git ou arquivos de código-fonte.
- [ ] A URL de callback registrada no CORE corresponde exatamente à URL exposta pelo consumidor.

---

## 2. Protocolo de Lançamento e Troca (Exchange)
- [ ] A recepção do callback trata adequadamente os parâmetros `code` e `state`.
- [ ] A troca do `launch_code` ocorre obrigatoriamente por chamada HTTP POST backend-to-backend para `/api/v1/launch/exchange`.
- [ ] O parâmetro `state` enviado na troca backend-to-backend é exatamente igual ao `state` recebido no callback.
- [ ] O consumidor valida se o `iss` retornado é exatamente igual ao `CORE_BASE_URL` configurado.
- [ ] O consumidor valida se o campo `application` no payload retornado corresponde ao seu `application_code`.
- [ ] O consumidor desconfia de qualquer dado vindo do navegador e não altera identidades sem validação no exchange.

---

## 3. Projeção Local de Identidade e Organização
- [ ] A aplicação consumidora possui estrutura de projeção local (ex: `core_identity_links`).
- [ ] A chave primária de vínculo do usuário é o `core_subject` (UUID v4 opaco).
- [ ] O vínculo entre `core_subject` e usuário local obedece à restrição de unicidade (no máximo 1:1 por runtime).
- [ ] Alterações futuras de e-mail ou nome não alteram o `core_subject` vinculado nem sobrescrevem o vínculo local de outro usuário.
- [ ] A aplicação valida e vincula o `core_organization_id` quando o contrato exige organização.

---

## 4. Gestão de Sessão Local
- [ ] A sessão da aplicação consumidora é regenerada (`invalidateAndRegenerateId`) imediatamente após validar a troca.
- [ ] Os cookies de sessão local possuem os atributos `HttpOnly` e `Secure` (em HTTPS).
- [ ] O logout na aplicação consumidora encerra a sessão local e invalida os cookies de sessão local.
- [ ] A aplicação consumidora não realiza chamadas SQL diretas ao banco de dados do CORE para verificar a sessão a cada requisição HTTP.

---

## 5. Segurança, Observabilidade e Logs
- [ ] Todas as chamadas backend-to-backend utilizam HTTPS/TLS 1.2+.
- [ ] O `client_secret` NUNCA é impresso em logs de aplicação ou arquivos de servidor web.
- [ ] O `launch_code` NUNCA é registrado em arquivos de log.
- [ ] Falhas na troca backend-to-backend, timeouts ou erros TLS resultam em negação imediata de acesso (*fail-closed*).
- [ ] A aplicação consumidora implementa timeout apropriado (ex: 5.0 segundos) na chamada HTTP para o CORE.

---

## 6. Testes e Resiliência
- [ ] Foram executados testes de contrato validando a deserialização do payload de exchange.
- [ ] Foi executado teste de tentativa de reuso de `launch_code` (Replay) confirmando a rejeição pelo CORE.
- [ ] Foi executado teste de timeout de conexão confirmando que a aplicação consumidora não entra em estado inconsistente.
