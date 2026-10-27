---
title: "Residencial"
url: "https://605e94abf8ab.us.kongportals.com/apis/residencial/docs/residencial"
image: "https://605e94abf8ab.us.kongportals.com/_og/d/c_Ocean.takumi,title_Residencial,props_eyJ0aGVtZSI6eyJjb2xvcnMiOnsicHJpbWFyeSI6IiNGMzcwMjEifX19,p_Ii9hcGlzL3Jlc2lkZW5jaWFsL2RvY3MvcmVzaWRlbmNpYWwi,s_qCEkxHiawp5XD3R2.png"
---

# Residencial

## [Residencial](#residencial)

O **Seguro Residencial** é a nossa solução para você que quer proteger e cuidar do seu lar! Com o seguro **Pottencial Residencial** você conta com assistência emergencial 24 horas. Afinal, situações inusitadas não acontecem apenas no horário comercial... Proporcionamos a você a facilidade de personalizar o seu seguro, com diversas opções de coberturas, de acordo com a sua realidade.

As APIs da Pottencial permitem que sua empresa realize cotações, propostas e emissões de apólices para os produtos da Pottencial de forma integrada, rápida e segura.

O fluxo para emissão de uma apólice está descrito em passos simples abaixo:

-   **Passo 1 - Autenticação:** Gere um token de autenticação a partir de sua chave de acesso. Esse token será necessário nas demais operações.
-   **Passo 2 - Solicitar cotação:** Envie os dados da cotação e receba os valores do prêmio para os dados informados.
-   **Passo 3 - Enviar proposta:** Quando a cotação estiver pronta, envie os dados para criar uma proposta.
-   **Passo 4 - Aceitar proposta:** Aceite a proposta criada.
-   **Passo 5 - Emitir apólice:** Com a proposta aceita, emita sua apólice.

Você pode testar agora as chamadas das APIs da Pottencial utilizando o Postman:

[Download Collection](https://static.pottencial.com.br/devportal/postman/pottencial-apis.postman_collection.json) [Environment Sandbox](https://static.pottencial.com.br/devportal/postman/pottencial.postman_environment.json)

## [Pré-requisitos](#pré-requisitos)

Antes de começar a utilizar a API, é fundamental compreender os requisitos de autenticação e obter as credenciais necessárias.

Certifique-se de revisar a seção de [autenticação](https://605e94abf8ab.us.kongportals.com/auth) para garantir uma integração segura e bem-sucedida.

## [Cotações](#cotações)

### [Solicitar cotação](#solicitar-cotação)

Permite solicitar uma nova cotação para um produto Pottencial.

Cada produto possui regras específicas de preenchimento, que estão detalhadas em cada um dos campos.

**Requisição (Request)**

| Tipo    | Campo                                                             | Descrição                          |
| :------ | :---------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/quotes |                                    |
| Method  | Post                                                              |                                    |
| Headers | client_id                                                         | Client ID da App.                  |
|         | access_token                                                      | Token de acesso gerado para a App. |
| Body    | Quote                                                             |                                    |

Exemplos Cotação para o produto Residencial Para cadastro de cotação para o produto Residencial é necessário informar um objeto de risco do tipo [Property](#property).

```json
{
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "discountPercentage": "0",
  "commercialLoadingFee": "0",
  "commissionedAgents": [
    {
      "documentNumber": "11111111111111",
      "role": "Broker",
      "lead": "true",
      "commissionPercentage": "0.15"
    },
    {
      "documentNumber": "22222222222222",
      "role": "PolicyOwner",
      "lead": "false"
    }
  ],
  "participants": [
    {
      "documentNumber": "33333333333",
      "role": "Beneficiary",
      "participationPercentage": "1.0",
      "type": "Corporate"
    },
    {
      "documentNumber": "44444444444",
      "role": "Insured",
      "isPayer": "true",
      "type": "Corporate",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    }
  ],
  "riskObjects": [
    {
      "type": "Property",
      "coverages": [
        {
          "key": "basica",
          "insuredAmount": "115000.00",
          "lmi": "115000.00"
        },
        {
          "key": "danos-eletricos",
          "insuredAmount": "2100.00",
          "lmi": "2100.00"
        },
        {
          "key": "impacto-veiculos",
          "insuredAmount": "4700.00",
          "lmi": "4700.00"
        },
        {
          "key": "perda-aluguel",
          "insuredAmount": "12600.00",
          "lmi": "12600.00"
        },
        {
          "key": "quebra-vidros",
          "insuredAmount": "12600.00",
          "lmi": "12600.00"
        },
        {
          "key": "roubo-furto",
          "insuredAmount": "2600.00",
          "lmi": "2600.00"
        },
        {
          "key": "ruptura",
          "insuredAmount": "2100.00",
          "lmi": "2100.00"
        },
        {
          "key": "vendaval",
          "insuredAmount": "4700.00",
          "lmi": "4700.00"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      },
      "paymentConditions": {
        "paymentType": "Boleto",
        "installments": "2"
      }
    },
    {
      "type": "Property",
      "coverages": [
        {
          "key": "rc-familiar",
          "insuredAmount": "115000.00",
          "lmi": "115000.00"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "documentNumber": "777777777777777",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    },
    {
      "type": "Property",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      },
      "coverages": [
        {
          "key": "basica",
          "insuredAmount": 3000000,
          "lmi": 3000000
        },
        {
          "key": "danos-eletricos",
          "insuredAmount": 1633.32,
          "lmi": 1633.32
        }
      ]
    }
  ]
}
```

###### Exemplos Cotação débito em Conta

```json
{
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "discountPercentage": 0,
  "commercialLoadingFee": 0,
  "commissionedAgents": [
    {
      "documentNumber": "11111111111111",
      "role": "Broker",
      "lead": true,
      "commissionPercentage": 0.15
    }
  ],
  "participants": [
    {
      "documentNumber": "33333333333",
      "role": "Beneficiary",
      "participationPercentage": 1.0
    },
    {
      "documentNumber": "44444444444",
      "role": "Insured",
      "isPayer": true,
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    }
  ],
  "riskObjects": [
    {
      "type": "Property",
      "coverages": [
        {
          "key": "basica",
          "insuredAmount": 115000.0
        }
      ],
      "insuredOwner": true,
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      },
      "paymentConditions": {
        "paymentType": "AutomaticDebit",
        "installments": 1
      }
    }
  ]
}
```

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Cotação cadastrada com sucesso.                                                  | Quote       |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Recurso não encontrado.                                                          | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

Exemplos Resposta da cotação para o produto Residencial O retorno da cotação traz o **quoteId** que identifica unicamente da cotação, além dos valores do prêmio bruto, comercial e iof.

```json
{
  "quoteId": "c03ab663-3d5c-4014-aa5e-cae727103392",
  "productKey": "residencial",
  "policyType": "Unique",
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "discountPercentage": "0",
  "commercialLoadingFee": "0",
  "createdAt": "2021-04-29T17:12:27.219Z",
  "status": "Approved",
  "commercialPremium": "109.98",
  "grossPremium": "99.99",
  "iof": "9.99",
  "commissionedAgents": [
    {
      "documentNumber": "11111111111111",
      "role": "Broker",
      "lead": "true",
      "commissionPercentage": "0.15",
      "commissionAmount": "9.99"
    },
    {
      "documentNumber": "22222222222222",
      "role": "PolicyOwner",
      "lead": "false"
    }
  ],
  "participants": [
    {
      "documentNumber": "33333333333",
      "role": "Beneficiary",
      "participationPercentage": "1.0"
    },
    {
      "documentNumber": "44444444444",
      "role": "Insured",
      "isPayer": "true",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    }
  ],
  "partners": [
    {
      "documentNumber": "33333333333",
      "role": "Advisor",
      "name": "parceiro 1"
    },
    {
      "documentNumber": "44444444444",
      "role": "CommercialAgent",
      "name": "parceiro 2"
    }
  ],
  "riskObjects": [
    {
      "type": "Property",
      "coverages": [
        {
          "key": "basica",
          "insuredAmount": "115000.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "danos-eletricos",
          "insuredAmount": "2100.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "impacto-veiculos",
          "insuredAmount": "4700.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "perda-aluguel",
          "insuredAmount": "12600.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "quebra-vidros",
          "insuredAmount": "12600.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "roubo-furto",
          "insuredAmount": "2600.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "ruptura",
          "insuredAmount": "2100.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "vendaval",
          "insuredAmount": "4700.00",
          "description": "Texto descritivo da franquia"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    },
    {
      "type": "Property",
      "coverages": [
        {
          "key": "rc-familiar",
          "insuredAmount": "115000.00",
          "description": "Texto descritivo da franquia"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "documentNumber": "777777777777777",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    }
  ]
}
```

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Cotação localizada com sucesso.                                                  | Quote       |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Recurso não encontrado.                                                          | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

Exemplos Resposta da cotação para o produto Residencial O retorno da cotação traz o **quoteId** que identifica unicamente da cotação, além dos valores do prêmio bruto, comercial e iof.

```json
{
  "quoteId": "c03ab663-3d5c-4014-aa5e-cae727103392",
  "productKey": "residencial",
  "policyType": "Unique",
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "discountPercentage": "0",
  "commercialLoadingFee": "0",
  "createdAt": "2021-04-29T17:12:27.219Z",
  "status": "Approved",
  "commercialPremium": "109.98",
  "grossPremium": "99.99",
  "iof": "9.99",
  "commissionedAgents": [
    {
      "documentNumber": "11111111111111",
      "role": "Broker",
      "lead": "true",
      "commissionPercentage": "0.15",
      "commissionAmount": "9.99"
    },
    {
      "documentNumber": "22222222222222",
      "role": "PolicyOwner",
      "lead": "false"
    }
  ],
  "participants": [
    {
      "documentNumber": "33333333333",
      "role": "Beneficiary",
      "participationPercentage": "1.0"
    },
    {
      "documentNumber": "44444444444",
      "role": "Insured",
      "isPayer": "true",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    }
  ],
  "partners": [
    {
      "documentNumber": "33333333333",
      "role": "Advisor",
      "name": "parceiro 1"
    },
    {
      "documentNumber": "44444444444",
      "role": "CommercialAgent",
      "name": "parceiro 2"
    }
  ],
  "riskObjects": [
    {
      "type": "Property",
      "coverages": [
        {
          "key": "basica",
          "insuredAmount": "115000.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "danos-eletricos",
          "insuredAmount": "2100.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "impacto-veiculos",
          "insuredAmount": "4700.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "perda-aluguel",
          "insuredAmount": "12600.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "quebra-vidros",
          "insuredAmount": "12600.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "roubo-furto",
          "insuredAmount": "2600.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "ruptura",
          "insuredAmount": "2100.00",
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "vendaval",
          "insuredAmount": "4700.00",
          "description": "Texto descritivo da franquia"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    },
    {
      "type": "Property",
      "coverages": [
        {
          "key": "rc-familiar",
          "insuredAmount": "115000.00",
          "description": "Texto descritivo da franquia"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "documentNumber": "777777777777777",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      }
    },
    {
      "type": "Property",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA",
        "type": "Residential"
      },
      "coverages": [
        {
          "key": "basica",
          "insuredAmount": 3000000,
          "description": "Texto descritivo da franquia"
        },
        {
          "key": "danos-eletricos",
          "insuredAmount": 1633.32,
          "description": "Texto descritivo da franquia"
        }
      ]
    }
  ]
}
```

### [Consultar documento da Cotação](#consultar-documento-da-cotação)

Essa operação permite consultar o documento a partir do **ID** da cotação.

**Requisição (Request)**

| Tipo    | Campo                                                                            | Descrição                          |
| :------ | :------------------------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/quotes/{quote_id}/draft |                                    |
| Method  | GET                                                                              |                                    |
| Headers | client_id                                                                        | Client ID da App.                  |
|         | access_token                                                                     | Token de acesso gerado para a App. |

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Documento localizado com sucesso.                                                | BLOB        |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Registro não encontrado.                                                         | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

### [Atualizar a forma de pagamento de uma cotação](#atualizar-a-forma-de-pagamento-de-uma-cotação)

Permite alterações na forma de pagamento de uma cotação já criada, desde que ainda não tenha sido emitida uma proposta correspondente. Após a emissão da proposta, a cotação não poderá ser alterada. Caso seja necessário fazer uma alteração cirar uma nova cotação.

\*\*Observação: \*\* Ao realizar a alteração das condições de pagamento, pode haver alterações nos valores da cotação.

Portanto, é importante validar os valores para informar os usuários sobre quaisquer alterações de preço.

**Requisição (Request)**

| Tipo    | Campo                                                                            | Descrição                          |
| :------ | :------------------------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/quotes/{quote_id}/payment-conditions |                                    |
| Method  | PUT                                                                              |                                    |
| Headers | client_id                                                                        | Client ID da App.                  |
|         | access_token                                                                     | Token de acesso gerado para a App. |
| Body    | QuotePayment                                                                     |                                    |

Exemplos Request do objeto para atualizar a forma de pagamento Atualizar a forma de pagamento de uma cotação.

```json
{
  "paymentType": "Boleto",
  "installments": "5"
}
```

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Atualizar forma de pagamento de uma cotação.                                     | Quote       |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Recurso não encontrado.                                                          | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

Exemplo

```json
{
  "quoteId": "c03ab663-3d5c-4014-aa5e-cae727103392",
  "productId": "b989e925-42c0-4f0c-87cc-872ddf73e542",
  "policyType": "Unique",
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "discountPercentage": "0",
  "commercialLoadingFee": "0",
  "createdAt": "2021-04-29T17:12:27.219Z",
  "status": "Approved",
  "commercialPremium": "109.98",
  "grossPremium": "99.99",
  "iof": "9.99",
  "commissionedAgents": [
    {
      "documentNumber": "11111111111111",
      "role": "Broker",
      "lead": "true",
      "commissionPercentage": "0.15",
      "commissionAmount": "9.99"
    },
    {
      "documentNumber": "22222222222222",
      "role": "PolicyOwner",
      "lead": "false"
    }
  ],
  "participants": [
    {
      "documentNumber": "33333333333",
      "role": "Beneficiary",
      "participationPercentage": "1.0"
    },
    {
      "documentNumber": "44444444444",
      "role": "Insured",
      "isPayer": "true",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA"
      }
    }
  ],
  "partners": [
    {
      "documentNumber": "33333333333",
      "role": "Advisor",
      "name": "parceiro 1"
    },
    {
      "documentNumber": "44444444444",
      "role": "CommercialAgent",
      "name": "parceiro 2"
    }
  ],
  "riskObjects": [
    {
      "type": "Property",
      "coverages": [
        {
          "key": "basica",
          "insuredAmount": "115000.00"
        },
        {
          "key": "danos-eletricos",
          "insuredAmount": "2100.00"
        },
        {
          "key": "impacto-veiculos",
          "insuredAmount": "4700.00"
        },
        {
          "key": "perda-aluguel",
          "insuredAmount": "12600.00"
        },
        {
          "key": "quebra-vidros",
          "insuredAmount": "12600.00"
        },
        {
          "key": "roubo-furto",
          "insuredAmount": "2600.00"
        },
        {
          "key": "ruptura",
          "insuredAmount": "2100.00"
        },
        {
          "key": "vendaval",
          "insuredAmount": "4700.00"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA"
      }
    },
    {
      "type": "Property",
      "coverages": [
        {
          "key": "rc-familiar",
          "insuredAmount": "115000.00"
        }
      ],
      "insuredOwner": "true",
      "propertyType": "Apartment",
      "constructionType": "Brick",
      "propertyUseType": "Usual",
      "documentNumber": "777777777777777",
      "address": {
        "street": "Av. Raja Gabáglia",
        "number": "1143",
        "district": "Luxemburgo",
        "city": "Belo Horizonte",
        "state": "MG",
        "zipCode": "30380403",
        "complement": "20º andar",
        "country": "BRA"
      }
    }
  ]
}
```

## [Propostas](#propostas)

### [Enviar proposta](#enviar-proposta)

Permite submeter uma nova proposta para um produto Pottencial. Para envio de uma proposta é necessário primeiramente ter realizado uma cotação a partir da API de cotação.

**Requisição (Request)**

| Tipo    | Campo                                                                | Descrição                          |
| :------ | :------------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/proposals |                                    |
| Method  | POST                                                                 |                                    |
| Headers | client_id                                                            | Client ID da App.                  |
|         | access_token                                                         | Token de acesso gerado para a App. |
| Body    | ProposalV1                                                           |                                    |

Exemplos Proposta com boleto Proposta com pagamento efetuado na cotação .

```json
{
  "quoteId": "ac721b29-0a5a-44b4-a205-b23288164f40",
  "policyPeriodStart": "2023-04-06T00:00:00.000Z",
  "policyPeriodEnd": "2024-04-06T00:00:00.000Z",
  "payment": {
    "firstInstallmentDueDateDelay": 1
  }
}
```

Proposta com débito em conta Proposta com pagamento efetuado na cotação .

```json
{
  "quoteId": "ac721b29-0a5a-44b4-a205-b23288164f40",
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "payment": {
    "firstDueDate": "2021-08-29",
    "automaticDebitAccount": {
      "bankCode": "001",
      "bankNumber": "5858",
      "accountNumber": "66544",
      "accountDigit": "0",
      "accountExtension": "",
      "accountType": "Current"
    }
  }
}
```

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Proposta submetida com sucesso.                                                  | Proposal    |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Recurso não encontrado.                                                          | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

Exemplos Resposta de proposta com débito em conta Resposta de proposta com pagamento parcelado em 12 vezes através de débito em conta.

```json
{
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "ac721b29-0a5a-44b4-a205-b23288164f40",
  "proposalNumber": "99999",
  "payment": {
    "paymentType": "AutomaticDebit",
    "installments": "12",
    "automaticDebitAccount": {
      "bankCode": "001",
      "bankNumber": "5858",
      "accountNumber": "66544",
      "accountDigit": "0",
      "accountExtension": "X",
      "documentNumber": "1111111111"
    }
  },
  "createdAt": "2021-08-03",
  "status": "Ready"
}
```

Resposta de proposta com boleto Resposta de proposta com pagamento à vista através de boleto, com vencimento em 7 dias.

```json
{
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "ac721b29-0a5a-44b4-a205-b23288164f40",
  "proposalNumber": "99999",
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "payment": {
    "paymentType": "Boleto",
    "installments": "1",
    "firstInstallmentDueDateDelay": "7"
  },
  "createdAt": "2021-08-03",
  "status": "Ready"
}
```

### [Aceitar proposta](#aceitar-proposta)

Permite aprovar uma proposta criada.

**Requisição (Request)**

| Tipo    | Campo                                                                            | Descrição                          |
| :------ | :------------------------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/proposals/{proposal_id}/accept |                                    |
| Method  | POST                                                                             |                                    |
| Headers | client_id                                                                        | Client ID da App.                  |
|         | access_token                                                                     | Token de acesso gerado para a App. |

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Proposta aceita com sucesso.                                                     |             |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Recurso não encontrado.                                                          | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

### [Consultar proposta](#consultar-proposta)

Essa operação permite consultar os dados de uma proposta a partir do **ID**. Algumas informações sensíveis de participantes e agentes comissionados são ofuscadas para atender a LGPD.

**Requisição (Request)**

| Tipo    | Campo                                                                            | Descrição                          |
| :------ | :------------------------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/proposals/{proposal_id} |                                    |
| Method  | GET                                                                              |                                    |
| Headers | client_id                                                                        | Client ID da App.                  |
|         | access_token                                                                     | Token de acesso gerado para a App. |

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Proposta localizada com sucesso.                                                 | Proposal    |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Recurso não encontrado.                                                          | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

Exemplos Resposta de proposta com débito em conta Resposta de proposta com pagamento parcelado em 12 vezes através de débito em conta.

```json
{
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "ac721b29-0a5a-44b4-a205-b23288164f40",
  "proposalNumber": "99999",
  "payment": {
    "paymentType": "AutomaticDebit",
    "installments": "12",
    "automaticDebitAccount": {
      "bankCode": "001",
      "bankNumber": "5858",
      "accountNumber": "66544",
      "accountDigit": "0",
      "accountExtension": "X",
      "documentNumber": "1111111111"
    }
  },
  "createdAt": "2021-08-03",
  "status": "Ready"
}
```

Resposta de proposta com boleto Resposta de proposta com pagamento à vista através de boleto, com vencimento em 7 dias.

```json
{
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "ac721b29-0a5a-44b4-a205-b23288164f40",
  "proposalNumber": "99999",
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "payment": {
    "paymentType": "Boleto",
    "installments": "1",
    "firstInstallmentDueDateDelay": "7"
  },
  "createdAt": "2021-08-03",
  "status": "Ready"
}
```

Resposta de proposta com fatura Resposta de proposta com pagamento parcelado em 12 vezes através de Fatura.

```json
{
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "ac721b29-0a5a-44b4-a205-b23288164f40",
  "proposalNumber": "99999",
  "policyPeriodStart": "2021-01-01",
  "policyPeriodEnd": "2021-12-31",
  "payment": {
    "paymentType": "Boleto",
    "installments": "12"
  },
  "createdAt": "2021-08-03",
  "status": "Ready"
}
```

### [Consultar documento da proposta](#consultar-documento-da-proposta)

Essa operação permite consultar o documento de uma proposta a partir do **ID**.

**Requisição (Request)**

| Tipo    | Campo                                                                            | Descrição                          |
| :------ | :------------------------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/proposals/{proposal_id}/document |                                    |
| Method  | GET                                                                              |                                    |
| Headers | client_id                                                                        | Client ID da App.                  |
|         | access_token                                                                     | Token de acesso gerado para a App. |

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Documento da proposta localizado com sucesso.                                    |             |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Registro não encontrado.                                                         | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

## [Apólices](#apólices)

### [Emitir apólice](#emitir-apólice)

Permite emitir uma apólice para uma proposta existente. A proposta deve ter sido aceita previamente.

**Requisição (Request)**

| Tipo    | Campo                                                               | Descrição                          |
| :------ | :------------------------------------------------------------------ | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/policies |                                    |
| Method  | POST                                                                |                                    |
| Headers | client_id                                                           | Client ID da App.                  |
|         | access_token                                                        | Token de acesso gerado para a App. |
| Body    | Policy                                                              |                                    |

Exemplos Emissão de apólice Emissão da apólice para uma proposta existente.

```json
{
  "proposalId": "bddbcd59-213f-4523-a055-7e28f23a4978"
}
```

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Apólice emitida com sucesso.                                                     | Policy      |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Recurso não encontrado.                                                          | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

Exemplos Resposta de emissão de apólice aguardando processamento Resposta de emissão de apólice aguardando processamento.

```json
{
  "policyId": "62354f29-4029-4ba1-82f8-309e458a0170",
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "81e56840-adb3-404f-8ce1-0a75d5dc029c",
  "policyPeriodStart": "2021-08-03T00:00:00Z",
  "policyPeriodEnd": "2022-08-03T00:00:00Z",
  "status": "Creating",
  "createdAt": "2021-08-03T18:11:02.573Z"
}
```

Resposta de emissão de apólice processada com sucesso Resposta de emissão de apólice processada com sucesso.

```json
{
  "policyId": "62354f29-4029-4ba1-82f8-309e458a0170",
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "81e56840-adb3-404f-8ce1-0a75d5dc029c",
  "policyNumber": "0306920219901140003053000",
  "policyPeriodStart": "2021-08-03T00:00:00Z",
  "policyPeriodEnd": "2022-08-03T00:00:00Z",
  "status": "Active",
  "createdAt": "2021-08-03T18:11:02.573Z"
}
```

### [Consultar apólice](#consultar-apólice)

Essa operação permite consultar os dados de uma apólice a partir do **ID**. Algumas informações sensíveis de participantes e agentes comissionados são ofuscadas para atender a LGPD.

**Requisição (Request)**

| Tipo    | Campo                                                                           | Descrição                          |
| :------ | :------------------------------------------------------------------------------ | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/policies/{policy_id} |                                    |
| Method  | GET                                                                             |                                    |
| Headers | client_id                                                                       | Client ID da App.                  |
|         | access_token                                                                    | Token de acesso gerado para a App. |

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Apólice localizada com sucesso.                                                  | Policy      |
| 400    | Os dados da requisição estão inválidos.                                          | ErrorResult |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Registro não encontrado.                                                         | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

Exemplos Resposta de emissão de apólice aguardando processamento Resposta de emissão de apólice aguardando processamento.

```json
{
  "policyId": "62354f29-4029-4ba1-82f8-309e458a0170",
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "81e56840-adb3-404f-8ce1-0a75d5dc029c",
  "policyPeriodStart": "2021-08-03T00:00:00Z",
  "policyPeriodEnd": "2022-08-03T00:00:00Z",
  "status": "Creating",
  "createdAt": "2021-08-03T18:11:02.573Z"
}
```

Resposta de emissão de apólice processada com sucesso Resposta de emissão de apólice processada com sucesso.

```json
{
  "policyId": "62354f29-4029-4ba1-82f8-309e458a0170",
  "proposalId": "a6ab0082-64ec-4350-81d0-281888fa638e",
  "quoteId": "81e56840-adb3-404f-8ce1-0a75d5dc029c",
  "policyNumber": "0306920219901140003053000",
  "policyPeriodStart": "2021-08-03T00:00:00Z",
  "policyPeriodEnd": "2022-08-03T00:00:00Z",
  "status": "Active",
  "createdAt": "2021-08-03T18:11:02.573Z"
}
```

### [Consultar documento da apólice](#consultar-documento-da-apólice)

Essa operação permite consultar o documento de uma apólice a partir do **ID**.

**Requisição (Request)**

| Tipo    | Campo                                                                            | Descrição                          |
| :------ | :------------------------------------------------------------------------------- | :--------------------------------- |
| Url     | https://api-hml.pottencial.com.br/insurance/v1/residencial/policies/{policy_id}/document |                                    |
| Method  | GET                                                                              |                                    |
| Headers | client_id                                                                        | Client ID da App.                  |
|         | access_token                                                                     | Token de acesso gerado para a App. |

**Respostas (Response)**

| Status | Descrição                                                                        | Tipo        |
| :----- | :------------------------------------------------------------------------------- | :---------- |
| 200    | Documento da apólice localizado com sucesso.                                     |             |
| 401    | Parceiro não autorizado a realizar a operação.                                   | ErrorResult |
| 404    | Registro não encontrado.                                                         | ErrorResult |
| 500    | Erro interno da API. Por favor tente novamente mais tarde ou contate o suporte técnico da Pottencial. | ErrorResult |

## [Dicionario](#dicionario)

### [Quote](#quote)

Representa uma cotação de um produto.

| Campo                | Tipo                       | Regras          | Descrição                                                                        |
| :------------------- | :------------------------- | :-------------- | :------------------------------------------------------------------------------- |
| quoteId              | string (uuid)              | Somente leitura | ID único da cotação que a identifica dentro da Pottencial. Esse campo é preenchido automaticamente e retornado quando uma cotação é criada com sucesso. Ele deve ser armazenado pois é utilizado nas demais operações de consulta e proposta. |
| productKey           | string (uuid)              | Somente leitura | chave do produto. A API de produtos deve ser utilizada para recuperar a lista de produtos que estão disponíveis para cotação. |
| policyType           | string (PolicyType)        | Somente Leitura | Tipo de contratação da apólice, que no caso do produto Residencial é Unique      |
| quoteNumber          | number                     | Opcional        | Numero da Cotação                                                                |
| discountPercentage   | number                     | Opcional        | Percentual de desconto a ser aplicado no valor do prêmio. O percentual de desconto e a taxa de agravo são mutuamente exclusivos, então somente um deles pode ser informado para uma cotação. |
| commercialLoadingFee | number                     | Opcional        | Taxa de agravo comercial a ser aplicada no valor do prêmio. O percentual de desconto e a taxa de agravo são mutuamente exclusivos, então somente um deles pode ser informado para uma cotação. |
| createdAt            | string (date-time)         | Somente leitura | Data e hora de criação da cotação. Esse campo é preenchido automaticamente e retornado quando uma cotação é criada com sucesso. |
| status               | string (QuoteStatus)       | Somente leitura | Situação atual da cotação. Esse campo é preenchido automaticamente e retornado quando uma cotação é criada com sucesso. |
| commercialPremium    | number                     | Somente leitura | Valor de prêmio considerando-se subscrição e comercial, incluindo valores de comissão e prolabore. Esse campo é preenchido automaticamente e retornado quando uma cotação é criada com sucesso. |
| grossPremium         | number                     | Somente leitura | Valor de prêmio total incluindo, além do prêmio comercial, o IOF e encargos financeiros. Esse campo é preenchido automaticamente e retornado quando uma cotação é criada com sucesso. |
| iof                  | number                     | Somente leitura | Valor do IOF da cotação. Esse campo é preenchido automaticamente e retornado quando uma cotação é criada com sucesso. |
| policyPeriodStart    | string (date)              | Opcional        | Data de início de vigência da apólice.                                           |
| policyPeriodEnd      | string (date)              | Opcional        | Data do fim de vigência da apólice.                                              |
| commissionedAgents   | array de CommissionedAgent | Obrigatório     | Lista de agentes comissionados da apólice, que inclui corretoras e estipulantes. |
| participants         | array de Participant       | Obrigatório     | Lista de participantes da apólice, que inclui segurado, tomador e beneficiário   |
| partners             | array de Partners          | Opcional        | Lista de Parceiros comerciais em uma apólice, não é uma entidade oficial ou regulamentada, é utilizada somente para representar vínculos comerciais. riskObjects array de - Property Obrigatório Lista de objetos segurados. Os objetos segurados variam conforme o produto informado. Residencial Enviar um objeto Property. |

### [PolicyType](#policytype)

Tipo de contratação da apólice.

-   **Unique**: Contratação única.

### [QuoteStatus](#quotestatus)

Situação da cotação define o passo do fluxo que a cotação se encontra.

-   **Creating**: A cotação foi criada, porém ainda não foi encaminhada para a subscrição.
-   **UnderAnalysis**: A cotação está sob análise da subscrição.
-   **Denied**: A cotação não foi aprovada.
-   **Approved**: A cotação foi aprovada.

### [Participant](#participant)

Representa um participante dentro da apólice.

| Campo                   | Tipo                     | Regras      | Descrição                                                                        |
| :---------------------- | :----------------------- | :---------- | :------------------------------------------------------------------------------- |
| documentNumber          | string                   | Obrigatório | CPF/CNPJ do participante. Devem ser informados somente os dígitos, incluindo zeros à esquerda. Essa informação é ofuscada no retorno da API para atender a LGPD, trazendo somente alguns dígitos visíveis. Os demais são substituídos por asterisco (*). Exemplos de retorno: 11699534000174 -> 11699***0001** 12ABC34501DE35 -> 12ABC***01DE** (CNPJ alfanumérico) 12345678909 -> 123***789** |
| participationPercentage | number                   | Opcional    | Percentual do prêmio a que o beneficiário tem direito. O valor deve ter no máximo duas casas decimais e estar entre 0.01 e 1.00. Obrigatório para os beneficiários e a soma da participação de todos os beneficiários deve ser igual a 1. |
| role                    | string (ParticipantRole) | Obrigatório | Papel do participante na cotação. Os participantes que são obrigatórios varia por produto. |
| address                 | Address                  | Opcional    | Endereço do participante.                                                        |
| contact                 | Contact                  | Opcional    | Contato do participante.                                                         |
| isPayer                 | boolean                  | Opcional    | informa quem é o pagador da apolice.                                             |
| main                    | boolean                  | Opcional    | Indicação que o participante é o beneficiário principal. Só deve ser usada para participantes com o papel Beneficiary. Não pode ter mais de um beneficiário principal. |
| type                    | string                   | Opcional    | tipo do participante. - **Individual **: pessoa física (CPF) Opcional. - **Corporate **: pessoa jurídica (CNPJ) Obrigatório informar. |

### [Address](#address)

Representa uma localidade com endereço.

| Campo      | Tipo   | Regras      | Descrição                                                                        |
| :--------- | :----- | :---------- | :------------------------------------------------------------------------------- |
| street     | string | Obrigatório | Nome da avenida, rua ou praça da localidade.                                     |
| number     | string | Obrigatório | Número da localidade.                                                            |
| district   | string | Obrigatório | Bairro do localidade.                                                            |
| city       | string | Obrigatório | Nome da cidade da localidade, conforme cadastrado nos Correios.                  |
| state      | string | Obrigatório | Sigla do estado da localidade, com duas letras.                                  |
| zipCode    | string | Obrigatório | CEP da localidade. Devem ser informados somente os dígitos.                      |
| complement | string | Opcional    | Complemento da localidade. Apartamento, andar.                                   |
| coutry     | string | Opcional    | Pais.                                                                            |
| type       | string | Opcional    | Descrição da localidade - Residential: Endereço Residencial. - Business: Endereço Empresal. - Billing: Endereço de Cobrança. |

### [ParticipantRole](#participantrole)

Lista de possíveis valores para os participantes da apólice.

-   **Beneficiary**: O beneficiário é a pessoa da apólice que tem direito a indenização do seguro em caso de problema (sinistro). O beneficiário pode ser uma pessoa diferente do segurado (que é quem está com o risco protegido) e do estipulante (que é quem está contratando o seguro), dependendo da situação.
-   **Insured**: O segurado é a pessoa, física ou jurídica, cujos interesses estão garantidos pela apólice de seguro. Geralmente é quem contrata o seguro.
-   **PolicyHolder**: Devedor das obrigações por ele assumidas no contrato principal. (Circular SUSEP 232/03).

### [CommissionedAgent](#commissionedagent)

Representa um agente comissionado dentro da apólice.

| Campo                   | Tipo                           | Regras          | Descrição                                                                        |
| :---------------------- | :----------------------------- | :-------------- | :------------------------------------------------------------------------------- |
| documentNumber          | string                         | Obrigatório     | CPF/CNPJ do agente comissionado. Devem ser informados somente os dígitos, incluindo zeros à esquerda. Essa informação é ofuscada no retorno da API para atender a LGPD, trazendo somente alguns dígitos vizíveis. Os demais são substituídos por asterísco (*). Exemplos de retorno: 11699534000174 -> 11699***0001** 12ABC34501DE35 -> 12ABC***01DE** (CNPJ alfanumérico) 12345678909 -> 123***789** |
| role                    | string (CommissionedAgentRole) | Obrigatório     | Papel desempenhado pelo agente comissionado. Os papéis obrigatórios variam por produto. |
| lead                    | boolean                        | Opcional        | Identifica a corretora principal na cotação. Esse campo pode ser omitido quando somente houver uma única corretora na cotação. |
| commissionPercentage    | number                         | Opcional        | Percentual de comissão, no caso de corretora, e percentual de pró-labore, no caso de estipulante. O limite desse valor varia conforme o produto e contrato com a seguradora. O valor deve ter no máximo 3 casas decimais. |
| participationPercentage | number                         | Opcional        | Percentual de participação na comissão, no caso de co-corretagem. Quando informado, a soma desse valor para todas as corretoras deve ser igual a 1. O valor deve ter no máximo 3 casas decimais. |
| commissionAmount        | number                         | Somente leitura | Valor de comissão, no caso de corretora, e valor de pró-labore, no caso de estipulante. Esse valor é preenchido no retorno da cotação. |
| address                 | Address                        | Opcional        | Endereço do pagador.                                                             |
| isPayer                 | boolean                        | Opcional        | informa quem é o pagador da apolice.                                             |

### [CommissionedAgentRole](#commissionedagentrole)

Lista de possíveis valores para os agentes comissionados da apólice.

-   **Broker**: É o profissional especializado e habilitado a intermediar contratos de seguros entre as seguradoras, empresas e os consumidores, sejam eles pessoas físicas ou jurídicas.
-   **PolicyOwner**: O estipulante é a pessoa que contrata o seguro, independente se é ele que se beneficia do seguro ou se é ele quem vai receber a indenização. É o estipulante, ou dono da apólice, que preenche e assina a proposta de seguro, paga o prêmio, solicita eventuais modificações durante a vigência da apólice, autoriza a sua renovação, ou solicita o seu cancelamento quando for o caso.
-   **InsuranceAgent**: .

### [Contact](#contact)

Representa os dados de contato.

| Campo           | Tipo   | Regras      | Descrição                           |
| :-------------- | :----- | :---------- | :---------------------------------- |
| name            | string | Obrigatório | Nome para identificação do contato. |
| email           | string | Obrigatório | E-mail de contato.                  |
| phoneNumber     | string | Obrigatório | Número do telefone, com DDD.        |
| cellPhoneNumber | string | Opcional    | Número do Celular, com DDD.         |

### [Coverage](#coverage)

Contém informações das coberturas para cotação.

| Campo           | Tipo   | Regras      | Descrição                                                                        |
| :-------------- | :----- | :---------- | :------------------------------------------------------------------------------- |
| key             | string | Obrigatório | ID da cobertura, conforme cada produto. Residencial - Incêndio, Queda de Raio, Queda de Aeronave, Fumaça, Explosão de Qualquer Natureza e Implosãos: basica - Danos elétricos: danos-eletricos - Impacto de veículos terrestres: impacto-veiculos - Perda ou pagamento de aluguel: perda-aluguel - Quebra de vidros e espelhos, mármore e granito: quebra-vidros - Ruptura de tubulações: ruptura - Roubo e Furto qualificado de Bens: roubo-furto - Responsabilidade Civil Familiar: rc-familiar - Vendaval, furacão, ciclone, tornado, granizo e neve: vendaval |
| lmi             | number | Opcional    | Limite máximo de indenização. No caso de contratação única, este valor pode ser menor ou igual à soma da importância segurada dos objetos segurados. Na contratação item a item, este valor é exatamente a soma dos itens desta cobertura. Caso não seja informado, assume-se o valor da importância segurada. |
| insuredAmount   | number | Opcional    | Importância segurada, ou valor segurado, deste objeto de risco.                  |
| feeMultiple     | number | Opcional    | Identifica a taxa múltipla.                                                      |
| indemnityPeriod | number | Opcional    | Identifica o período de indenização. (aplicável apenas para a cobertura Perda ou pagamento de alguel a terceiros) |
| price           | number | Opcional    | prêmio por cobertura.                                                            |
| pos             | number | Opcional    | percentual de Participação Obrigatória do Segurado.                              |
| description     | string | Opcional    | texto descritivo da franquia.                                                    |

### [Property](#property)

Representa uma propriedade que está sendo segurada nos seguros do grupo Patrimonial.

| Campo                 | Tipo                      | Regras      | Descrição                                                                        |
| :-------------------- | :------------------------ | :---------- | :------------------------------------------------------------------------------- |
| type                  | string                    | Obrigatório | Nome do tipo do objeto de risco. Valor fixo "Property"                           |
| coverages             | array de Coverage         | Obrigatório | Lista de coberturas para o objeto segurado.                                      |
| address               | Address                   | Obrigatório | Endereço da propriedade que está sendo segurada.                                 |
| insuredOwner          | boolean                   | Obrigatório | Identifica se o segurado é dono da propriedade.                                  |
| constructionType      | string (ConstructionType) | Obrigatório | Define o tipo de construção da propriedade.                                      |
| propertyType          | string (PropertyType)     | Obrigatório | Tipo da propriedade segurada.                                                    |
| propertyUseType       | string (PropertyUseType)  | Obrigatório | Tipo de uso da propriedade segurada.                                             |
| documentNumber        | string                    | Opcional    | CPF do Segurado na cobertura rc-familiar.                                        |
| paymentConditions     | PaymentConditions         | Opcional    | Representa um objeto informando forma de pagamento na Pottencial.                |
| companyDocumentNumber | string                    | Opcional    | CNPJ da Empresa segurada. -->                                                    |
| activityId            | string                    | Opcional    | Identificador da atividade Exercida. Obter atividade na Api Risk Object em: Listar as atividades --> |
| professionalType      | string (ProfessionalType) | Opcional    | tipo de profissional. MEI ou LIBERAL Objeto preenchido apenas, mediante a acordo comercial --> ConstructionType Lista os possíveis tipos de construção de propriedade. - Brick: Alvenaria. - Wood: Madeira. PropertyUseType Lista os possíveis tipos de uso de propriedade. - Usual: Habitual. - Summer: Veraneiro. - Vacate: Desocupada. ProfessionalType Lista os possíveis tipo de profissional. MEI ou LIBERAL Objeto preenchido apenas, mediante a acordo comercial - IndividualMicroentrepreneur: Micro empreendedor individual MEI - **LiberalProfessional **: Profissional Liberal. PropertyType Lista os possíveis tipos de propriedade. - Apartment: Apartamentos. - House: Casas. - CondominiumHouse: Casas em condomínios. - CountrySideHouse: Chácaras , Sítios ou Fazenda. |

### [PaymentConditions](#paymentconditions)

Representa um objeto informando forma de pagamento na Pottencial.

| Campo        | Tipo                 | Regras      | Descrição                                                    |
| :----------- | :------------------- | :---------- | :----------------------------------------------------------- |
| paymentType  | string (PaymentType) | Obrigatório | Forma de pagamento da apólice.                               |
| installments | integer              | Obrigatório | Número de parcelas que será dividido o pagamento da apólice. |

### [Partners](#partners)

| Representa um Parceiros comerciais em uma apólice, não é uma entidade oficial ou regulamentada, é utilizada somente | para representar vínculos comerciais. | | |

| Campo          | Tipo                 | Regras      | Descrição                                                                        |
| :------------- | :------------------- | :---------- | :------------------------------------------------------------------------------- |
| documentNumber | string               | Obrigatório | CPF/CNPJ do participante. Devem ser informados somente os dígitos, incluindo zeros à esquerda. Essa informação é ofuscada no retorno da API para atender a LGPD, trazendo somente alguns dígitos visíveis. Os demais são substituídos por asterisco (*). Exemplos de retorno: 11699534000174 -> 11699***0001** 12ABC34501DE35 -> 12ABC***01DE** (CNPJ alfanumérico) 12345678909 -> 123***789** |
| role           | string (PartnerRole) | Obrigatório | Papel do parceiro na cotação. Os participantes que são obrigatórios varia por produto. |
| name           | string               | Opcional    | nome do parceiro PartnerRole Parceiros comerciais em uma apólice, não é uma entidade oficial ou regulamentada, é utilizada somente para representar vínculos comerciais. - Advisor. - CommercialAgent: Representante Comercial . |

### [QuotePayment](#quotepayment)

Representa o objeto com as informações de pagamento.

| Campo        | Tipo                 | Regras   | Descrição                                                                        |
| :----------- | :------------------- | :------- | :------------------------------------------------------------------------------- |
| paymentType  | string (PaymentType) | Opcional | Forma de pagamento da apólice.                                                   |
| installments | integer              | Opcional | Número de parcelas que será dividido o pagamento da apólice. Os produtos possuem regras específicas para a quantidade de parcelas que pode ser divido o pagamento. |

### [Proposal](#proposal)

Representa uma proposta de um produto.

| Campo             | Tipo                    | Regras          | Descrição                                                                        |
| :---------------- | :---------------------- | :-------------- | :------------------------------------------------------------------------------- |
| proposalId        | string (uuid)           | Somente leitura | ID único da proposta. O ID será gerado automaticamente no retorno da API.        |
| quoteId           | string (uuid)           | Obrigatório     | ID único da cotação que vai dar origem à proposta.                               |
| proposalNumber    | number                  | Opcional        | Numero da Proposta                                                               |
| policyPeriodStart | string (date)           | Opcional        | Data de início de vigência da apólice. Esse valor pode ser omitido caso essa informação tenha sido informada no momento de cotação. |
| policyPeriodEnd   | string (date)           | Opcional        | Data do fim de vigência da apólice. Esse valor pode ser omitido caso essa informação tenha sido informada no momento de cotação. |
| payment           | Payment                 | Obrigatório     | Detalhes do pagamento da apólice.                                                |
| createdAt         | string (date-time)      | Somente leitura | Data e hora de criação da proposta. Esse campo é preenchido automaticamente e retornado quando uma proposta é criada com sucesso. |
| status            | string (ProposalStatus) | Somente leitura | Situação atual da proposta. Esse campo é preenchido automaticamente e retornado quando uma proposta é criada com sucesso. |

### [ProposalStatus](#proposalstatus)

Situação da proposta define o passo do fluxo que a proposta se encontra.

-   **Pending**: A proposta foi criada, porém ainda não foi definido meio de pagamento.
-   **Ready**: O meio de pagamento da proposta foi definido.
-   **Issued**: O documento da proposta foi emitido.
-   **Accepted**: A proposta foi aceita.

### [Payment](#payment)

Representa os dados referentes ao pagamento da apólice.

| Campo                        | Tipo                 | Regras      | Descrição                                                                        |
| :--------------------------- | :------------------- | :---------- | :------------------------------------------------------------------------------- |
| paymentType                  | string (PaymentType) | Obrigatório | Forma de pagamento da apólice.                                                   |
| installments                 | integer              | Obrigatório | Número de parcelas que será dividido o pagamento da apólice. Os produtos possuem regras específicas para a quantidade de parcelas que pode ser divido o pagamento. |
| firstInstallmentDueDateDelay | integer              | Opcional    | Carência para a data de vencimento da primeira parcela, em dias, para pagamento em boleto. |
| firstDueDate                 | string               | Opcional    | Data de vencimento da primeira parcela, para pagamento em boleto.                |
| automaticDebitAccount        | BankAccount          | Opcional    | Dados bancários para pagamento através de débito em conta.                       |

### [BankAccount](#bankaccount)

Representa os dados de uma conta bancária.

| Campo            | Tipo   | Regras      | Descrição                                                                        |
| :--------------- | :----- | :---------- | :------------------------------------------------------------------------------- |
| bankCode         | string | Obrigatório | Código de compensação do banco, conforme cadastrado no Banco Central.            |
| bankNumber       | string | Obrigatório | Número a agência da conta bancária, sem dígito.                                  |
| accountNumber    | string | Obrigatório | Número da conta bancária, sem dígito.                                            |
| accountDigit     | string | Obrigatório | Dígito verificador da conta bancária.                                            |
| accountExtension | string | Opcional    | Letra verificador da conta bancária.                                             |
| documentNumber   | string | Obrigatório | CPF/CNPJ do titular da conta bancária.                                           |
| accountType      | string | Opcional    | tipo da conta . - Current: Conta Corrente. - Savings: Conta Poupança. - Payments: Conta Pagamento. |

### [PaymentType](#paymenttype)

Lista as possíveis formas de pagamento da apólice.

-   **Boleto**: Pagamento através de boleto
-   **AutomaticDebit**: Pagamento através de débito em conta. Descrição auxiliar | **Débito em Conta (AutomaticDebit):** | Máximo de parcelas: 11 | | | Valor mínimo por parcela: R$ 25,00 Data mínima para pagamento da primeira parcela: Data Atual + 6 dias Data máxima para pagamento da primeira parcela: (Data Atual + 6) + 14 dias (20 dias a partir da data atual) Bancos conveniados: Sicoob, Itaú, Santander, Cresol, Ailos e Banco do Nordeste | **Boleto:** | Máximo de parcelas: 11 | | | Valor mínimo por parcela: R$ 25,00 Data máxima para pagamento da primeira parcela: (Data Atual + 6) + 14 dias (20 dias a partir da data atual)

### [Policy](#policy)

Representa uma apólice de um produto.

| Campo             | Tipo                  | Regras          | Descrição                                                                        |
| :---------------- | :-------------------- | :-------------- | :------------------------------------------------------------------------------- |
| policyId          | string (uuid)         | Somente leitura | ID único da apólice. O ID será gerado automaticamente no retorno da API.         |
| proposalId        | string (uuid)         | Obrigatório     | ID único da proposta aceita anteriormente que vai dar origem à apólice.          |
| quoteId           | string (uuid)         | Somente leitura | ID único da cotação que deu origem à proposta.                                   |
| policyNumber      | string                | Somente leitura | Número da apólice.                                                               |
| policyPeriodStart | string (date)         | Somente leitura | Data de início de vigência da apólice.                                           |
| policyPeriodEnd   | string (date)         | Somente leitura | Data do fim de vigência da apólice.                                              |
| status            | string (PolicyStatus) | Somente leitura | Situação atual da apólice. Esse campo é preenchido automaticamente e retornado quando uma apólice é criada com sucesso. |
| createdAt         | string (date-time)    | Somente leitura | Data e hora de criação da apólice. Esse campo é preenchido automaticamente e retornado quando uma apólice é criada com sucesso. |

### [PolicyStatus](#policystatus)

Situação da apólice define o passo do fluxo que a apólice se encontra.

-   **Creating**
-   **Modifying**
-   **Canceling**
-   **Active**
-   **Cancelled**
-   **Expired**

## [Resolução de erros](#resolução-de-erros)

Código Erro Descrição Solução

400 Bad Request Invalid secret in Authorization header O **client\_secret** informado no header é inválido. Verifique se o **client\_secret** informado é o mesmo da **APP** e se o header Authorization está sendo passado conforme especificado.

401 Unauthorized Could not find a required APP in the request, identified by HEADER client\_id. O **client\_id** informado no header é inválido. Verifique se o **client\_id** informado é o mesmo da **APP** e se o header Authorization está sendo passado conforme especificado.

404 Not Found Not Found Provavelmente a URL sendo chamado não está correta. Verifique se está conforme especificado.

415 Unsupported Media Type Content type not supported O header Content-Type não está sendo informado conforme esperado. O correto é **application/json**.

429 Too Many Requests Too Many Requests O consumidor estourou o limite de requisições por tempo. Aguarde um pouco para tentar novas requisições.

500 Internal Server Error Internal Server Error Erro não esperado, algo está quebrado na API. Por favor aguarde ou entre em contato com o time de suporte.

### [Estrutura da mensagem de erro](#estrutura-da-mensagem-de-erro)

### [ErrorResult](#errorresult)

Representa um resultado de erro na chamada da plataforma.

| Campo  | Tipo                  | Regras   | Descrição                      |
| :----- | :-------------------- | :------- | :----------------------------- |
| errors | array de ErrorMessage | Opcional | Lista de erros da solicitação. |

### [ErrorMessage](#errormessage)

Contém informações de um erro.

| Campo   | Tipo   | Regras   | Descrição                        |
| :------ | :----- | :------- | :------------------------------- |
| code    | string | Opcional | Código de identificação do erro. |
| message | string | Opcional | Descrição do erro.               |