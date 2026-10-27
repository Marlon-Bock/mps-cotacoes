---
title: "Fiança Locatícia PJ (Aluguel Pessoa Jurídica)"
url: "https://605e94abf8ab.us.kongportals.com/apis/aluguel-pj/docs/aluguel-pj"
image: "https://605e94abf8ab.us.kongportals.com/_og/d/c_Ocean.takumi,title_~RmlhbsOnYSBMb2NhdMOtY2lhIFBKIChBbHVndWVsIFBlc3NvYSBKdXLDrWRpY2Ep,props_eyJ0aGVtZSI6eyJjb2xvcnMiOnsicHJpbWFyeSI6IiNGMzcwMjEifX19,p_Ii9hcGlzL2FsdWd1ZWwtcGovZG9jcy9hbHVndWVsLXBqIg,s_rTAmHK32SBZpTqBn.png"
---

# Fiança Locatícia PJ (Aluguel Pessoa Jurídica)

## [Aluguel - Pessoa Jurídica](#aluguel-pessoa-jurídica)

O **Seguro Aluguel PJ** é a modalidade Fiança Locatícia da Pottencial destinada a locatários **Pessoa Jurídica (CNPJ)**, plano **Tradicional**. Ele substitui o fiador e outras garantias na locação de imóveis comerciais e residenciais, protegendo o proprietário em caso de inadimplência.

As APIs da Pottencial permitem que sua empresa realize cotações, reanálises, propostas e emissões de apólices para locatários PJ de forma integrada, rápida e segura.

O fluxo para emissão de uma apólice está descrito nos passos abaixo:

-   **Passo 1 — Autenticação:** Gere um token de autenticação a partir de sua chave de acesso. Esse token será necessário em todas as demais operações.
-   **Passo 2 — Criar cotação:** Envie os dados do imóvel, locatário (CNPJ) e coberturas. O processamento é assíncrono; o resultado é enviado para a `callbackUrl` informada.
-   **Passo 3 — Consultar cotação:** Verifique o status da cotação via callback ou por polling. Quando o status for `Approved`, a cotação está aprovada e pronta para os próximos passos.
-   **Passo 4 — Atualizar cotação** _(se necessário)_: Caso precise ajustar dados (coberturas, vigência, participantes), atualize a cotação antes da emissão. Após a atualização, aguarde o novo resultado e confirme o status `Approved`.
-   **Passo 5 — Consultar condições de pagamento:** Consulte as opções de parcelamento e formas de pagamento disponíveis para a cotação aprovada.
-   **Passo 6 — Emitir proposta/apólice:** Transmita a proposta com a forma de pagamento e número de parcelas desejados para gerar a apólice.

---

## [Pré-requisitos](#pré-requisitos)

Antes de começar a utilizar a API, é fundamental compreender os requisitos de autenticação e obter as credenciais necessárias.

Certifique-se de revisar a seção de [autenticação](https://605e94abf8ab.us.kongportals.com/auth) para garantir uma integração segura e bem-sucedida.

**Base URL (Sandbox):** `https://api-hml.pottencial.com.br`

---

## [Cotações](#cotações)

### [Criar cotação](#criar-cotação)

Cria uma nova cotação para o produto Fiança Locatícia — Plano Tradicional PJ (CNPJ).

O processamento é **assíncrono**: a resposta imediata confirma apenas o recebimento com `201` e retorna um `id` (requestId). O resultado da análise é enviado para a `callbackUrl` informada na requisição.

> **Importante:** O campo `planType` deve ser obrigatoriamente `"Traditional"`. O locatário principal deve ser Pessoa Jurídica (CNPJ com 14 dígitos).

**Requisição (Request)**

| Campo  | Valor                                                           |
| :----- | :-------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Quotes |
| Método | POST                                                            |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

Body

```json
{
  "dateInsuranceStart": "2026-09-01T00:00:00",
  "dateInsuranceEnd": "2027-09-01T00:00:00",
  "dateRentalStart": "2026-09-01T00:00:00",
  "dateRentalEnd": "2027-09-01T00:00:00",
  "planType": "Traditional",
  "rentalType": "Commercial",
  "isShoppingCenter": false,
  "callbackUrl": "https://sua-api.com/callback/cotacao",
  "policyOwner": {
    "name": "Imobiliaria Exemplo",
    "document": "12345678000195",
    "commissionPercentage": 0.05
  },
  "broker": {
    "name": "Corretora Exemplo LTDA",
    "document": "12345678000195",
    "commissionPercentage": 0.1
  },
  "coBrokers": [
    {
      "name": "Corretora Exemplo LTDA",
      "document": "12345678000195",
      "commissionPercentage": 0.1
    }
  ],
  "tenants": [
    {
      "type": "Principal",
      "name": "Empresa Locatária LTDA",
      "document": "98765432000188",
      "email": "contato@empresa-locataria.com.br",
      "phoneNumber": "31999990000",
      "address": {
        "zipCode": "30130010",
        "street": "Rua Exemplo",
        "number": "100",
        "complement": "Sala 1",
        "district": "Centro",
        "city": "Belo Horizonte",
        "uf": "MG"
      }
    }
  ],
  "insureds": [
    {
      "name": "Locatário Seguro LTDA",
      "document": "06979397078",
      "email": "contato@pessoa-locataria.com.br",
      "address": {
        "zipCode": "30130010",
        "street": "Rua Exemplo",
        "number": "100",
        "complement": "Sala 1",
        "district": "Centro",
        "city": "Belo Horizonte",
        "uf": "MG"
      }
    }
  ],
  "address": {
    "zipCode": "30130010",
    "street": "Avenida do Comércio",
    "number": "500",
    "complement": "Loja 2",
    "district": "Centro",
    "city": "Belo Horizonte",
    "uf": "MG"
  },
  "coverages": [
    {
      "type": "BasicRental",
      "multiple": 3,
      "value": 5000.0
    },
    {
      "type": "Condominium",
      "multiple": 3,
      "value": 500.0
    },
    {
      "type": "Iptu",
      "multiple": 3,
      "value": 1200.0
    },
    {
      "type": "Water",
      "multiple": 3,
      "value": 120.0
    },
    {
      "type": "Electricity",
      "multiple": 3,
      "value": 1000.0
    },
    {
      "type": "Gas",
      "multiple": 3,
      "value": 100.0
    },
    {
      "type": "PropertyDamage",
      "multiple": 3,
      "value": 5000.0
    },
    {
      "type": "Painting",
      "multiple": 3,
      "value": 5000.0
    },
    {
      "type": "FineForTermination",
      "multiple": 3,
      "value": 5000.0
    }
  ],
  "payment": {
    "type": "Bill",
    "installments": 12
  }
}
```

**Resposta (Response)**

| Código                    | Descrição                                                                      |
| :------------------------ | :----------------------------------------------------------------------------- |
| 201 Created               | Cotação aceita para processamento assíncrono. Retorna { "id": "<requestId>" }. |
| 400 Bad Request           | Dados inválidos. Retorna lista de erros de validação.                          |
| 500 Internal Server Error | Erro inesperado.                                                               |

**Regras e restrições:**

-   `planType` deve ser `"Traditional"`.
-   O locatário principal (`type: "Principal"`) deve ser PJ (CNPJ com 14 dígitos). Somente um locatário principal é permitido.
-   São permitidos até 5 locatários solidários (`type: "Solidary"`).
-   Para os locatários solidários são permitidas pessoas físicas e jurídicas.
-   A lista `coverages` deve conter exatamente uma cobertura do tipo `BasicRental`.
-   Coberturas `PromotionalFund` e `AirConditioning` são permitidas apenas quando `isShoppingCenter: true`.
-   Vigência mínima de 3 meses e máxima de 60 meses.
-   Demais tipos suportados: `Condominium`, `Iptu`, `Water`, `Electricity`, `Gas`, `PropertyDamage`, `Painting`, `FineForTermination`. Consulte a tabela **Tipos de cobertura** em [QuoteCoverageRequest](#quotecoveragerequest) para a lista completa.
-   Quando o contrato de locação for ≤ 60 meses, `dateInsuranceEnd` deve coincidir com `dateRentalEnd`.
-   A comissão total (corretor + co-corretores + estipulante) deve estar entre **5% e 40%**.
-   A forma `Invoice` (Fatura) só pode ser usada quando houver estipulante (`policyOwner`).
-   Não é permitido informar `policyOwner` (estipulante) e `realEstate` (imobiliária) simultaneamente.

---

### [Atualizar cotação](#atualizar-cotação)

Atualiza os dados de uma cotação existente. Utiliza o mesmo schema do corpo da criação (`QuoteRequest`).

> A cotação só pode ser atualizada enquanto a proposta/apólice não tiver sido emitida.

**Requisição (Request)**

| Campo  | Valor                                                                      |
| :----- | :------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Quotes/{quote_id} |
| Método | PUT                                                                        |

Parâmetros de Path

| Parâmetro | Tipo | Descrição                                  |
| :-------- | :--- | :----------------------------------------- |
| quote_id  | uuid | Identificador da cotação a ser atualizada. |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

Body

Mesmo schema de [Criar cotação](#criar-cota%C3%A7%C3%A3o) (`QuoteRequest`).

**Resposta (Response)**

| Código                    | Descrição                                              |
| :------------------------ | :----------------------------------------------------- |
| 201 Created               | Cotação atualizada com sucesso. Retorna QuoteResponse. |
| 400 Bad Request           | Dados inválidos.                                       |
| 500 Internal Server Error | Erro inesperado.                                       |

---

### [Consultar cotação](#consultar-cotação)

Retorna os dados completos de uma cotação, incluindo status, coberturas, limites disponíveis e informações dos participantes.

**Requisição (Request)**

| Campo  | Valor                                                                      |
| :----- | :------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Quotes/{quote_id} |
| Método | GET                                                                        |

Parâmetros de Path

| Parâmetro | Tipo | Descrição                                                  |
| :-------- | :--- | :--------------------------------------------------------- |
| quote_id  | uuid | Identificador da cotação (requestId retornado na criação). |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

**Status possíveis:**

| Status          | Descrição                                                             |
| :-------------- | :-------------------------------------------------------------------- |
| InAnalysis      | Cotação em análise manual pela mesa de subscrição.                    |
| Approved        | Cotação aprovada. Disponível para emissão de proposta.                |
| PreApproved     | Aprovado com pendências. Deve seguir via portal para regularização.   |
| Refused         | Cotação recusada. Possível solicitar reanálise.                       |
| Pending         | Cotação com pendências. Verificar statusDescription para orientações. |
| WaitingEmission | Proposta transmitida, aguardando emissão da apólice.                  |
| Emitted         | Apólice emitida.                                                      |
| Expired         | Cotação expirada.                                                     |

**Exemplo de Response:**

```json
{
  "id": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "quoteNumber": "000123",
  "dateInsuranceStart": "2026-09-01T00:00:00",
  "dateInsuranceEnd": "2027-09-01T00:00:00",
  "dateRentalStart": "2026-09-01T00:00:00",
  "dateRentalEnd": "2027-09-01T00:00:00",
  "status": "Approved",
  "statusDescription": null,
  "availableLimit": 45000.0,
  "maximumLimit": 60000.0,
  "commercialPremium": 1200.0,
  "totalPremium": 1278.0,
  "iof": 78.0,
  "installmentValue": 106.5,
  "planType": "Traditional",
  "rentalType": "Commercial",
  "isShoppingCenter": false,
  "callbackUrl": "https://sua-api.com/callback/cotacao",
  "policyOwner": {
    "name": "Imobiliária LTDA",
    "document": "12345678000195",
    "commissionPercentage": 0.0
  },
  "broker": {
    "name": "Corretora Exemplo LTDA",
    "document": "12345678000195",
    "commissionPercentage": 0.2
  },
  "coBrokers": [],
  "tenants": [
    {
      "type": "Principal",
      "name": "Empresa Locatária LTDA",
      "document": "98765432000188",
      "email": "contato@empresa-locataria.com.br",
      "phoneNumber": "31999990000",
      "address": {
        "zipCode": "30130010",
        "street": "Rua Exemplo",
        "number": "100",
        "complement": "Sala 1",
        "district": "Centro",
        "city": "Belo Horizonte",
        "uf": "MG"
      }
    }
  ],
  "insureds": [],
  "address": {
    "zipCode": "30130010",
    "street": "Avenida do Comércio",
    "number": "500",
    "complement": "Loja 2",
    "district": "Centro",
    "city": "Belo Horizonte",
    "uf": "MG"
  },
  "coverages": [
    {
      "type": "BasicRental",
      "multiple": 3,
      "lmi": 15000.0,
      "premium": 1200.0,
      "value": 5000.0
    },
    {
      "type": "Condominium",
      "multiple": 3,
      "lmi": 1500.0,
      "premium": 150.0,
      "value": 500.0
    },
    {
      "type": "Iptu",
      "multiple": 3,
      "lmi": 3600.0,
      "premium": 130.0,
      "value": 1200.0
    },
    {
      "type": "Water",
      "multiple": 3,
      "lmi": 360.0,
      "premium": 12.0,
      "value": 120.0
    },
    {
      "type": "Electricity",
      "multiple": 3,
      "lmi": 3000.0,
      "premium": 300.0,
      "value": 1000.0
    },
    {
      "type": "Gas",
      "multiple": 3,
      "lmi": 300.0,
      "premium": 50.0,
      "value": 100.0
    },
    {
      "type": "PropertyDamage",
      "multiple": 3,
      "lmi": 15000.0,
      "premium": 1200.0,
      "value": 5000.0
    },
    {
      "type": "Painting",
      "multiple": 3,
      "lmi": 15000.0,
      "premium": 1200.0,
      "value": 5000.0
    },  
    {
      "type": "FineForTermination",
      "multiple": 3,
      "lmi": 15000.0,
      "premium": 1200.0,
      "value": 5000.0
    }
  ],
  "payment": {
    "type": "Bill",
    "installments": 12,
    "firstDueDate": null
  }
}
```

**Resposta (Response)**

| Código                    | Descrição                                  |
| :------------------------ | :----------------------------------------- |
| 200 OK                    | Cotação encontrada. Retorna QuoteResponse. |
| 404 Not Found             | Cotação não encontrada.                    |
| 500 Internal Server Error | Erro inesperado.                           |

> **Nota sobre comissões:** Os percentuais de comissão são retornados em base decimal (ex.: `0.20` = 20%).

---

### [Consultar documento da cotação (PDF)](#consultar-documento-da-cotação-pdf)

Retorna o PDF da cotação em Base64. Disponível apenas para cotações com status `Approved`.

**Requisição (Request)**

| Campo  | Valor                                                                          |
| :----- | :----------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Quotes/{quote_id}/pdf |
| Método | GET                                                                            |

Parâmetros de Path

| Parâmetro | Tipo | Descrição                 |
| :-------- | :--- | :------------------------ |
| quote_id  | uuid | Identificador da cotação. |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

**Resposta (Response)**

| Código                    | Descrição                                              |
| :------------------------ | :----------------------------------------------------- |
| 200 OK                    | PDF gerado com sucesso. Retorna { "pdf": "<base64>" }. |
| 400 Bad Request           | Cotação em status não permitido para geração do PDF.   |
| 404 Not Found             | Cotação não encontrada.                                |
| 500 Internal Server Error | Erro inesperado.                                       |

**Status que permitem geração do PDF:**

| Status                                             | PDF disponível?                           |
| :------------------------------------------------- | :---------------------------------------- |
| Approved                                           | Sim                                       |
| Demais status (Refused, InAnalysis, Pending, etc.) | Não — retorna 400 com mensagem descritiva |

---

### [Consultar carta de aprovação/recusa](#consultar-carta-de-aprovaçãorecusa)

Retorna a carta de análise (aprovação ou recusa) da cotação PJ em formato PDF (Base64). Disponível para cotações com status `Approved` (carta de aprovação) ou `Refused` (carta de recusa).

**Requisição (Request)**

| Campo  | Valor                                                                            |
| :----- | :------------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Policy/Subscription?requestId={id} |
| Método | GET                                                                              |

Parâmetros de Query

| Parâmetro | Tipo | Descrição                                                  |
| :-------- | :--- | :--------------------------------------------------------- |
| requestId | uuid | Identificador da cotação (requestId retornado na criação). |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

**Status que permitem geração da carta:**

| Status                                                              | Carta gerada                              |
| :------------------------------------------------------------------ | :---------------------------------------- |
| Approved                                                            | Carta de aprovação                        |
| Refused                                                             | Carta de recusa                           |
| Expired, WaitingEmission, Emitted, Pending, InAnalysis, PreApproved | Não — retorna 400 com mensagem descritiva |

> **Importante:** Este endpoint aplica a rota PJ apenas quando `planType = "Traditional"` e o locatário principal possui **CNPJ**. Para planos não-Tradicional, utiliza a rota legada.

**Resposta (Response)**

| Código                    | Descrição                                                               |
| :------------------------ | :---------------------------------------------------------------------- |
| 200 OK                    | Carta gerada com sucesso. Retorna { "pdf": "<base64>" }.                |
| 400 Bad Request           | RequestId inválido, tipo de locatário inválido ou status não permitido. |
| 404 Not Found             | Cotação não encontrada ou PDF vazio retornado.                          |
| 500 Internal Server Error | Erro inesperado.                                                        |

---

### [Solicitar reanálise](#solicitar-reanálise)

Solicita a reanálise de uma cotação PJ junto à Mesa de Subscrição. O processamento é **assíncrono** e o resultado é enviado para a `callbackUrl`.

**Requisição (Request)**

| Campo  | Valor                                                                            |
| :----- | :------------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Quotes/reanalysis/{quote_id} |
| Método | PUT                                                                              |

Parâmetros de Path

| Parâmetro | Tipo | Descrição                                   |
| :-------- | :--- | :------------------------------------------ |
| quote_id  | uuid | Identificador da cotação a ser reanalisada. |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

Body (`QuoteReanalysisRequest`)

| Campo              | Tipo         | Obrigatório | Descrição                                                                        |
| :----------------- | :----------- | :---------- | :------------------------------------------------------------------------------- |
| requestId          | uuid         | Sim         | Identificador da cotação.                                                        |
| dateInsuranceStart | datetime     | Sim         | Início da vigência do seguro.                                                    |
| dateInsuranceEnd   | datetime     | Sim         | Fim da vigência do seguro.                                                       |
| dateRentalStart    | datetime     | Sim         | Início do contrato de locação.                                                   |
| dateRentalEnd      | datetime     | Sim         | Fim do contrato de locação.                                                      |
| policyOwner        | QuoteParty   | Não         | Estipulante.                                                                     |
| address            | QuoteAddress | Sim         | Endereço do local de risco.                                                      |
| reason             | string       | Condicional | Motivo da reanálise. Obrigatório para cotações aprovadas: AVALIACAO_TAXA ou AUMENTO_LIMITE. |
| rentalType         | integer      | Sim         | Tipo de imóvel (1 = Residential, 2 = Commercial).                                |
| isShoppingCenter   | boolean      | Não         | Indica se é shopping center.                                                     |
| tenants            | array        | Sim         | Lista de locatários.                                                             |
| coverages          | array        | Sim         | Lista de coberturas com nova proposta de valores.                                |
| notes              | string       | Não         | Observações adicionais.                                                          |
| files              | array        | Sim         | Arquivos de suporte: fileName e fileContent (Base64). Formatos aceitos: .pdf, .jpg, .png; máx. 10 MB cada. Mínimo 1 arquivo. Ex.: Extrato bancário e imposto de renda. |
| callbackUrl        | string       | Sim         | URL para receber o resultado da reanálise.                                       |

```json
{
  "requestId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "dateInsuranceStart": "2026-09-01T00:00:00",
  "dateInsuranceEnd": "2027-09-01T00:00:00",
  "dateRentalStart": "2026-09-01T00:00:00",
  "dateRentalEnd": "2027-09-01T00:00:00",
  "policyOwner": {
    "name": "Imobiliária LTDA",
    "document": "12345678000195",
    "commissionPercentage": 0.0
  },
  "address": {
    "zipCode": "32676592",
    "street": "RUA RIACHUELO",
    "number": "100",
    "complement": "",
    "district": "LARANJEIRAS",
    "city": "BETIM",
    "uf": "MG"
  },
  "reason": "AVALIACAO_TAXA",
  "tenants": [
    {
      "type": "Principal",
      "name": "Empresa Locatária LTDA",
      "phoneNumber": "31988997766",
      "document": "98765432000188",
      "email": "contato@empresa-locataria.com.br",
      "address": {
        "zipCode": "32676592",
        "street": "RUA RIACHUELO",
        "number": "100",
        "complement": "",
        "district": "LARANJEIRAS",
        "city": "BETIM",
        "uf": "MG"
      }
    }
  ],
  "coverages": [
    {
      "type": "BasicRental",
      "multiple": 3,
      "value": 5000.0
    },
    {
      "type": "Condominium",
      "multiple": 3,
      "value": 500.0
    },
    {
      "type": "Iptu",
      "multiple": 3,
      "value": 1200.0
    },
    {
      "type": "Water",
      "multiple": 3,
      "value": 120.0
    },
    {
      "type": "Electricity",
      "multiple": 3,
      "value": 1000.0
    },
    {
      "type": "Gas",
      "multiple": 3,
      "value": 100.0
    },
    {
      "type": "PropertyDamage",
      "multiple": 3,
      "value": 5000.0
    },
    {
      "type": "Painting",
      "multiple": 3,
      "value": 5000.0
    },  
    {
      "type": "FineForTermination",
      "multiple": 3,
      "value": 5000.0
    }
  ],
  "rentalType": 1,
  "isShoppingCenter": false,
  "notes": "Observações adicionais para a mesa de subscrição.",
  "files": [
    {
      "fileName": "documento-suporte.pdf",
      "fileContent": "<base64>"
    }
  ],
  "callbackUrl": "https://sua-api.com/callback/reanalise"
}
```

**Resposta (Response)**

| Código                    | Descrição                                                          |
| :------------------------ | :----------------------------------------------------------------- |
| 201 Created               | Reanálise solicitada com sucesso. Retorna { "id": "<requestId>" }. |
| 400 Bad Request           | Dados inválidos, cotação inelegível ou arquivos inválidos.         |
| 500 Internal Server Error | Erro inesperado.                                                   |

**Regras e restrições:**

-   A reanálise está disponível para cotações com status `Approved`, `Refused`.
-   Não é possível solicitar reanálise enquanto houver outra em andamento.
-   Para motivo `AVALIACAO_TAXA`: a soma dos valores das coberturas não pode ultrapassar o limite aprovado original.
-   Para motivo `AUMENTO_LIMITE`: valores podem exceder o limite; encaminhado para avaliação.
-   Para cotações **recusadas**: o motivo deve ser `ANALISE_RECUSADA`.
-   Pelo menos 1 arquivo deve ser enviado.
-   Ao obter a cotação, caso esteja com o status **Pending** ou **PreApproved** as orientações estarão disponíveis no campo `statusDescription` e a cotação deverá seguir pelo **Portal do Parceiro**.
-   Caso a cotação não esteja apta para uma solicitação de reanálise, será retornado o status **400 Bad Request**, com a seguite mensagem: `Não é possível solicitar reanálise para essa cotação`

---

### [Baixar documento de minuta](#baixar-documento-de-minuta)

Retorna a minuta da cotação em formato PDF (Base64).

**Requisição (Request)**

| Campo  | Valor                                                                            |
| :----- | :------------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Quotes/{quote_id}/draft |
| Método | GET                                                                              |

Parâmetros de Path

| Parâmetro | Tipo | Descrição                 |
| :-------- | :--- | :------------------------ |
| quote_id  | uuid | Identificador da cotação. |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

**Resposta (Response)**

| Código                    | Descrição                                                    |
| :------------------------ | :----------------------------------------------------------- |
| 200 OK                    | Minuta retornada com sucesso. Retorna { "pdf": "<base64>" }. |
| 400 Bad Request           | Parâmetros inválidos.                                        |
| 401 Unauthorized          | Token ausente ou inválido.                                   |
| 404 Not Found             | Cotação não encontrada.                                      |
| 500 Internal Server Error | Erro inesperado.                                             |

---

### [Consultar condições de pagamento](#consultar-condições-de-pagamento)

Retorna as opções de parcelamento e formas de pagamento disponíveis para uma cotação aprovada.

> Disponível apenas para cotações em status elegível (com valores calculados, ex.: `Approved`). A forma `Invoice` (Fatura) só aparece quando houver estipulante (`policyOwner`) vinculado à cotação.

**Requisição (Request)**

| Campo  | Valor                                                                            |
| :----- | :------------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Quotes/{quote_id}/payment-simulation |
| Método | GET                                                                              |

Parâmetros de Path

| Parâmetro | Tipo | Descrição                 |
| :-------- | :--- | :------------------------ |
| quote_id  | uuid | Identificador da cotação. |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

**Exemplo de Response:**

```json
{
  "requestId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "totalPremium": 1278.0,
  "premium": 1200.0,
  "totalComission": 240.0,
  "installments": [
    {
      "paymentWay": "Bill",
      "installmentValue": 106.5,
      "installmentAmount": 12
    },
    {
      "paymentWay": "Bill",
      "installmentValue": 213.0,
      "installmentAmount": 6
    }
  ]
}
```

**Resposta (Response)**

| Código                    | Descrição                                      |
| :------------------------ | :--------------------------------------------- |
| 200 OK                    | Condições de pagamento retornadas com sucesso. |
| 400 Bad Request           | Parâmetros inválidos.                          |
| 404 Not Found             | Cotação não encontrada.                        |
| 500 Internal Server Error | Erro inesperado.                               |

---

## [Propostas PJ](#propostas-pj)

### [Realizar emissão de proposta/apólice](#realizar-emissão-de-propostaapólice)

Transmite a proposta e emite a apólice para uma cotação PJ aprovada. O processamento é **síncrono**: a resposta retorna o identificador da proposta criada.

> A cotação deve estar com status `Approved` (aprovada manualmente ou pelo motor).

**Requisição (Request)**

| Campo  | Valor                                                                 |
| :----- | :-------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Proposals/pj |
| Método | POST                                                                  |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

Body (`ProposalPjRequest`)

```json
{
  "quoteId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "paymentType": "Bill",
  "installments": 12,
  "firstDueDate": "2026-09-05T00:00:00",
  "insureds": [
    {
      "name": "João da Silva",
      "documentNumber": "12345678901",
      "email": "joao.silva@proprietario.com.br",
      "type": 1,
      "address": {
        "zipCode": "30130010",
        "street": "Rua do Proprietário",
        "number": "200",
        "complement": "",
        "district": "Centro",
        "city": "Belo Horizonte",
        "uf": "MG"
      }
    }
  ],
  "callbackUrl": "https://sua-api.com/callback/proposta"
}
```

**Resposta (Response)**

| Código                    | Descrição                                                                        |
| :------------------------ | :------------------------------------------------------------------------------- |
| 201 Created               | Proposta transmitida com sucesso. Retorna { "id": "<proposalId>" }.              |
| 400 Bad Request           | Dados inválidos — status da cotação inelegível, parcelas inválidas, forma de pagamento inválida, etc. |
| 500 Internal Server Error | Erro inesperado.                                                                 |

**Regras e restrições:**

-   A cotação deve estar com status `Approved`.
-   `paymentType` aceito: `"Bill"` (Boleto) ou `"Invoice"` (Fatura).
-   Para `Bill`: `firstDueDate` é obrigatória e deve estar entre hoje e hoje + 30 dias.
-   Para `Invoice`: requer estipulante (`policyOwner`) vinculado à cotação; `firstDueDate` é ignorado.
-   O número de parcelas (`installments`) não pode exceder o máximo permitido pelo motor para a vigência.
-   A lista `insureds` é obrigatória e não pode ser vazia. Cada segurado deve ter nome, e-mail válido, documento (CPF/CNPJ) e endereço.
-   O endereço de cada segurado deve conter: CEP, logradouro (máx. 200 caracteres), bairro (máx. 100 caracteres). Complemento é opcional (máx. 50 caracteres).
-   Em todos os cenários (sucesso, subscrição reprovada ou erro), o serviço notifica a `callbackUrl` com o resultado final.

---

### [Consultar documento da proposta](#consultar-documento-da-proposta)

Retorna o documento da proposta/apólice PJ em formato PDF (Base64).

**Requisição (Request)**

| Campo  | Valor                                                                            |
| :----- | :------------------------------------------------------------------------------- |
| URL    | https://api-hml.pottencial.com.br/partners/rentalbond/v1/Proposals/pj/{quote_id}/document |
| Método | GET                                                                              |

Parâmetros de Path

| Parâmetro | Tipo | Descrição                                      |
| :-------- | :--- | :--------------------------------------------- |
| quote_id  | uuid | Identificador da cotação associada à proposta. |

Headers

| Campo        | Descrição                          |
| :----------- | :--------------------------------- |
| client_id    | Client ID da App.                  |
| access_token | Token de acesso gerado para a App. |

**Resposta (Response)**

| Código                    | Descrição                                                       |
| :------------------------ | :-------------------------------------------------------------- |
| 200 OK                    | Documento retornado com sucesso. Retorna { "pdf": "<base64>" }. |
| 400 Bad Request           | Parâmetros inválidos.                                           |
| 404 Not Found             | Proposta não encontrada.                                        |
| 500 Internal Server Error | Erro inesperado.                                                |

---

## [CallBack](#callback)

### [URL de CallBack](#url-de-callback)

Existe a possibilidade de configurar um URL de callback para que, a cada alteração no status da cotação, reanálise ou emissão, seja disponibilizada uma resposta informando o andamento do processo.

A URL de callback deverá ser informada na requisição de criação da cotação no campo `callbackUrl`.

**Status de callback**

| Código           | Descrição                               |
| :--------------- | :-------------------------------------- |
| 0 - Error        | Caso ocorra algum erro no processo.     |
| 1 - Approved     | Cotação/Análise aprovada.               |
| 2 - Refused      | Cotação/Análise reprovada.              |
| 3 - InAnalysis   | Cotação/Análise encontra-se em análise. |
| 4 - Pending      | Cotação/Análise pendente.               |
| 30 - PreApproved | Cotação/Análise pré-aprovada.           |
| 55 - Emitted     | Apólice emitida.                        |

**Exemplo de Response sem erro:**

```json
{
  "requestId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "status": 1
}
```

**Exemplo de Response com erro:**

```json
{
  "requestId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "status": 0,
  "errorMessage" {
    "code" : "10",
    "message" : "mensagem de erro."
  }
}
```

**Exemplo de Response com pendencia:**

```json
{
  "requestId": "3fa85f64-5717-4562-b3fc-2c963f66afa6",
  "status": 4,
  "observation": "Existe pendencia de documentos."
}
```

## [Dicionário de Schemas](#dicionário-de-schemas)

### [QuoteRequest](#quoterequest)

Requisição para criação ou atualização de cotação PJ.

| Campo              | Tipo                        | Obrigatório | Descrição                                                                        |
| :----------------- | :-------------------------- | :---------- | :------------------------------------------------------------------------------- |
| dateInsuranceStart | datetime                    | Sim         | Início da vigência do seguro. Não pode ser anterior a hoje nem superior a hoje + 30 dias. |
| dateInsuranceEnd   | datetime                    | Sim         | Fim da vigência do seguro. Período máximo de 60 meses a contar do início de vigência. |
| dateRentalStart    | datetime                    | Sim         | Início do contrato de locação.                                                   |
| dateRentalEnd      | datetime                    | Sim         | Fim do contrato de locação.                                                      |
| planType           | string                      | Sim         | Tipo de plano. Deve ser "Traditional".                                           |
| rentalType         | string                      | Sim         | Tipo de imóvel: "Residential" ou "Commercial".                                   |
| isShoppingCenter   | boolean                     | Não         | Indica se o imóvel está em shopping center.                                      |
| callbackUrl        | string                      | Sim         | URL para receber o resultado da análise.                                         |
| policyOwner        | QuoteParty                  | Não         | Estipulante. Mutuamente exclusivo com realEstate.                                |
| broker             | QuoteParty                  | Sim         | Corretora. CNPJ válido; comissão entre 5% e 40%.                                 |
| coBrokers          | array<QuoteParty>           | Não         | Co-corretoras.                                                                   |
| realEstate         | QuoteParty                  | Não         | Imobiliária. CNPJ válido. Mutuamente exclusivo com policyOwner.                  |
| tenants            | array<QuoteTenantItem>      | Sim         | Lista de locatários. Deve conter exatamente 1 Principal (CNPJ); até 5 Solidários (PF e/ou PJ). |
| insureds           | array<QuoteInsuredItem>     | Não         | Lista de segurados. Máximo 10.                                                   |
| address            | QuoteAddress                | Sim         | Endereço do imóvel (local de risco).                                             |
| coverages          | array<QuoteCoverageRequest> | Sim         | Lista de coberturas. Obrigatória 1 BasicRental.                                  |
| payment            | QuotePaymentInfo            | Sim         | Dados de pagamento.                                                              |

### [QuoteParty](#quoteparty)

| Campo                | Tipo   | Descrição                           |
| :------------------- | :----- | :---------------------------------- |
| name                 | string | Nome da parte.                      |
| document             | string | CPF ou CNPJ (somente dígitos).      |
| commissionPercentage | number | Percentual de comissão (mínimo 5%). |

### [QuoteTenantItem](#quotetenantitem)

| Campo       | Tipo         | Descrição                  |
| :---------- | :----------- | :------------------------- |
| type        | string       | "Principal" ou "Solidary". |
| name        | string       | Nome do locatário.         |
| document    | string       | CNPJ (locatário PJ).       |
| email       | string       | E-mail.                    |
| phoneNumber | string       | Telefone.                  |
| address     | QuoteAddress | Endereço do locatário.     |

### [QuoteInsuredItem](#quoteinsureditem)

| Campo    | Tipo         | Descrição             |
| :------- | :----------- | :-------------------- |
| name     | string       | Nome do segurado.     |
| document | string       | CPF ou CNPJ.          |
| email    | string       | E-mail.               |
| address  | QuoteAddress | Endereço do segurado. |

### [QuoteAddress](#quoteaddress)

| Campo      | Tipo   | Descrição               |
| :--------- | :----- | :---------------------- |
| zipCode    | string | CEP.                    |
| street     | string | Logradouro.             |
| number     | string | Número.                 |
| complement | string | Complemento (opcional). |
| district   | string | Bairro.                 |
| city       | string | Cidade.                 |
| uf         | string | Estado (UF).            |

### [QuoteCoverageRequest](#quotecoveragerequest)

| Campo    | Tipo    | Descrição                                         |
| :------- | :------ | :------------------------------------------------ |
| type     | string  | Tipo da cobertura. Ver tabela Tipos de cobertura. |
| multiple | integer | Múltiplo do aluguel.                              |
| value    | number  | Valor mensal do aluguel para a cobertura.         |

**Tipos de cobertura (`type`):**

| Valor (string)     | Código | Descrição                     | Múltiplo (Min/Max) | Observações                            |
| :----------------- | :----- | :---------------------------- | :----------------- | :------------------------------------- |
| BasicRental        | 1      | Aluguel                       | 3/45               | Obrigatória. Exatamente 1 por cotação. |
| Condominium        | 2      | Condomínio                    | 1/45               |                                        |
| Iptu               | 3      | IPTU                          | 1/45               |                                        |
| Water              | 4      | Água                          | 1/45               |                                        |
| Electricity        | 5      | Luz                           | 1/45               |                                        |
| Gas                | 6      | Gás                           | 1/45               |                                        |
| PromotionalFund    | 7      | Fundo de Promoção             | 1/45               | Somente quando isShoppingCenter: true. |
| AirConditioning    | 8      | Ar Condicionado               | 1/45               | Somente quando isShoppingCenter: true. |
| PropertyDamage     | 9      | Danos ao Imóvel               | 1/6                |                                        |
| Painting           | 10     | Pintura Interna e Externa     | 1/3                |                                        |
| FineForTermination | 11     | Multa por Rescisão Contratual | 1/3                |                                        |

### [QuoteCoverageResponse](#quotecoverageresponse)

| Campo    | Tipo    | Descrição                                         |
| :------- | :------ | :------------------------------------------------ |
| type     | string  | Tipo da cobertura. Ver tabela Tipos de cobertura. |
| multiple | integer | Múltiplo do aluguel.                              |
| lmi      | number  | Limite Máximo de Indenização.                     |
| premium  | number  | Prêmio da cobertura.                              |
| value    | number  | Valor do aluguel.                                 |

### [QuotePaymentInfo](#quotepaymentinfo)

| Campo        | Tipo     | Descrição                                                  |
| :----------- | :------- | :--------------------------------------------------------- |
| type         | string   | Forma de pagamento: "Bill" (Boleto) ou "Invoice" (Fatura). |
| firstDueDate | datetime | Data do primeiro vencimento (obrigatória para Boleto).     |
| installments | integer  | Número de parcelas.                                        |

### [QuoteResponse](#quoteresponse)

| Campo              | Tipo             | Descrição                                                          |
| :----------------- | :--------------- | :----------------------------------------------------------------- |
| id                 | uuid             | Identificador único da cotação.                                    |
| quoteNumber        | string           | Número sequencial da cotação.                                      |
| dateInsuranceStart | datetime         | Início da vigência.                                                |
| dateInsuranceEnd   | datetime         | Fim da vigência.                                                   |
| dateRentalStart    | datetime         | Início do contrato de locação.                                     |
| dateRentalEnd      | datetime         | Fim do contrato de locação.                                        |
| status             | string           | Status atual (ver tabela de status).                               |
| statusDescription  | string?          | Mensagem descritiva. Preenchida apenas para PreApproved e Pending. |
| availableLimit     | decimal          | Limite disponível = Limite - (Acúmulo + Σ LMIs). Mínimo 0.         |
| maximumLimit       | decimal          | Limite máximo = Limite - Acúmulo. Mínimo 0.                        |
| commercialPremium  | decimal          | Prêmio líquido (sem IOF).                                          |
| totalPremium       | decimal          | Prêmio total (com IOF).                                            |
| iof                | decimal          | Valor do IOF.                                                      |
| installmentValue   | decimal          | Valor da parcela mensal.                                           |
| hasCCGCondition    | boolean?         | Indica condicionante CCG.                                          |
| policyOwner        | QuoteParty?      | Estipulante (null se não houver).                                  |
| broker             | QuoteParty       | Corretora.                                                         |
| coBrokers          | array            | Co-corretoras.                                                     |
| realEstate         | QuoteParty?      | Imobiliária (null se não houver).                                  |
| tenants            | array            | Locatários.                                                        |
| insureds           | array            | Segurados.                                                         |
| address            | QuoteAddress     | Endereço do imóvel.                                                |
| coverages          | array            | Coberturas.                                                        |
| payment            | QuotePaymentInfo | Dados de pagamento.                                                |
| planType           | string           | Tipo do plano.                                                     |
| rentalType         | string           | Tipo de imóvel.                                                    |
| isShoppingCenter   | boolean          | Indica se é shopping center.                                       |
| callbackUrl        | string           | URL de callback informada na criação.                              |

### [QuoteReanalysisRequest](#quotereanalysisrequest)

| Campo              | Tipo                       | Obrigatório | Descrição                                                                        |
| :----------------- | :------------------------- | :---------- | :------------------------------------------------------------------------------- |
| requestId          | uuid                       | Sim         | Identificador da cotação.                                                        |
| dateInsuranceStart | datetime                   | Sim         | Início da vigência.                                                              |
| dateInsuranceEnd   | datetime                   | Sim         | Fim da vigência.                                                                 |
| dateRentalStart    | datetime                   | Sim         | Início do contrato de locação.                                                   |
| dateRentalEnd      | datetime                   | Sim         | Fim do contrato de locação.                                                      |
| address            | QuoteAddress               | Sim         | Endereço do local de risco.                                                      |
| reason             | string                     | Condicional | Motivo: "AVALIACAO_TAXA" ou "AUMENTO_LIMITE". Obrigatório para cotações aprovadas. |
| rentalType         | integer                    | Sim         | Tipo de imóvel (1 = Residential, 2 = Commercial).                                |
| isShoppingCenter   | boolean                    | Não         | Shopping center.                                                                 |
| tenants            | array                      | Sim         | Locatários.                                                                      |
| coverages          | array                      | Sim         | Coberturas.                                                                      |
| notes              | string                     | Não         | Observações.                                                                     |
| files              | array<QuoteReanalysisFile> | Sim         | Arquivos em Base64 (fileName + fileContent). Formatos: .pdf, .jpg, .png; mínimo 1; máx. 10 MB cada. |
| callbackUrl        | string                     | Sim         | URL de callback.                                                                 |

### [ProposalPjRequest](#proposalpjrequest)

| Campo        | Tipo                       | Obrigatório | Descrição                                                                        |
| :----------- | :------------------------- | :---------- | :------------------------------------------------------------------------------- |
| quoteId      | uuid                       | Sim         | Identificador da cotação aprovada.                                               |
| paymentType  | string                     | Sim         | Forma de pagamento: "Bill" ou "Invoice".                                         |
| installments | integer                    | Sim         | Número de parcelas (≥ 1).                                                        |
| firstDueDate | datetime                   | Condicional | Data do 1º vencimento. Obrigatória para Bill; deve estar entre hoje e hoje + 30 dias. |
| insureds     | array<ProposalInsuredItem> | Sim         | Lista de segurados. Não pode ser vazia.                                          |
| callbackUrl  | string                     | Não         | URL de callback para o resultado.                                                |

### [ProposalInsuredItem](#proposalinsureditem)

| Campo          | Tipo         | Obrigatório | Descrição                                        |
| :------------- | :----------- | :---------- | :----------------------------------------------- |
| name           | string       | Sim         | Nome do segurado.                                |
| documentNumber | string       | Sim         | CPF (PF) ou CNPJ (PJ), somente dígitos.          |
| email          | string       | Sim         | E-mail válido.                                   |
| type           | integer      | Sim         | Tipo do segurado.                                |
| address        | QuoteAddress | Sim         | Endereço (CEP, logradouro, bairro obrigatórios). |

### [PdfResponse](#pdfresponse)

| Campo | Tipo   | Descrição                                   |
| :---- | :----- | :------------------------------------------ |
| pdf   | string | Conteúdo do documento codificado em Base64. |

### [PaymentSimulationResponse](#paymentsimulationresponse)

| Campo          | Tipo                                | Descrição                           |
| :------------- | :---------------------------------- | :---------------------------------- |
| requestId      | uuid                                | Identificador da cotação.           |
| totalPremium   | number                              | Prêmio total (com IOF).             |
| premium        | number                              | Prêmio líquido (sem IOF).           |
| totalComission | number                              | Comissão total.                     |
| installments   | array<PaymentSimulationInstallment> | Opções de parcelamento disponíveis. |

### [PaymentSimulationInstallment](#paymentsimulationinstallment)

| Campo             | Tipo    | Descrição                               |
| :---------------- | :------ | :-------------------------------------- |
| paymentWay        | string  | Forma de pagamento ("Bill", "Invoice"). |
| installmentValue  | number  | Valor de cada parcela.                  |
| installmentAmount | integer | Número de parcelas.                     |

### [ErrorResult](#errorresult)

| Campo  | Tipo                | Descrição                      |
| :----- | :------------------ | :----------------------------- |
| errors | array<ErrorMessage> | Lista de erros da solicitação. |

### [ErrorMessage](#errormessage)

| Campo   | Tipo   | Descrição                        |
| :------ | :----- | :------------------------------- |
| code    | string | Código de identificação do erro. |
| message | string | Descrição legível do erro.       |

### [CallBackResponse](#callbackresponse)

| Campo        | Tipo         | Descrição                                                                 |
| :----------- | :----------- | :------------------------------------------------------------------------ |
| RequestId    | guid         | Id da cotação.                                                            |
| Status       | integer      | Codigo do status.                                                         |
| Observation  | string       | Observação, esse campo geralmente será preenchido para os códigos 4 e 30. |
| ErrorMessage | ErrorMessage | Erros na solicitação, será preenchido quando o código for 0.              |

---

## [Códigos de Retorno](#códigos-de-retorno)

| Código                    | Descrição                                                                        |
| :------------------------ | :------------------------------------------------------------------------------- |
| 200 OK                    | Requisição bem-sucedida.                                                         |
| 201 Created               | Recurso criado ou aceito para processamento assíncrono.                          |
| 400 Bad Request           | Dados da requisição inválidos. Verifique os erros retornados em errors.          |
| 401 Unauthorized          | Token de acesso ausente, inválido ou expirado.                                   |
| 403 Forbidden             | Parceiro não possui permissão para realizar a operação.                          |
| 404 Not Found             | Recurso não encontrado.                                                          |
| 500 Internal Server Error | Erro interno. Tente novamente mais tarde ou contate o suporte técnico da Pottencial. |

**Estrutura de erro:**

```json
{
  "errors": [
    {
      "code": "VALIDATION_ERROR",
      "message": "O campo planType é obrigatório."
    }
  ]
}
```