# Relatório de Gestão - Revisão do Roadmap ProjectV2 nº 7

Data: 14/07/2026

Project: SICODE Ecosystem | Foundation & SP Starter

Owner: BiGSerial

ProjectV2: nº 7

## 1. Status Executivo

O cronograma do ProjectV2 nº 7 foi revisado para corrigir a premissa da frente SICODE SP.

A versão anterior tratava a adaptação do SICODE Legado para São Paulo como se fosse necessário adaptar ou reimplementar individualmente fluxos, regras e atividades do sistema. Essa premissa foi substituída pela diretriz correta:

> O SICODE Legado continuará utilizando o código existente. A base São Paulo deve fornecer estruturas compatíveis com as tabelas atualmente consumidas. O trabalho técnico deve validar compatibilidade, configurar a instância SP, executar testes e corrigir somente incompatibilidades efetivamente demonstradas.

Com essa correção, a frente SICODE SP foi reduzida para uma janela plausível de julho a outubro de 2026. O horizonte macro do Project passou a encerrar em dezembro de 2026, justificado por CORE, SICODESK, validação integrada e fechamento da primeira etapa, não pela adaptação SP.

## 2. Premissa Corrigida

### Premissa anterior

A premissa anterior indicava necessidade de adaptar individualmente despacho, andamento, conclusão, confirmação, devoluções, retornos, métricas, regras de acesso e atividades do legado.

Essa leitura inflava artificialmente o prazo e transformava a preparação SP em uma reconstrução parcial do SICODE.

### Premissa vigente

A frente SP passa a seguir o princípio:

**Compatível por padrão. Corrigir somente o que quebrar.**

A fonte São Paulo deve entregar, diretamente ou por camada de compatibilidade, estruturas equivalentes às atualmente consumidas pelo SICODE:

- `tbld_usr_baseEP`
- `tbld_usr_baseOV`
- `tbld_usr_baseOrdens`
- `tbld_usr_baseOperacoes`

Essas estruturas devem preservar nomes esperados ou compatibilidade equivalente, colunas obrigatórias, tipos, formatos, dados necessários às rotinas existentes e frequência operacional de atualização.

## 3. Alterações Executadas no ProjectV2

Foram removidos do Project os itens criados sob a premissa incorreta.

Resumo operacional:

- 84 issues antigas foram fechadas como `not planned`.
- 84 itens antigos foram removidos do ProjectV2 nº 7.
- 46 novas issues foram criadas para o roadmap revisado.
- 7 novas macroentregas foram criadas.
- 39 novas sub-issues foram criadas.
- Nenhum campo novo foi criado no ProjectV2.
- Nenhuma opção nova de campo foi criada.
- Nenhum responsável foi atribuído sem evidência.

As issues antigas fechadas correspondem ao intervalo `#43` a `#126`.

As novas issues criadas correspondem ao intervalo `#127` a `#172`.

## 4. Novo Roadmap Programático

### Foundation e Governança Técnica

Período: 06/07/2026 a 31/07/2026

Objetivo: consolidar fundação técnica, contratos arquiteturais e governança mínima do ecossistema.

Status no Project: mantido.

### SICODE CORE MVP

Período: 20/07/2026 a 30/10/2026

Objetivo: entregar a fundação funcional de identidade, autorização e HUB do SICODE Ecosystem.

Status no Project: mantido.

Justificativa: desenvolvimento independente e necessário para identidade, autorização e integração entre aplicações.

### SICODESK MVP Funcional

Período: 03/08/2026 a 20/11/2026

Objetivo: entregar primeira versão funcional, auditável e integrada ao CORE para sistema de tickets.

Status no Project: mantido.

Justificativa: desenvolvimento paralelo e independente da complexidade da frente SP.

### Validar Contrato de Dados SP

Issue pai: `#127`

Período: 15/07/2026 a 14/08/2026

Objetivo: validar que a fonte São Paulo entrega estruturas equivalentes às consumidas pelo SICODE Legado.

Itens principais:

- Mapear fonte consolidada SP.
- Validar `tbld_usr_baseEP`.
- Validar `tbld_usr_baseOV`.
- Validar `tbld_usr_baseOrdens`.
- Validar `tbld_usr_baseOperacoes`.
- Validar colunas obrigatórias.
- Validar tipos e formatos.
- Validar frequência de atualização.
- Disponibilizar dados SP para desenvolvimento.
- Representar risco externo de disponibilidade da fonte SP.

### Configurar SICODE Legado para SP

Issue pai: `#138`

Período: 17/08/2026 a 28/08/2026

Objetivo: configurar a instância SP do SICODE Legado para consumir a fonte compatível sem reescrever rotinas existentes.

Itens principais:

- Preparar configuração isolada SP.
- Configurar conexão com fonte SP.
- Configurar banco operacional SP.
- Validar bootstrap da aplicação.
- Validar storage e configurações específicas da instância.
- Executar sincronização inicial.

### Testes de Compatibilidade SP

Issue pai: `#145`

Período: 31/08/2026 a 18/09/2026

Objetivo: executar testes de compatibilidade e regressão sobre o código existente do SICODE Legado usando os dados SP.

Itens principais:

- Executar smoke test da aplicação.
- Validar ingestão EP.
- Validar ingestão OV.
- Validar ingestão Ordens.
- Validar ingestão Operações.
- Executar regressão dos fluxos existentes.
- Validar atividades disponíveis no legado.
- Validar jobs e schedules aplicáveis.
- Registrar incompatibilidades encontradas.

Observação: validar atividades disponíveis no legado representa execução de matriz de testes. Não representa tarefa antecipada de desenvolvimento para cada atividade.

### Correções de Compatibilidade SP

Issue pai: `#155`

Período: 21/09/2026 a 09/10/2026

Objetivo: reservar janela de contingência para corrigir somente incompatibilidades efetivamente demonstradas pelos testes.

Regra de governança: não foram criadas sub-issues de correção antecipada. Issues específicas devem ser abertas apenas quando um teste demonstrar incompatibilidade real.

Exemplos de evidência necessária:

- incompatibilidade de tipo de dado;
- coluna com semântica divergente;
- regra dependente de característica exclusiva do ES;
- consulta com premissa incompatível com SP;
- falha de sincronização;
- diferença de atualização da fonte.

### Homologação SICODE SP

Issue pai: `#156`

Período: 12/10/2026 a 30/10/2026

Objetivo: homologar a operação SP após validação do contrato de dados, configuração da instância e testes de compatibilidade.

Itens principais:

- Executar regressão final.
- Validar atualização recorrente da base.
- Validar estabilidade das sincronizações.
- Executar homologação operacional.
- Corrigir bloqueadores.
- Preparar liberação da instância SP.

### Validação Integrada do Ecossistema

Issue pai: `#163`

Período: 23/11/2026 a 04/12/2026

Objetivo: validar integração mínima entre CORE, SICODESK e SICODE SP após estabilização dos MVPs paralelos.

Justificativa para ocorrer após outubro: depende da conclusão do SICODESK MVP, planejada até 20/11/2026.

### Fechamento da Primeira Etapa

Issue pai: `#169`

Período: 07/12/2026 a 18/12/2026

Objetivo: consolidar documentação operacional, checklist de homologação corporativa e aceite da primeira etapa.

Justificativa para ocorrer após outubro: trata-se de fechamento macro do ecossistema, não de adaptação SP.

## 5. Novo Prazo Consolidado

Frente SICODE SP:

```text
15/07/2026 a 30/10/2026
```

Project macro:

```text
06/07/2026 a 18/12/2026
```

Janeiro de 2027 foi removido do roadmap ativo.

## 6. Risco Externo

Risco registrado:

```text
#137 - Risco externo: disponibilidade efetiva da fonte SP
```

Esse risco representa que a frente SP depende da entrega de uma fonte São Paulo utilizável e compatível com o contrato esperado pelo SICODE.

Impacto potencial:

- atraso na configuração da instância SP;
- impossibilidade de executar sincronização inicial;
- bloqueio dos testes de compatibilidade;
- postergação da homologação SP.

Mitigação:

- validar contrato de dados antecipadamente;
- explicitar incompatibilidades de estrutura, tipo, formato ou atualização;
- abrir issues específicas somente quando uma incompatibilidade real for demonstrada.

## 7. Justificativa para Entregas Após Outubro de 2026

O prazo da frente SICODE SP termina em 30/10/2026.

As entregas posteriores existem por motivos independentes:

- SICODESK MVP segue planejado até 20/11/2026.
- A validação integrada do ecossistema depende de CORE, SICODESK e SICODE SP minimamente estabilizados.
- O fechamento da primeira etapa exige documentação operacional, checklist de homologação corporativa e aceite.

Portanto, dezembro de 2026 permanece como horizonte macro do Project por integração e fechamento do ecossistema, não por adaptação do legado para São Paulo.

## 8. Validação Final

Validação executada no ProjectV2 nº 7:

- Total de itens ativos no Project: 87.
- Itens antigos `#43` a `#126` remanescentes no Project: 0.
- Issues antigas fechadas como `not planned`: 84.
- Itens sem `Status`: 0.
- Itens sem `Start date`: 0.
- Itens sem `Target date`: 0.
- Itens sem `Workstream`: 0.
- Itens sem `Phase`: 0.
- Itens sem `Priority`: 0.
- Todos os itens ativos permanecem em `Todo`.

Distribuição atual por workstream:

- `CROSS / FOUNDATION`: 18 itens.
- `SICODE CORE`: 15 itens.
- `SICODESK`: 18 itens.
- `SICODE SP STARTER`: 36 itens.

Distribuição atual por prioridade:

- `Critical`: 36 itens.
- `High`: 51 itens.

## 9. Mensagem Para Gestão

O cronograma foi corrigido para refletir a estratégia adequada de implantação SP: manter o SICODE Legado operando sobre estruturas compatíveis, validar a base São Paulo e corrigir apenas incompatibilidades reais.

Essa revisão elimina a leitura de reconstrução do legado, reduz a frente SP para julho a outubro de 2026 e preserva dezembro de 2026 apenas como horizonte de integração e fechamento do ecossistema.

O principal risco externo é a disponibilidade efetiva da fonte São Paulo no contrato esperado. Esse risco agora está explicitamente rastreado no Project.
