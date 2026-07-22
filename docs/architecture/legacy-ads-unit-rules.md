# Regras de Arquitetura e Unidade para o Domínio de ADS (SICODE Legacy)

## 1. Mapeamento do Domínio Real

No SICODE Legacy, o domínio de **ADS** (Arquivos de Dados de Serviços / Informe de Obra ADS) gerencia o ciclo de entrega de formulários e arquivos digitais de medição de obras por empreiteiras/parceiros.

Sua arquitetura é fundamentada nas tabelas:
- `adsforms`: Armazena o formulário ADS vinculado ao informe de obra (`work_reports`) e nota (`notes`).
- `adsforms_files`: Tabela pivot de ligação com arquivos anexos (`files`).
- `ads_requests`: Registra solicitações de processamento/geração de ADS de lote.

---

## 2. Abstrações de Arquitetura Multiunidade

Para manter uma única base de código operando as instâncias **ES** e **SP** com comportamentos e políticas específicas, o sistema utiliza a seguinte hierarquia de abstrações:

1. **Capability (`ads.delivery`)**:
   - Registrada em `UnitCapability::ADS_DELIVERY`.
   - Controla a habilitação/desabilitação funcional no backend e nos componentes Livewire via `UnitCapabilities->require(UnitCapability::ADS_DELIVERY)`.

2. **Contexto Empresarial Operacional (`AdsCompanyContext`)**:
   - Resolve a empresa operacional corrente (`currentCompanyId()`).
   - No **SP** (`provisioning`): Obriga um `CurrentCompanyContext` estabelecido pela projeção `CoreOrganizationLink` vinda do Launch CORE. Rejeita o uso ou persistência de UUIDs do CORE em colunas locais `company_id`.
   - No **ES** (`reconciliation`): Permite fallback controlado para `users.company_id` quando o contexto de Launch não está ativo.
   - Revalida o pertencimento da nota (`validateNoteAccess`) em cada busca ou mutação de dados.

3. **Política de Submissão (`AdsSubmissionPolicy`)**:
   - Interface `App\Contracts\AdsSubmissionPolicy` com injeção de dependência no container Laravel por unidade:
     - `EsAdsSubmissionPolicy` para a unidade ES.
     - `SpAdsSubmissionPolicy` para a unidade SP.
   - **Regras no ES**: Preserva verificações de histórico legado (`OldAds`) e validação de informe de obra.
   - **Regras no SP**: Exige que exista uma Ordem (`Order`) ativa (status diferente de `ENT%` e `ENC%`), obriga contexto empresarial ativo via `AdsCompanyContext` e revalida autorização em cada operação.

---

## 3. Proteções em Componentes Livewire

O componente `ReceiveAdsfomrm` (Partner ADS Form):
- Não confia em propriedades públicas empresariais vindas do navegador.
- Revalida a empresa ativa e a política de submissão `AdsSubmissionPolicy` nos métodos `search()`, `getNote()`, `toSave()` e `save()`.
- Impede a submissão cruzada entre empresas distintas.

---

## 4. Estratégia de Testes e Garantia de Qualidade

- **Testes de Unidade e Funcionais (`AdsDomainUnitRulesTest`)**:
  - Validam a inalterabilidade do escopo empresarial via navegador.
  - Rejeitam UUIDs do CORE em `company_id`.
  - Comprovam a injeção dinâmica de `EsAdsSubmissionPolicy` no ES e `SpAdsSubmissionPolicy` no SP.
  - Verificam a regra de ordem ativa exigida especificamente na unidade SP.
- **Harness E2E (`make legacy-sp-e2e`)**:
  - Valida a integração real HTTP do CORE até o estabelecimento da sessão e autorização de contexto no Legacy SP.
