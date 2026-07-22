# Inventário Funcional e Empresarial do Domínio de ADS (SICODE Legacy)

## 1. Visão Geral do Domínio

O domínio de **ADS (Arquivos de Dados de Serviços / Informe de Obra ADS)** é responsável pela recepção, validação, registro e controle do envio de formulários e anexos de medição/documentação de serviços realizados por empreiteiras/parceiros.

O fluxo é composto por:
- **`adsforms`**: Registro do formulário ADS vinculado a um Informe de Obra (`work_reports`) e a uma Nota (`notes`), contendo informações de contrato, centro, depósito, valor e status de entrega (incluindo controle tácito).
- **`adsforms_files`**: Tabela pivot que vincula a `adsform` aos arquivos físicos (`files`).
- **`ads_requests`**: Tabela de solicitações/pedidos de geração e processamento de ADS (utilizada em automações, retentativas e integrações).

---

## 2. Inventário de Símbolos, Componentes e Rotas

| Arquivo / Símbolo | Rota / Local | Tabela | Leitura/Escrita | Origem Empresa | Origem Contrato | Regra ES | Regra SP | Risco |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| `App\Models\Adsform` | `app/Models/Adsform.php` | `adsforms` | Leitura / Escrita | Via `notes.company_id` / `work_reports.company_id` | `adsforms.contract` | Registro de ADS com histórico legado (`OldAds`) | Registro via `CurrentCompanyContext` da projeção CORE | Incompatibilidade de empresa se `user.company_id` for assumido |
| `App\Models\AdsRequest` | `app/Models/AdsRequest.php` | `ads_requests` | Leitura / Escrita | `ads_requests.company_id` (FK `companies.id`) | Via `note_id -> notes` | Fila de requisições legada | `company_id` derivado da empresa vinculada na projeção CORE | Persistência indevida de UUID CORE em `company_id` |
| `App\Http\Controllers\PartnerController::sendAdsForm()` | `/company/construction/partner/send-adsform` | N/A | Leitura (View) | `CurrentCompanyContext` | N/A | Exibe `partner.adsform` | Requer middleware `current.company` e capability | Acesso sem empresa contexto estabelecida |
| `App\Http\Livewire\Partner\Forms\ReceiveAdsfomrm` | `app/Http/Livewire/Partner/Forms/ReceiveAdsfomrm.php` | `adsforms`, `files`, `adsforms_files`, `notes` | Leitura / Escrita | `CurrentCompanyContext` / `note.company_id` | `note.contract` / Excel | Permite busca por nota e envio de Excel + arquivos | Revalida empresa corrente no `save()` e impede envio cruzado | Mutação confiando em propriedade pública ou empresa do browser |
| `App\Http\Livewire\Engineers\Ads\Dashboard` | `app/Http/Livewire/Engineers/Ads/Dashboard.php` | `adsforms`, `work_reports` | Leitura | `work_reports.company_id` | N/A | Visão administrativa de engenharia | Filtro por empresa do contexto ativado | Exposição de dados de outras empresas |

---

## 3. Relações com Empresa e Contrato

1. **Empresa Proprietária do Registro**:
   - A empresa vinculada à Nota (`notes.company_id`) e ao Informe de Obra (`work_reports.company_id`).
   - Em `ads_requests`, a coluna `company_id` DEVE sempre referenciar a FK local `companies.id` (nunca o UUID da organização CORE).
2. **Empresa no Runtime Multiunidade**:
   - No **ES** (`reconciliation`): Derivada do login local / contratação histórica.
   - No **SP** (`provisioning`): Derivada exclusivamente da projeção local `CoreOrganizationLink` resolvida pelo `CurrentCompanyContext`.
3. **Contratos**:
   - A `Note` possui relacionamento com `Contract` (`notes.contract_id`).
   - O campo `adsforms.contract` armazena a identificação textual do contrato importada do arquivo de dados ou informada pelo parceiro.

---

## 4. Classificação das Diferenças ES / SP (Categorias de Mudança)

- **Comum (A)**: Estrutura de dados das tabelas `adsforms`, `adsforms_files`, `ads_requests`. Armazenamento de arquivos no disk e pivot com `files`.
- **Configurável (B)**: Prazo limite para reenvio ou entrega tácita, limite de tamanho de arquivos (configurado em `config/sicode.php`).
- **Capability (C)**: Capability `ads.delivery` para controlar acesso e autorização aos fluxos de entrega de ADS.
- **Policy / Context (D/E)**: Resolução de empresa em cada mutação do Livewire (`ReceiveAdsfomrm` / `AdsCompanyContext` / `CurrentCompanyContext`), garantindo que o envio de ADS seja revalidado contra a empresa local ativa e rejeite tentativas de falsificação de `company_id`.
