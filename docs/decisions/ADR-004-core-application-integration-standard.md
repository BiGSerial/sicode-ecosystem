# ADR-004: Padrão Normativo de Integração de Aplicações no SICODE CORE

Data: 2026-07-21  
Status: Aceita  

---

## Contexto

O SICODE CORE é a autoridade canônica e soberana de identidade, autenticação e autorização no ecossistema. Com a evolução da plataforma, diversas aplicações (como o SICODESK, o SICODE ES/SP e futuras aplicações web/serviços) precisam integrar-se ao CORE para autorizar o acesso de usuários e obter dados organizacionais.

Sem um padrão normativo explícito e obrigatório, corria-se o risco de:
- Aplicações consumidoras criarem autenticações paralelas por senha;
- Acesso direto e acoplado ao banco de dados PostgreSQL do CORE por aplicações consumidoras;
- Proliferação de soluções ad-hoc incompatíveis de gestão de identidade e sessão;
- Quebra de isolamento entre a stack do CORE (Laravel 13/Livewire 4) e a de consumidores (Laravel 10, Spring Boot, Node.js, etc.).

---

## Decisão

Fica estabelecido o **Padrão Normativo de Integração de Aplicações no SICODE CORE** (`SICODE CORE Application Integration Standard`), documentado em `docs/architecture/core-application-integration-standard.md`.

As principais decisões normativas incluem:

1. **Obrigatoriedade do Application Launch Protocol**: Toda aplicação web interativa DEVE utilizar o `Application Launch Protocol` para autorizar a entrada de usuários vindos do CORE Hub.
2. **Troca Backend-to-Backend Atômica**: A resolução do `launch_code` retornado ao navegador DEVE ocorrer exclusivamente por comunicação direta backend-to-backend entre o servidor da aplicação consumidora e o endpoint `/api/v1/launch/exchange` do CORE.
3. **Independência Tecnológica**: A integração é totalmente neutra quanto a linguagem, framework ou arquitetura interna do consumidor.
4. **Projeção Local de Identidade Mínima**: O consumidor DEVE manter uma tabela de projeção local (ex: `core_identity_links`) vinculando o `core_subject` (UUID v4) à sua conta local. E-mail ou login local NÃO DEVEM ser usados como chave soberana de vínculo.
5. **Proibição Absoluta de Conexão Direta ao Banco do CORE**: É estritamente proibido que aplicações consumidoras leiam, gravem ou compartilhem conexões com o banco de dados PostgreSQL do CORE.
6. **Desconfiança do Navegador & Fail-Closed**: O consumidor DEVE desconfiar de qualquer dado vindo do navegador, validar `iss`, `application`, `context` e `state`, e falhar com negação de acesso (fail-closed) perante qualquer inconsistência ou timeout.

---

## Alternativas Rejeitadas

1. **Acesso Direto ao Banco do CORE via Read-Replica / Multi-Database Query**:
   - *Motivo da Rejeição*: Viola o princípio de desacoplamento e limites de aplicação, acopla a estrutura física de tabelas do CORE aos consumidores e impede a evolução independente dos esquemas de banco.
2. **Autenticação Direta por Formular de Senha na Aplicação Consumidora**:
   - *Motivo da Rejeição*: Duplica o risco de segurança, obriga o consumidor a manipular senhas e quebra a autoridade soberana do CORE.
3. **Passagem de Tokens de Identidade / Claims pela Query String do Navegador**:
   - *Motivo da Rejeição*: Extremamente vulnerável a vazamento em logs de servidor web, histórico de navegador, referers e interceptação por scripts maliciosos.

---

## Consequências

- **Positivas**:
  - Garantia de que qualquer aplicação (independente de stack) pode integrar-se de forma consistente e segura ao ecossistema.
  - As aplicações consumidoras mantêm total autonomia sobre seus dados operacionais sem acoplar-se ao banco do CORE.
  - Segurança reforçada através de trocas backend-to-backend, códigos de uso único, segredos fora do cliente e logs sanitizados.
- **Custos / Requisitos**:
  - Exige que todas as novas aplicações construam a camada anticorrupção de troca (`CoreIntegration`) e gerenciem sua própria tabela de projeção local e sessão HTTP.
  - Exige o cadastro formal prévio no CORE de credenciais de cliente (`client_identifier` e `client_secret`) e URLs de callback para cada ambiente.

---

## Documentos Relacionados
- [Documento Normativo do Padrão de Integração](file:///home/will/code/ecosystem/docs/architecture/core-application-integration-standard.md)
- [ADR-001: Autoridade de Identidade CORE](file:///home/will/code/ecosystem/docs/decisions/ADR-001-core-identity-authority-and-legacy-transition.md)
- [ADR-002: Protocolo de Lançamento CORE](file:///home/will/code/ecosystem/docs/decisions/ADR-002-core-launch-protocol-and-legacy-consumer.md)
