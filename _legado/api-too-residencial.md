

<!-- ======== too-visao-geral-do-produto.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Visão Geral do Produto 

#### O que é? Proteção para o seu lar, tranquilidade para a sua família. 
 Garante indenização ao segurado, caso situações como incêndio, explosão, fumaça, queda de raio ou queda de avião aconteçam e danifiquem o seu imóvel.  

#### Vantagens e benefícios 
 Ao contratar, você terá direito à serviços emergenciais como: reparos elétricos, reparos hidráulicos, chaveiro e vidraceiro. Se desejar, pode solicitar ainda coberturas adicionais contra alagamento, vendaval, desmoronamento, roubo de bens, danos elétricos, eventos de responsabilidade civil familiar, entre outras.  

#### Feito para quem? 
 Quem quer proteger sua residência e proporcionar bem-estar à sua família.  
 Casas e apartamentos de pessoa física, no Brasil  

#### Coberturas Básicas do Seguro Residencial 
 A Too oferece cobertura contra eventuais danos estruturais ao seu imóvel, causados pelos fatores a seguir: Explosões, Incêndio/ Fumaça e Queda de Raio ou Aeronave.  

#### Coberturas Adicionais 
 Consulte as opções de coberturas adicionais através do seu corretor de seguros.  

#### Serviços Emergenciais 
 Os segurados contam com diversos serviços emergenciais para reparos no imóvel: mão de obra elétrica, hidráulica, vidraceiro e chaveiro.


<!-- ======== too-recursos-disponiveis.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Recursos Disponíveis 

#### Com a API do Residencial você poderá utilizar os recursos/serviços:  
-  Registro de Cotação: destinada ao registro de cotação nos formatos IS, Aluguel ou Imóvel.  
-  Consulta de Cotação: disponibiliza uma consulta para identificar a situação da cotação registrada no processo anterior.  
-  Envio de Bilhetes: destinado ao registro de um bilhete residencial onde os dados do imóvel e segurado são informados.  
-  Consulta de Status de Bilhete: disponibiliza uma consulta para obter o status do bilhete residencial.  
-  Envio de Proposta: destinado ao registro de uma proposta onde os dados dados do imóvel e segurado são informados.  
-  Consulta de Status da Proposta: disponibiliza uma consulta para obter o status da proposta.  
-  Consultar PDF da Proposta/Bilhete: retorna a proposta/bilhete em formato PDF.


<!-- ======== too-regras-e-payloads.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Regras, requisições e respostas 
Veja abaixo possíveis valores ao criar uma Cotação, gerar um Bilhete ou uma Proposta. 
Para iniciar o ciclo da emissão de um bilhete ou proposta, é necessário primeiro levantar o tipo de contrato firmado a fim de identificar qual o tipo de cotação que deverá ser realizada, IS, Aluguel ou Imóvel, e logo em seguida realizar uma cotação, conforme demostrado nos exemplos abaixo. 
Atenção: As requisições devem acompanhar no header o Authentication Bearer <<token>>  

#### Estrutura da requisição para criar uma nova Cotação para IS  
- Valor de Comissão deve estar entre 0 e 70 (%), porém seguir o descrito em contrato com o parceiro. 
- Cobertura de Incêndio é a principal e única obrigatória para o cálculo da cotação. 
- Cobertura de RC, se mencionada, deve estar entre 0 e 300000.00. 
- Cobertura de PerdaDeAluguel, se mencionada, deve estar entre 0 e 50000.00. 
- Cobertura de Vendaval, se mencionada, deve estar entre 0 e 300000.00. 
- Cobertura de DanosEletricos, se mencionada, deve estar entre 0 e 100000.00. 
- Cobertura de RouboOuFurto, se mencionada, deve ser maior 0. 
- Cobertura de VidrosEspelhosEMarmores, se mencionada, deve ser maior 0. 
- Cobertura de VazamentoDeTubulacoes, se mencionada, deve ser maior 0.  Corpo da Requisição 
POST https://openapi-uat.tooseguros.com.br/seguros/residenciais/cotacao/is 

#### Estrutura da requisição para buscar a proposta criada 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/proposta/{{numeroProposta}} Corpo da Requisição 
GET 'https://app-hom-gateway.tooseguros.com.br/proposta/residencial/0114010000001' \ 
```

```
  {
        "Assistencia": "<<Obrigatório: tipo de assistência>>",
        "CodigoProduto": "<<Obrigatório: código do produto do parceiro>>",
        "ValorComissao": "<<Obrigatório: valor de comissão conforme contrato>>",
        "TipoImovel": "<<Obrigatório: tipo de imóvel cotado>>",
        "Rc": "<<Não obrigatório: cobertura com o valor de IS de responsabilidade civil entre 0 e 300000.00>>",
        "Incendio": "<<Obrigatório: cobertura principal com o valor de IS de incêndio entre 0 e 1000000.00>>",
        "PerdadeAluguel": "<<Não obrigatório: cobertura com o valor de IS de perda de aluguel entre 0 e 50000.00>>",
        "Vendaval": "<<Não obrigatório: cobertura com o valor de IS de vendaval entre 0 e 300000.00>>",        
        "DanosEletricos": "<<Não obrigatório: cobertura com o valor de IS de danos elétricos entre 0 e 100000.00>>",
        "RouboOuFurto": "<<Não obrigatório: cobertura com o valor de IS de roubo ou furto maior que 0>>",
        "VidrosEspelhosEMarmores": "<<Não obrigatório: cobertura com o valor de IS de vidros, espelhos e marmores maior que 0>>",
        "VazamentoDeTubulacoes": "<<Não obrigatório: cobertura com o valor de IS de vazamento de tubulações maior que 0>>"
        }				

```

```
 
curl --location --request POST 'https://app-hom-ateway.tooseguros.com.br/cotacao/residencial/is' \ 
```

```
--header 'clientId: 3fb845188beb4dcb8b3bb09dc954c0d0' \
--header 'clientSecret: ae68515d92bc4ea892235e86eb51a1db' \
--header 'Content-Type: application/json' \
--data-raw ' {
        "assistencia": "Basica",
        "CodigoProduto": 1,
        "valorComissao": 10.0,
        "tipoImovel": "Casa",
        "rc": 1000.0,
        "incendio": 5000.0,
        "perdadeAluguel": 1000.0,
        "vendaval": 3000.0,
        "danosEletricos": 1000.0
        }'				

```

```

#### Estrutura da requisição para buscar a cotação de IS Corpo da Requisição 
 https://openapi-uat.tooseguros.com.br/seguros/residenciais/cotacao/is/{{codigoCotacao}}  
curl --location --request GET 'https://app-hom-gateway.tooseguros.com.br/cotacao/residencial/is/' \ 
```

```
{
--header 'clientId: 3fb845188beb4dcb8b3bb09dc954c0d0' \
--header 'clientSecret: ae68515d92bc4ea892235e86eb51a1db'
}

```

```

#### Estrutura da requisição para criar uma nova Cotação para Aluguel 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/cotacao/aluguel  
-  
Remuneração deve estar entre 5 e 80 (%), porém seguir o descrito em contrato com o parceiro.  
-  
Fator de Agravo deve estar entre 0 e 200 (%), porém seguir o descrito em contrato com o parceiro.  
-  
Valor de Aluguel é a principal e única obrigatória para o cálculo da cotação.  
-  
Cobertura de incêndio.  
-  
Coberturas de DanosEletricos, ResponsabilidadeCivil, PerdaOuPagamentoDeAluguel, VendavalEImpactoVeiculoTerrestre, RouboOuFurto, VidrosEspelhosEMarmores e VazamentoDeTubulacoes podem ser omitidas, mas se enviados, devem ter apenas valores como true ou false.   Corpo da Requisição 
POST 'https://app-hom-gateway.tooseguros.com.br/cotacao/residencial/aluguel' 
```

```
  {
    "Assistencia": "<<Obrigatório: tipo de assistência>>",
    "CodigoProduto": "<<Obrigatório: código do produto do parceiro>>",
    "TipoImovel": "<<Obrigatório: tipo de imóvel cotado>>",
    "Remuneracao": "<<Obrigatório: valor de comissão entre 5 e 80>>",
    "FatorAgravo": "<<Obrigatório: valor de comissão entre 0 e 200>>",
    "ValorAluguel": "<<Obrigatório: Cobertura básica com o valor de aluguel entre 300 e 10000.00>>",
    "DanosEletricos": "<<Não obrigatório: indica se possui cobertura de danos elétricos>>",
    "ResponsabilidadeCivil": "<<Não obrigatório: indica se possui cobertura de responsabilidade civil>>",
    "PerdaOuPagamentodeAluguel": "<<Não obrigatório: indica se possui cobertura de perda ou pagamento de aluguel>>",
    "VendavalEImpactoVeiculoTerrestre": "<<Não obrigatório: indica se possui cobertura de vendaval e impacto de veículo terrestre>>",
    "RouboOuFurto": "<<Não obrigatório: indica se possui cobertura de roubo ou furto>>",
    "VidrosEspelhosEMarmores": "<<Não obrigatório: indica se possui cobertura de vidros, espelhos e mármores>>",
    "VazamentoDeTubulacoes": "<<Não obrigatório: indica se possui cobertura de vazamento de tubulações>>"
		}

--header 'clientId: 07c35987e6254a1598ab82c7450b3a90' \
--header 'clientSecret: 3fbafe8d55514ad78d2f5ff5ca4f4540' \
--header 'Content-Type: application/json' \
--data-raw '{
    "Assistencia": "Basica",
    "CodigoProduto": 1,
    "TipoImovel": "Apartamento",
    "Remuneracao": 50,
    "FatorAgravo": 100,
    "ValorAluguel": 5000,
    "DanosEletricos": true,
    "ResponsabilidadeCivil": true,
    "PerdaOuPagamentodeAluguel": true,
    "VendavalEImpactoVeiculoTerrestre": true
		}'

```

```

#### Estrutura da requisição para buscar a cotação de aluguel 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/cotacao/aluguel/{{codigoCotacao}} Corpo da Requisição 
GET 'https://app-hom-gateway.tooseguros.com.br/cotacao/residencial/aluguel/'  
```

```
{
--header 'clientId: 07c35987e6254a1598ab82c7450b3a90' \
--header 'clientSecret: 3fbafe8d55514ad78d2f5ff5ca4f4540'
}

```

```

#### Estrutura da requisição para criar uma nova Cotação para Imóvel 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/cotacao/imovel  
- Remuneração definido em contrato com o parceiro. 
- Valor de Imóvel é a única obrigatória para o cálculo da cotação e deve estar entre 50000.00 e 20000000.00. 
- Coberturas de DanosEletricos, ResponsabilidadeCivil, PerdaOuPagamentoDeAluguel, VendavalEImpactoVeiculoTerrestre, RouboOuFurto, VidrosEspelhosEMarmores e VazamentoDeTubulacoes podem ser omitidas, mas se enviados, devem ter apenas valores como true ou false.  Corpo da Requisição 
POST 'https://app-hom-gateway.tooseguros.com.br/cotacao/residencial/imovel' 
```

```
	{
    "Assistencia": "<<Obrigatório: tipo de assistência>>",
		"CodigoProduto": <<Obrigatório: código do produto do parceiro>>,
		"TipoImovel": "<<Obrigatório: tipo de imóvel cotado>>”,
		"Remuneracao": <<Obrigatório: valor de remuneração acordado em contrato>>,   
		"ValorImovel": <<Obrigatório: Cobertura básica com o valor de imóvel entre 50000.00 e 20000000.00>>,
		"DanosEletricos": <<Não obrigatório: indica se possui cobertura de danos elétricos>>,
		"ResponsabilidadeCivil": <<Não obrigatório: indica se possui cobertura de responsabilidade civil>>,
		"PerdaOuPagamentodeAluguel": <<Não obrigatório: indica se possui cobertura de perda ou pagamento de aluguel>>,
		"VendavalEImpactoVeiculoTerrestre": <<Não obrigatório: indica se possui cobertura de vendaval e impacto de veículo terrestre>>,
		"RouboOuFurto": <<Não obrigatório: indica se possui cobertura de roubo ou furto>>,
		"VidrosEspelhosEMarmores": <<Não obrigatório: indica se possui cobertura de vidros, espelhos e mármores>>,
		"VazamentoDeTubulacoes": <<Não obrigatório: indica se possui cobertura de vazamento de tubulações>>
		}
		
--header 'clientId: 4dec8c1f8f6d4f0bb89cdc251e2c83b0' \
--header 'clientSecret: 85a196bff47f4fd8943ac311ec7c543a' \
--header 'Content-Type: application/json' \
--data-raw '{
		"Assistencia": "SemAssistencia",
		"CodigoProduto": 1,
		"TipoImovel": "Apartamento",
		"Remuneracao": 15,  
		"ValorImovel": 500000,
		"DanosEletricos": true,
		"ResponsabilidadeCivil": true,
		"PerdaOuPagamentodeAluguel": true,
		"RouboOuFurto": true,
    "Incendio": true
}'

```

```

#### Estrutura da requisição para buscar a cotação de imóvel 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/cotacao/imovel/{{codigoCotacao}}  Corpo da Requisição 
GET 'https://app-hom-gateway.tooseguros.com.br/cotacao/residencial/imovel/' \ 
```

```
{
--header 'clientId: 4dec8c1f8f6d4f0bb89cdc251e2c83b0' \
--header 'clientSecret: 85a196bff47f4fd8943ac311ec7c543a'
}

```

```

#### Estrutura da requisição para criar um novo Bilhete 
IS: https://openapi-uat.tooseguros.com.br/seguros/residenciais/bilhete/is 
Aluguel: https://openapi-uat.tooseguros.com.br/seguros/residenciais/bilhete/aluguel Corpo da Requisição 
POST 'https://app-hom-gateway.tooseguros.com.br/bilhetes/residencial/is' 
```

```
				{
				"codigoCotacao": "<<Obrigatório: código da cotação gerado em uma das estruturas de IS ou Aluguel>>",
				"codigoProduto": "<<Obrigatório: código do produto do parceiro>>",
				"codigoPlano": "001",
				"enderecoRisco": {
				"tipoResidencia": "<<Obrigatório: tipo de residência do imóvel a ser assegurado>>",
				"bairro": "<<Obrigatório: bairro do imóvel a ser assegurado>>",
				"cep": "<<Obrigatório: cep do imóvel a ser assegurado>>",
				"cidade": "<<Obrigatório: cidade do imóvel a ser assegurado>>",
				"complemento": "<<Não Obrigatório: complemento do imóvel a ser assegurado>>",
				"estado": "<<Obrigatório: estado do imóvel a ser assegurado>>",
				"logradouro": "<<Obrigatório: logradouro do imóvel a ser assegurado>>",
				"numero": "<<Obrigatório: número do imóvel a ser assegurado>>"
				},

    "segurado": {
		"autorizaComunicacao": <<Obrigatório: indica se segurado autoriza receber  comunicação>>,
		"emails": [
			"<<Obrigatório: email do segurado>>"
			],
			"telefones": [
			{
				"ddd": "<<Não Obrigatório: ddd do telefone do segurado>>",
				"numero": "<<Não Obrigatório: número do telefone do segurado>>",
				"tipoTelefone": "<<Obrigatório: tipo do telefone do segurado>>",
				"tiposNotificacao": [
						"<<Não Obrigatório: tipo de notificação do telefone do segurado>>"
						]
			}
      ],
			"nome": "<<Obrigatório: nome do segurado>>",
			"sexo": "<<Obrigatório: sexo do segurado>>",
			"dataNascimento": "<<Obrigatório: data de nascimento do segurado>>",
			"cpf": "<<Obrigatório: cpf do segurado>>",
			"pessoaPoliticamenteExposta": "<<Obrigatório: indica se segurado é pessoa politicamente exposta>>",
			"endereco": {
			       "bairro": "<<Obrigatório: bairro do segurado>>",
						 "cep": "<<Obrigatório: cep do segurado >>",
						 "cidade": "<<Obrigatório: cidade do segurado >>",
						 "complemento": "<<Não Obrigatório: complemento do segurado >>",
						 "estado": "<<Obrigatório: estado do segurado >>",
						 "logradouro": "<<Obrigatório: logradouro do segurado>>",
						 "numero": "<<Obrigatório: número do segurado>>"
						 }
		},
    "pagamento": {
		     "formaPagamento": "<<Obrigatório: tipo de forma de pagamento>>",
				 "numeroParcelas": "<<Obrigatório: número de parcelas de pagamento>>"
				 },
		"AgenteArrecadador": "<<Obrigatório: indica se agente arrecadador>>",
		"CNPJAgenteArrecadador": "<<Obrigatório: cnpj do agente arrecadador>>",
		"CNPJCorretora": "<<Obrigatório: cnpj do corretor>>",
		"CodigoSusepCorretora": "<<Obrigatório: código susep da corretora>>",
		"RazaoSocialCorretora": “<<Obrigatório: razão social da corretora>>”,
		"parceiro <<Obrigatório caso seja parceiro indireto>>": { 
						 "cnpj": "<<Obrigatório caso seja parceiro indireto: razão social do parceiro indireto>>",
						 "razaoSocial": "<<Obrigatório caso seja parceiro indireto: razão social do parceiro indireto>>",
						 "canal": "<<Obrigatório caso seja parceiro indireto: razão social do parceiro indireto>>",
						 "identificacao": "<<Obrigatório caso seja parceiro indireto: razão social do parceiro indireto>>"
						 }
		} 

--header 'clientId: 3fb845188beb4dcb8b3bb09dc954c0d0' \
--header 'clientSecret: ae68515d92bc4ea892235e86eb51a1db' \
--header 'Content-Type: application/json' \
--data-raw '{
		"codigoCotacao": "12345",
		"codigoProduto": "123",
		"codigoPlano": "001",
		"enderecoRisco": {
				"tipoResidencia": "Casa",
				"bairro": "Bairro",
				"cep": "11010-000",
				"cidade": "São Paulo",
				"complemento": "Casa",
				"estado": "SP",
				"logradouro": "Casa 01",
				"numero": "1"
			},
    "segurado": {
				"autorizaComunicacao": true,
				"emails": [
				     "email@email.com.br"
        ],
        "telefones": [
							{
							"ddd": "11",
							"numero": "982323232",
							"tipoTelefone": "celular",
							"tiposNotificacao": [
									"SMS"
									]
						}
				],
				"nome": "Nome Do Segurado",
				"sexo": "Feminino",
				"dataNascimento": "1981-01-01",
				"cpf": "12345678901",
				"pessoaPoliticamenteExposta": false,
				"endereco": {
						"bairro": "Bairro",
						"cep": "11010-000",
						"cidade": "São Paulo",
						"complemento": "casa",
						"estado": "SP",
						"logradouro": "casa 01",
						"numero": "1"
				}
		},
		"pagamento": {
				"formaPagamento": "Fatura",
				"numeroParcelas": 12
				},
		"AgenteArrecadador": false,
		"CNPJAgenteArrecadador": "12345678000100",
		"CNPJCorretora": "12345678000100",
		"CodigoSusepCorretora": 123456,
		"RazaoSocialCorretora": "Razão Social",
		"parceiro": {
        "cnpj": "29742867000102",
        "razaoSocial": "razaoSocial",
        "canal": "canal",
        "identificacao": "Identificacao"
    }
		}'

```

```

#### Estrutura da requisição para buscar o bilhete criado 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/bilhete/is/{{numeroBilhete}} 
{{urlApiGee}}/seguros/residenciais/bilhete/aluguel/{{numeroBilhete}} Corpo da Requisição 
GET 'https://app-hom-gateway.tooseguros.com.br/bilhetes/residencial/aluguel/null' \ 
```

```
--header 'clientId: 07c35987e6254a1598ab82c7450b3a90' \
--header 'clientSecret: 3fbafe8d55514ad78d2f5ff5ca4f4540' \
--header 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2OTBkMjViMTgzNzk0MDY4YTZmNmY1NTk2YzNkMmNjZCIsImp0aSI6ImE3ZDFiODJhLTcyZmMtNDY2My05ZDE5LTlmOTkzNWM3NDBkOSIsImlhdCI6MTYzNzMyNTU3NSwiSXNVc2VyIjoiZmFsc2UiLCJVc2VySWQiOiIiLCJuYmYiOjE2MzczMjUyNzUsImV4cCI6MTYzNzM2MTU3NSwiaXNzIjoiUGFuU2VndXJvc0lzc3VlciIsImF1ZCI6IlBhblNlZ3Vyb3NBdWRpZW5jZSJ9.GQ34taoWtqjkxk2NovuDT_YU9_sM41Innrp7QaJ26q0'

```

```

#### Estrutura da requisição para criar uma nova Proposta 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/proposta Corpo da Requisição 
POST 'https://app-hom-gateway.tooseguros.com.br/proposta/residencial' \ 
```

```
{

    "codigoCotacao": "<<Obrigatório: código da cotação gerado em uma das estruturas de IS ou Aluguel>>",
    "codigoProduto": "<<Obrigatório: código do produto do parceiro>>",
    "codigoPlano": "001",
    "enderecoRisco": {
        "tipoResidencia": "<<Obrigatório: tipo de residência do imóvel a ser assegurado>>",
        "bairro": "<<Obrigatório: bairro do imóvel a ser assegurado>>",
        "cep": "<<Obrigatório: cep do imóvel a ser assegurado>>",
        "cidade": "<<Obrigatório: cidade do imóvel a ser assegurado>>",
        "complemento": "<<Não Obrigatório: complemento do imóvel a ser assegurado>>",
        "estado": "<<Obrigatório: estado do imóvel a ser assegurado>>",
        "logradouro": "<<Obrigatório: logradouro do imóvel a ser assegurado>>",
        "numero": "<<Obrigatório: número do imóvel a ser assegurado>>"
    },
    "segurado": {
        "autorizaComunicacao": <<Obrigatório: indica se segurado autoriza receber comunicação>>,
        "emails": [
            "<<Obrigatório: email do segurado>>"
        ],
        "telefones": [
            {
                "ddd": "<<Não Obrigatório: ddd do telefone do segurado>>",
                "numero": "<<Não Obrigatório: número do telefone do segurado>>"
                "tipoTelefone": "<<Obrigatório: tipo do telefone do segurado>>",
                "tiposNotificacao": [
                    "<<Não Obrigatório: tipo de notificação do telefone do segurado>>"
                ]
            }
        ],
        "nome": "<<Obrigatório: nome do segurado>>"
        "sexo": "<<Obrigatório: sexo do segurado>>",
        "dataNascimento": "<<Obrigatório: data de nascimento do segurado>>",
        "cpf": "<<Obrigatório: cpf do segurado>>",
        "pessoaPoliticamenteExposta": <<Obrigatório: indica se segurado é pessoa politicamente exposta>>,
        "endereco": {
             "bairro": "<<Obrigatório: bairro do segurado>>",
             "cep": "<<Obrigatório: cep do segurado >>",
             "cidade": "<<Obrigatório: cidade do segurado >>",
             "complemento": "<<Não Obrigatório: complemento do segurado >>",
             "estado": "<<Obrigatório: estado do segurado >>",
             "logradouro": "<<Obrigatório: logradouro do segurado>>",
             "numero": "<<Obrigatório: número do segurado>>"
        }
    },
    "pagamento": {
        "formaPagamento": "<<Obrigatório: tipo de forma de pagamento>>",
        "numeroParcelas": <<Obrigatório: número de parcelas de pagamento>>
    },
    "AgenteArrecadador": <<Obrigatório: indica se agente arrecadador>>,
    "CNPJAgenteArrecadador": "<<Obrigatório: cnpj do agente arrecadador>>",
    "CNPJCorretora": "<<Obrigatório: cnpj do corretor>>",
    "CodigoSusepCorretora": <<Obrigatório: código susep da corretora>>,
    "RazaoSocialCorretora": “<<Obrigatório: razão social da corretora>>”
}

--header 'clientId: 4dec8c1f8f6d4f0bb89cdc251e2c83b0' \
--header 'clientSecret: 85a196bff47f4fd8943ac311ec7c543a' \
--header 'Content-Type: application/json' \
--data-raw '{
    "codigoCotacao": "12345",
    "codigoProduto": "123",
    "codigoPlano": "001",
    "enderecoRisco": {
        "tipoResidencia": "Casa",
        "bairro": "Bairro",
        "cep": "11010-000",
        "cidade": "São Paulo",
        "complemento": "Casa",
        "estado": "SP",
        "logradouro": "Casa 01",
        "numero": "1"
    },
    "segurado": {
        "autorizaComunicacao": true,
        "emails": [
            "email@email.com.br"
        ],
        "telefones": [
            {
                "ddd": "11",
                "numero": "982323232",
                "tipoTelefone": "celular",
                "tiposNotificacao": [
                    "SMS"
                ]
            }
        ],
        "nome": "Nome Do Segurado",
        "sexo": "Feminino",
        "dataNascimento": "1981-01-01",
        "cpf": "12345678901",
        "pessoaPoliticamenteExposta": false,
        "endereco": {
            "bairro": "Bairro",
            "cep": "11010-000",
            "cidade": "São Paulo",
            "complemento": "casa",
            "estado": "SP",
            "logradouro": "casa 01",
            "numero": "1"
        }
    },
    "pagamento": {
        "formaPagamento": "Fatura",
        "numeroParcelas": 12
    },
    "AgenteArrecadador": false,
    "CNPJAgenteArrecadador": "12345678000100",
    "CNPJCorretora": "12345678000100",
    "CodigoSusepCorretora": 123456,
    "RazaoSocialCorretora": "Razão Social"
}'

```

```

#### Estrutura da requisição para buscar a proposta criada 
https://openapi-uat.tooseguros.com.br/seguros/residenciais/proposta/{{numeroProposta}} Corpo da Requisição 
GET 'https://app-hom-gateway.tooseguros.com.br/proposta/residencial/0114010000001' \ 
```

```
--header 'clientId: 4dec8c1f8f6d4f0bb89cdc251e2c83b0' \
--header 'clientSecret: 85a196bff47f4fd8943ac311ec7c5430' \
--header 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2OTBkMjViMTgzNzk0MDY4YTZmNmY1NTk2YzNkMmNjZCIsImp0aSI6ImE3ZDFiODJhLTcyZmMtNDY2My05ZDE5LTlmOTkzNWM3NDBkOSIsImlhdCI6MTYzNzMyNTU3NSwiSXNVc2VyIjoiZmFsc2UiLCJVc2VySWQiOiIiLCJuYmYiOjE2MzczMjUyNzUsImV4cCI6MTYzNzM2MTU3NSwiaXNzIjoiUGFuU2VndXJvc0lzc3VlciIsImF1ZCI6IlBhblNlZ3Vyb3NBdWRpZW5jZSJ9.GQ34taoWtqjkxk2NovuDT_YU9_sM41Innrp7QaJ26q0'

```

```

#### Estrutura da requisição para buscar a papelaria do Bilhete ou Proposta 
https://openapi-uat.tooseguros.com.br/arquivos/{{codigopapelaria}}  Corpo da Requisição 
GET 'https://app-hom-gateway.tooseguros.com.br/arquivos/ebab2e46-7a88-4591-af8f-a1ad6bbae5d0' \ 
```

```
--header 'clientId: 4dec8c1f8f6d4f0bb89cdc251e2c83b0' \
--header 'clientSecret: 85a196bff47f4fd8943ac311ec7c5430' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJzdWIiOiI2OTBkMjViMTgzNzk0MDY4YTZmNmY1NTk2YzNkMmNjZCIsImp0aSI6ImE3ZDFiODJhLTcyZmMtNDY2My05ZDE5LTlmOTkzNWM3NDBkOSIsImlhdCI6MTYzNzMyNTU3NSwiSXNVc2VyIjoiZmFsc2UiLCJVc2VySWQiOiIiLCJuYmYiOjE2MzczMjUyNzUsImV4cCI6MTYzNzM2MTU3NSwiaXNzIjoiUGFuU2VndXJvc0lzc3VlciIsImF1ZCI6IlBhblNlZ3Vyb3NBdWRpZW5jZSJ9.GQ34taoWtqjkxk2NovuDT_YU9_sM41Innrp7QaJ26qw'

```

```


<!-- ======== too-contratacao-propostav2.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Proposta Residencial - API Contratação Proposta v2 

#### Para que serve? 
Na versão v2 estamos disponibilizando uma API onde possa ser possível enviar dados de pessoas jurídicas e não somente de pessoas físicas como na v1.  

#### Importante 
Essa forma de emissão como PJ ainda não esta disponível para imóveis para fins comerciais (bares, restaurantes, salas comerciais …) .  

#### Rota 
Segue abaixo, a rota para efetuar a contratação de proposta v2: 
```
https://openapi-uat.tooseguros.com.br/seguros/residenciais/proposta/v2
```

#### Principais modificações 
Para essa nova versão (v2) dentre as principais modificações em relação a v1 temos: 
 Nó de Segurado  
Aqui deixamos os campos em comum entre os tipos de pessoas (PF/PJ) na raiz do nó de segurado, enquanto aqueles dados mais específicos foram migrados para seus devidos nós tipificados como seguradoPessoaJuridica ou seguradoPessoaFisica. 
Para a identificação de qual tipo de pessoa que se trata, criamos a tipificação tipoPessoaque deverá ser preenchida com:  
- PF - para caso a contratação seja feita por uma pessoa física ou  
- PJ - para caso a contratação seja feita por uma pessoa jurídica.  
 Exemplo de objeto de Pessoa Física 
```

```

				"segurado": {
        "tipoPessoa": "PF", 
        "autorizaComunicacao": true, // true ou false
        "emails": [
            "tooseguros@tooseguros.com.br"
        ],
        "telefones": [
            {
                "ddd": "11",
                "numero": "999009999",
                "tipoTelefone": "Celular",
                "tiposNotificacao": [
                    "SMS" 
                ]
            }
        ],
        "pessoaPoliticamenteExposta": false, // true ou false
        "endereco": {
            "bairro": "Cerqueira César",
            "cep": "01310-200",
            "cidade": "São Paulo",
            "complemento": "13o andar",
            "estado": "SP",
            "logradouro": "Av. Paulista",
            "numero": "1374"
        },
        "seguradoPessoaFisica": {
            "nome": "Too Seguros",
            "sexo": "Masculino", //Masculino ou Feminino
            "dataNascimento": "2000-01-01",
            "cpf": "53698437082"
        }     
    }

```

```

 Exemplo de objeto de Pessoa Jurídica 
```
		```
			"segurado": {
    "tipoPessoa": "PJ", //PF, PJ
    "autorizaComunicacao": true, // true ou false
    "emails": [
        "tooseguros@tooseguros.com.br"
    ],
    "telefones": [
        {
            "ddd": "11",
            "numero": "999009999",
            "tipoTelefone": "Celular",
            "tiposNotificacao": [
                "SMS" 
            ]
        }
    ],
    "pessoaPoliticamenteExposta": false, // true ou false
    "endereco": {
        "bairro": "Cerqueira César",
        "cep": "01310-200",
        "cidade": "São Paulo",
        "complemento": "13o andar",
        "estado": "SP",
        "logradouro": "Av. Paulista",
        "numero": "1374"
    },
    "seguradoPessoaJuridica": {
        "cnpj": "47998363000121",
        "razaoSocial": "Too Seguros",
        "nomeFantasia": "Too Seguros",
        "inscricaoEstadual" : "",
        "inscricaoMunicipal": ""
    }     
}
	```
		
```

 Informação do Objeto Estipulante  
O campo estipulante deverá ser informado, quando for enviado propostas individuais, vinculadas a um contratante, que representa o grupo segurado, que contratou uma apólice coletiva de seguros, e a obrigatoriedade da informação do campo, se dá apenas para parceiros configurados para este tipo de contrato. 
Abaixo segue o exemplo de um novo nó Estipulante e,que devera adicionado a requisição:  
 
```

```

"Estipulante": {
    "Cnpj":"36925604000196",
    "RazaoSocial": "Too Seguros LTDA",
    "NomeFantasia": "TooSeguros" }	

```

```

 Organização do Nó de Corretora  
Aproveitamos para organizar os campos referentes ao objeto Corretora de forma que ficassem de mais fácil compreensão. 
Com isso, criamos um novo nó Corretora que ficará assim:  
 
```

```

"Corretora": {
    "CNPJ": "33245762000107",
    "CodigoSusep": 517,
    "RazaoSocial": "Too Seguros LTDA"
}

```

```

 Exemplo de solicitação de contratação Pessoa Física 
Para exemplificar o uso da nova versão v2 da API para Pessoa Física, segue abaixo o code snippet em curl via Postman: 
```

```

				curl --location --request POST 'https://tooseguros-dev.apigee.net/seguros/residenciais/proposta/v2' \
--header 'clientId: ffc55916992c45b1b6722ef2ed2fa52c' \
--header 'clientSecret: 626a2d7eace44645bd75138083b545dd' \
--header 'Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICIzYjNBQWx6VWN4UVJ2S2tmNlNqd01Tbk1vSlNXRjZkbEc0NHBRdUJaMFpvIn0.eyJleHAiOjE2NDQ1MTA5MTEsImlhdCI6MTY0NDUwNzMxMCwianRpIjoiMzlmMGY3MzEtNTUyMS00YmJhLWEwMWYtNWZjZDcxM2EyYzU5IiwiaXNzIjoiaHR0cHM6Ly90b29zZWd1cm9zLWRldi5hcGlnZWUubmV0L2tleWNsb2FrL2F1dGgvcmVhbG1zL29wZW5pZC1iYWNrZW5kLWt1YmUiLCJzdWIiOiI3Y2I5MTY0MS1lMDVlLTQyMDMtYjhjOC1jMTNlMzVlMmE3MjEiLCJ0eXAiOiJCZWFyZXIiLCJhenAiOiIzZmI4NDUxODhiZWI0ZGNiOGIzYmIwOWRjOTU0YzBkMyIsImFjciI6IjEiLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsicHJvZHV0b3NfcGVzc29haXMiLCJhcnF1aXZvc19taWNyb3NlcnZpY2UiLCJiaWxoZXRlX3Jlc2lkZW5jaWFsIiwid2ViaG9vay1jb21wb25lbnRlcyIsIndlYmhvb2tfcmVzaWRlbmNpYWwiLCJwYXBlbGFyaWFfcmVzaWRlbmNpYWwiLCJjb3RhY29lc19wZXNzb2FpcyIsInByb3Bvc3RhX3Jlc2lkZW5jaWFsIiwiY2xpZW50ZXNfbWljcm9zZXJ2aWNlIiwiYXJxdWl2b3MtbWljcm9zZXJ2aWNlIiwib3Bpbi1yZXNpZGVudGlhbCIsImNvdGFjYW9fcmVzaWRlbmNpYWwiXX0sInNjb3BlIjoiZW1haWwgcHJvZmlsZSIsImNsaWVudElkIjoiM2ZiODQ1MTg4YmViNGRjYjhiM2JiMDlkYzk1NGMwZDMiLCJjbGllbnRIb3N0IjoiMTAuMTkxLjEyNS4xNDQiLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsInByZWZlcnJlZF91c2VybmFtZSI6InNlcnZpY2UtYWNjb3VudC0zZmI4NDUxODhiZWI0ZGNiOGIzYmIwOWRjOTU0YzBkMyIsImNsaWVudEFkZHJlc3MiOiIxMC4xOTEuMTI1LjE0NCIsImF1dGhvcml6ZWRfYXBwcyI6WyJwcm9kdXRvc19wZXNzb2FpcyIsImFycXVpdm9zX21pY3Jvc2VydmljZSIsImJpbGhldGVfcmVzaWRlbmNpYWwiLCJ3ZWJob29rLWNvbXBvbmVudGVzIiwid2ViaG9va19yZXNpZGVuY2lhbCIsInBhcGVsYXJpYV9yZXNpZGVuY2lhbCIsImNvdGFjb2VzX3Blc3NvYWlzIiwicHJvcG9zdGFfcmVzaWRlbmNpYWwiLCJjbGllbnRlc19taWNyb3NlcnZpY2UiLCJhcnF1aXZvcy1taWNyb3NlcnZpY2UiLCJvcGluLXJlc2lkZW50aWFsIiwiY290YWNhb19yZXNpZGVuY2lhbCJdfQ.C_M9CyEeY05P8AcbexU9J-0SRGkZz1O58viUCu9xvUk7mAgDHTw9oI21y1h5SCSxfSzhSw1t7y1eIwkI2Nsbx2gnnCwuSfcGMEgmTFIRU-qq4nE3_88Q_ztai12qmx7lOAvN_unGKHJ-USDMFs3zKkbqqqqymbb5UjDHAueDE46LycbJ1hwaeiXZZJH6wWw9XPzrmqvXon9nqdkERra8M9xCCsZG-sCmdDlz2UoDBCvfQQ9eJmL0hdHWMbvfpAI_pjHAEPh06Y4cgbJ5M3nOvC63LgYtJlLqNaoIfrx9edjr30yvIRtde8_N2T5Cjv08dlt0ooqpcMorzz3YgDtO5A' \
--header 'Content-Type: application/json' \
--data-raw '{
    "codigoCotacao": "722d5376-2691-415c-92e1-7c1320ca0000",
    "codigoProduto": "457",
    "codigoPlano": "001",
    "enderecoRisco": {
        "tipoResidencia": "Apartamento", //Apartamento ou Casa
        "bairro": "Cerqueira César",
        "cep": "01310-200",
        "cidade": "São Paulo",
        "complemento": "13o andar",
        "estado": "SP",
        "logradouro": "Av. Paulista",
        "numero": "1374"
    },
    "segurado": {
        "tipoPessoa": "PF", 
        "autorizaComunicacao": true, // true ou false
        "emails": [
            "tooseguros@tooseguros.com.br"
        ],
        "telefones": [
            {
                "ddd": "11",
                "numero": "999009999",
                "tipoTelefone": "Celular",
                "tiposNotificacao": [
                    "SMS" 
                ]
            }
        ],
        "pessoaPoliticamenteExposta": false, // true ou false
        "endereco": {
            "bairro": "Cerqueira César",
            "cep": "01310-200",
            "cidade": "São Paulo",
            "complemento": "13o andar",
            "estado": "SP",
            "logradouro": "Av. Paulista",
            "numero": "1374"
        },
        "seguradoPessoaFisica": {
            "nome": "Too Seguros",
            "sexo": "Masculino", //Masculino ou Feminino
            "dataNascimento": "2000-01-01",
            "cpf": "53698437082"
        }     
    },
    "pagamento": {
        "formaPagamento": "Fatura", //Fatura ou Boleto
        "numeroParcelas": 12
    },
    "AgenteArrecadador": false, // true ou false
    "CNPJAgenteArrecadador": "35602536000161",
    "Corretora": {
        "CNPJ": "33245762000107",
        "CodigoSusep": 517,
        "RazaoSocial": "Too Seguros LTDA"
    },
		"Estipulante": {
        "Cnpj":"36925604000196",
        "RazaoSocial": "Too Seguros LTDA",
        "NomeFantasia": "TooSeguros"
    }
}'

```

```

 Exemplo de solicitação de contratação Pessoa Jurídica 
Para exemplificar o uso da nova versão v2 da API para Pessoa Jurídica, segue abaixo o code snippet em curl via Postman: 
```

			curl --location --request POST 'https://tooseguros-dev.apigee.net/seguros/residenciais/proposta/v2' \
--header 'clientId: ffc55916992c45b1b6722ef2ed2fa52c' \
--header 'clientSecret: 626a2d7eace44645bd75138083b545dd' \
--header 'Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICIzYjNBQWx6VWN4UVJ2S2tmNlNqd01Tbk1vSlNXRjZkbEc0NHBRdUJaMFpvIn0.eyJleHAiOjE2NDQ1MTA5MTEsImlhdCI6MTY0NDUwNzMxMCwianRpIjoiMzlmMGY3MzEtNTUyMS00YmJhLWEwMWYtNWZjZDcxM2EyYzU5IiwiaXNzIjoiaHR0cHM6Ly90b29zZWd1cm9zLWRldi5hcGlnZWUubmV0L2tleWNsb2FrL2F1dGgvcmVhbG1zL29wZW5pZC1iYWNrZW5kLWt1YmUiLCJzdWIiOiI3Y2I5MTY0MS1lMDVlLTQyMDMtYjhjOC1jMTNlMzVlMmE3MjEiLCJ0eXAiOiJCZWFyZXIiLCJhenAiOiIzZmI4NDUxODhiZWI0ZGNiOGIzYmIwOWRjOTU0YzBkMyIsImFjciI6IjEiLCJyZWFsbV9hY2Nlc3MiOnsicm9sZXMiOlsicHJvZHV0b3NfcGVzc29haXMiLCJhcnF1aXZvc19taWNyb3NlcnZpY2UiLCJiaWxoZXRlX3Jlc2lkZW5jaWFsIiwid2ViaG9vay1jb21wb25lbnRlcyIsIndlYmhvb2tfcmVzaWRlbmNpYWwiLCJwYXBlbGFyaWFfcmVzaWRlbmNpYWwiLCJjb3RhY29lc19wZXNzb2FpcyIsInByb3Bvc3RhX3Jlc2lkZW5jaWFsIiwiY2xpZW50ZXNfbWljcm9zZXJ2aWNlIiwiYXJxdWl2b3MtbWljcm9zZXJ2aWNlIiwib3Bpbi1yZXNpZGVudGlhbCIsImNvdGFjYW9fcmVzaWRlbmNpYWwiXX0sInNjb3BlIjoiZW1haWwgcHJvZmlsZSIsImNsaWVudElkIjoiM2ZiODQ1MTg4YmViNGRjYjhiM2JiMDlkYzk1NGMwZDMiLCJjbGllbnRIb3N0IjoiMTAuMTkxLjEyNS4xNDQiLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsInByZWZlcnJlZF91c2VybmFtZSI6InNlcnZpY2UtYWNjb3VudC0zZmI4NDUxODhiZWI0ZGNiOGIzYmIwOWRjOTU0YzBkMyIsImNsaWVudEFkZHJlc3MiOiIxMC4xOTEuMTI1LjE0NCIsImF1dGhvcml6ZWRfYXBwcyI6WyJwcm9kdXRvc19wZXNzb2FpcyIsImFycXVpdm9zX21pY3Jvc2VydmljZSIsImJpbGhldGVfcmVzaWRlbmNpYWwiLCJ3ZWJob29rLWNvbXBvbmVudGVzIiwid2ViaG9va19yZXNpZGVuY2lhbCIsInBhcGVsYXJpYV9yZXNpZGVuY2lhbCIsImNvdGFjb2VzX3Blc3NvYWlzIiwicHJvcG9zdGFfcmVzaWRlbmNpYWwiLCJjbGllbnRlc19taWNyb3NlcnZpY2UiLCJhcnF1aXZvcy1taWNyb3NlcnZpY2UiLCJvcGluLXJlc2lkZW50aWFsIiwiY290YWNhb19yZXNpZGVuY2lhbCJdfQ.C_M9CyEeY05P8AcbexU9J-0SRGkZz1O58viUCu9xvUk7mAgDHTw9oI21y1h5SCSxfSzhSw1t7y1eIwkI2Nsbx2gnnCwuSfcGMEgmTFIRU-qq4nE3_88Q_ztai12qmx7lOAvN_unGKHJ-USDMFs3zKkbqqqqymbb5UjDHAueDE46LycbJ1hwaeiXZZJH6wWw9XPzrmqvXon9nqdkERra8M9xCCsZG-sCmdDlz2UoDBCvfQQ9eJmL0hdHWMbvfpAI_pjHAEPh06Y4cgbJ5M3nOvC63LgYtJlLqNaoIfrx9edjr30yvIRtde8_N2T5Cjv08dlt0ooqpcMorzz3YgDtO5A' \
--header 'Content-Type: application/json' \
--data-raw '{
    "codigoCotacao": "722d5376-2691-415c-92e1-7c1320ca0000",
    "codigoProduto": "457",
    "codigoPlano": "001",
    "enderecoRisco": {
        "tipoResidencia": "Apartamento", //Apartamento ou Casa
        "bairro": "Cerqueira César",
        "cep": "01310-200",
        "cidade": "São Paulo",
        "complemento": "13o andar",
        "estado": "SP",
        "logradouro": "Av. Paulista",
        "numero": "1374"
    },
    "segurado": {
        "tipoPessoa": "PJ", //PF, PJ
        "autorizaComunicacao": true, // true ou false
        "emails": [
            "tooseguros@tooseguros.com.br"
        ],
        "telefones": [
            {
                "ddd": "11",
                "numero": "999009999",
                "tipoTelefone": "Celular",
                "tiposNotificacao": [
                    "SMS" 
                ]
            }
        ],
        "pessoaPoliticamenteExposta": false, // true ou false
        "endereco": {
            "bairro": "Cerqueira César",
            "cep": "01310-200",
            "cidade": "São Paulo",
            "complemento": "13o andar",
            "estado": "SP",
            "logradouro": "Av. Paulista",
            "numero": "1374"
        },
        "seguradoPessoaJuridica": {
            "cnpj": "47998363000121",
            "razaoSocial": "Too Seguros",
            "nomeFantasia": "Too Seguros",
            "inscricaoEstadual" : "",
            "inscricaoMunicipal": ""
        }     
    },
    "pagamento": {
        "formaPagamento": "Fatura", //Fatura ou Boleto
        "numeroParcelas": 12
    },
    "AgenteArrecadador": false, // true ou false
    "CNPJAgenteArrecadador": "35602536000161",
    "Corretora": {
        "CNPJ": "33245762000107",
        "CodigoSusep": 517,
        "RazaoSocial": "Too Seguros LTDA"
    },
		"Estipulante": {
        "Cnpj":"36925604000196",
        "RazaoSocial": "Too Seguros LTDA",
        "NomeFantasia": "TooSeguros"
    }
}'

```

Você também pode fazer o download da nossa collection do Postman aqui.


<!-- ======== too-cancelamento-propostav2.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Proposta Residencial - API de Solicitação de Cancelamento - v2 

#### Para que serve? 
Nessa versão estamos disponibilizando na API a solicitação de cancelamentos de propostas sem restituição de prêmio para os casos de PJ, deve ser usada para os motivos listados abaixo: 

#### Motivos de Cancelamento  
- Erro de Emissão; 
- Cancelamento por arrependimento do segurado;  
- Cancelamento até 7 dias do segurado; 
- Cancelamento por desistência do seguro.  

#### Rota 
Segue abaixo, a rota para efetuar o cancelamento: 
```
https://openapi-uat.tooseguros.com.br/seguros/residenciais/proposta/cancelar/v2
```

#### Autorização 
Assim como as outras APIs já existentes solicitam a autorização para fins de segurança, para essa nova API de cancelamento também não seria diferente. Seguimos utilizando a autenticação via “Bearer token”. 
Quanto as credenciais, serão as mesmas já utilizadas por cada parceiro. 

#### Quais dados são necessários para solicitar o cancelamento? 
Na requisição da solicitação basta enviar os seguintes dados:  
  | Campo  | Descrição  | Obrigatório  
  | numeroProposta  | Número da proposta residencial do qual se deseja efetuar o cancelamento  | Sim  
  | tipoPessoa  | 
Tipo de pessoa do segurado a fim de identificar se trata de Pessoa Física ou Pessoa Jurídica. 
Obs.: preencher com um dos tipos listados na tabela de tipos de pessoas abaixo.  | Sim  
  | cpf  | CPF do segurado vinculado a proposta do seguro.  | Não, porém caso tipoPessoa = PF será obrigatório  
  | cnpj  | CNPJ do segurado vinculado a proposta do seguro.  | Não, porém caso tipoPessoa = PJ será obrigatório  
  | tipoCancelamento  | 
Tipo de cancelamento que será efetuado. 
Primeiramente estará disponível apenas para o tipo “SemRestituicaoDePremio”. 
Obs.: preencher com um dos tipos de listados na tabela de tipos de cancelamento abaixo.  | Sim  
  | codigoMotivo  | 
Código do motivo do cancelamento. 
Obs.: preencher com um dos códigos listados na tabela de códigos de motivo de cancelamento abaixo.  | Sim   
 Tabela de Tipos de pessoas  
  | Código  | Tipo  | Descrição  
  | 0  | PF  | Pessoa Física  
  | 1  | PJ  | Pessoa Jurídica   
 Tabela de Tipos de Cancelamento  
  | Código  | Tipo  | Descrição  
  | 1  | SemRestituicaoDePremio  | Sem restituição de Prêmio   
 Tabela de Códigos de Motivo de Cancelamento  
  | Código  | Descrição  
  | 1  | Erro de Emissão  
  | 2  | Cancelamento por arrependimento do Segurado  
  | 3  | Cancelamento após 7 dias  
  | 4  | Cancelamento por desistência   

#### Exemplo de solicitação de cancelamento 
 Para exemplificar o uso da API para tipo de pessoa PJ, segue abaixo o code snippet em curl via Postman: 
```

```
curl --location --request POST 'https://openapi-uat.tooseguros.com.br/seguros/residenciais/proposta/cancelar/v2' \
--header 'clientId: 948f8bd0f66a4373876bd9cf879b67b2' \
--header 'clientSecret: a0313390b6f44abeb29438ee15b9d0f6' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJteVFXOGVJeGhEN0FIcDVOeXJVcVR3TDhrdzZsUEtGUmFKZWhxWFA0aWtvIn0.eyJleHAiOjE2NDQ5MzQzMzcsImlhdCI6MTY0NDkzMDczNywianRpIjoiMzU5ZWJhMzMtNGI2NS00Y2NlLTkyYmYtY2IzZGI4ZGUxMmZiIiwiaXNzIjoiaHR0cHM6Ly9vcGVuLWFwaS50b29zZWd1cm9zLmNvbS5ici9rZXljbG9hay9hdXRoL3JlYWxtcy9vcGVuaWQtYmFja2VuZC1rdWJlIiwic3ViIjoiMjlhOWMzYzEtNzE1MS00YTg2LTgwOWItNGUyNmRhNDcyMDE3IiwidHlwIjoiQmVhcmVyIiwiYXpwIjoiNTJiN2I5M2Q1NTEzNDM2ODkxNjAzNzRkMTA0OTdhM2UiLCJhY3IiOiIxIiwicmVhbG1fYWNjZXNzIjp7InJvbGVzIjpbImJpbGhldGVfcmVzaWRlbmNpYWwiLCJ3ZWJob29rX3Jlc2lkZW5jaWFsIiwicGFwZWxhcmlhX3Jlc2lkZW5jaWFsIiwicHJvcG9zdGFfcmVzaWRlbmNpYWwiLCJhcnF1aXZvcy1taWNyb3NlcnZpY2UiLCJjb3RhY2FvX3Jlc2lkZW5jaWFsIl19LCJzY29wZSI6ImVtYWlsIGF1dGhvcml6ZWRfYXBwcyBwcm9maWxlIiwiY2xpZW50SWQiOiI1MmI3YjkzZDU1MTM0MzY4OTE2MDM3NGQxMDQ5N2EzZSIsImNsaWVudEhvc3QiOiIxMC4xOTEuNjYuNjgiLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsInByZWZlcnJlZF91c2VybmFtZSI6InNlcnZpY2UtYWNjb3VudC01MmI3YjkzZDU1MTM0MzY4OTE2MDM3NGQxMDQ5N2EzZSIsImNsaWVudEFkZHJlc3MiOiIxMC4xOTEuNjYuNjgiLCJhdXRob3JpemVkX2FwcHMiOlsiYmlsaGV0ZV9yZXNpZGVuY2lhbCIsIndlYmhvb2tfcmVzaWRlbmNpYWwiLCJwYXBlbGFyaWFfcmVzaWRlbmNpYWwiLCJwcm9wb3N0YV9yZXNpZGVuY2lhbCIsImFycXVpdm9zLW1pY3Jvc2VydmljZSIsImNvdGFjYW9fcmVzaWRlbmNpYWwiXX0.LAJnh_p6PIQ5EcaUJM-nzm6FoPMRGVd9xMr0X2vWCLsAi6YNZ9rmED_MjRBustX_-DmNvObogYgO9J7Vlrq4i9bgoNIW9EN2eK7mItA-jsUFa2WmwMsRX3-eeXQ5I0Z2PnusIqtpg9KoAgK8pnWIeV9NVeNxo-xmUw0j6EROEzzaR9NqY6YkKkTv8XnPmFBf9UEYqBthhtpJqks4-G2d9Q9bq6L3PyLy02mHFRPJdlRokrg68WSZ_3yWcMwKgpvPsV4WrE0JCnkzQTWoRWcOTPsg-7BJYivHSFTHtU6O6p9lQESGesQLF468hYY4lxgyzWuF-9jO_gkIuTpyIo81Dw' \
--data-raw '{
  "numeroProposta": "0114000000210",
  "tipoPessoa": "PJ",
  "cnpj": "21863690000182",
  "tipoCancelamento": "SemRestituicaoDePremio",
  "codigoMotivo": 1
}'

```

```
  
 Para exemplificar o uso da API para tipo de pessoa PF, segue abaixo o code snippet em curl via Postman: 
```

```
curl --location --request POST 'https://openapi-uat.tooseguros.com.br/seguros/residenciais/proposta/cancelar/v2' \
--header 'clientId: 948f8bd0f66a4373876bd9cf879b67b2' \
--header 'clientSecret: a0313390b6f44abeb29438ee15b9d0f6' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCIgOiAiSldUIiwia2lkIiA6ICJteVFXOGVJeGhEN0FIcDVOeXJVcVR3TDhrdzZsUEtGUmFKZWhxWFA0aWtvIn0.eyJleHAiOjE2NDQ5MzQzMzcsImlhdCI6MTY0NDkzMDczNywianRpIjoiMzU5ZWJhMzMtNGI2NS00Y2NlLTkyYmYtY2IzZGI4ZGUxMmZiIiwiaXNzIjoiaHR0cHM6Ly9vcGVuLWFwaS50b29zZWd1cm9zLmNvbS5ici9rZXljbG9hay9hdXRoL3JlYWxtcy9vcGVuaWQtYmFja2VuZC1rdWJlIiwic3ViIjoiMjlhOWMzYzEtNzE1MS00YTg2LTgwOWItNGUyNmRhNDcyMDE3IiwidHlwIjoiQmVhcmVyIiwiYXpwIjoiNTJiN2I5M2Q1NTEzNDM2ODkxNjAzNzRkMTA0OTdhM2UiLCJhY3IiOiIxIiwicmVhbG1fYWNjZXNzIjp7InJvbGVzIjpbImJpbGhldGVfcmVzaWRlbmNpYWwiLCJ3ZWJob29rX3Jlc2lkZW5jaWFsIiwicGFwZWxhcmlhX3Jlc2lkZW5jaWFsIiwicHJvcG9zdGFfcmVzaWRlbmNpYWwiLCJhcnF1aXZvcy1taWNyb3NlcnZpY2UiLCJjb3RhY2FvX3Jlc2lkZW5jaWFsIl19LCJzY29wZSI6ImVtYWlsIGF1dGhvcml6ZWRfYXBwcyBwcm9maWxlIiwiY2xpZW50SWQiOiI1MmI3YjkzZDU1MTM0MzY4OTE2MDM3NGQxMDQ5N2EzZSIsImNsaWVudEhvc3QiOiIxMC4xOTEuNjYuNjgiLCJlbWFpbF92ZXJpZmllZCI6ZmFsc2UsInByZWZlcnJlZF91c2VybmFtZSI6InNlcnZpY2UtYWNjb3VudC01MmI3YjkzZDU1MTM0MzY4OTE2MDM3NGQxMDQ5N2EzZSIsImNsaWVudEFkZHJlc3MiOiIxMC4xOTEuNjYuNjgiLCJhdXRob3JpemVkX2FwcHMiOlsiYmlsaGV0ZV9yZXNpZGVuY2lhbCIsIndlYmhvb2tfcmVzaWRlbmNpYWwiLCJwYXBlbGFyaWFfcmVzaWRlbmNpYWwiLCJwcm9wb3N0YV9yZXNpZGVuY2lhbCIsImFycXVpdm9zLW1pY3Jvc2VydmljZSIsImNvdGFjYW9fcmVzaWRlbmNpYWwiXX0.LAJnh_p6PIQ5EcaUJM-nzm6FoPMRGVd9xMr0X2vWCLsAi6YNZ9rmED_MjRBustX_-DmNvObogYgO9J7Vlrq4i9bgoNIW9EN2eK7mItA-jsUFa2WmwMsRX3-eeXQ5I0Z2PnusIqtpg9KoAgK8pnWIeV9NVeNxo-xmUw0j6EROEzzaR9NqY6YkKkTv8XnPmFBf9UEYqBthhtpJqks4-G2d9Q9bq6L3PyLy02mHFRPJdlRokrg68WSZ_3yWcMwKgpvPsV4WrE0JCnkzQTWoRWcOTPsg-7BJYivHSFTHtU6O6p9lQESGesQLF468hYY4lxgyzWuF-9jO_gkIuTpyIo81Dw' \
--data-raw '{
  "numeroProposta": "0114000000210",
  "tipoPessoa": "PF",
  "cpf": "53698437082",
  "tipoCancelamento": "SemRestituicaoDePremio",
  "codigoMotivo": 1
}'

```

```

Você também pode fazer o download da nossa collection do Postman aqui.


<!-- ======== too-webhook.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Notificações via Webhook 
O papel do Webhook é entregar mensagens via HTTP Post para aplicações externas assim que um evento ocorre durante o processo. 

#### API Callback 
É desenvolvida pelo parceiro uma API que será responsável por efetuar a recepção das notificações. 
 A Too envia as notificações de BilheteCriado, PapelariaBilheteResidencialGerado, PapelariaApoliceGerada e PapelariaCertificadoGerado.  

#### Como se inscrever nos eventos do webhook? 
Para se inscrever nos eventos, o parceiro deverá enviar uma mensagem via suporte neste link com as urls para cada um dos 3 eventos listados abaixo 

#### Segurança 
As urls de callback poderão conter autenticação Basic caso necessário, não esquecendo de informar o usuário e senha para a devida configuração em nossa plataforma. 

#### Evento da API do Residencial  
-  BilheteCriado - ocorre quando é feita a criação de um novo bilhete. 
 Payload 
```

                {
                    "Id": "8c01ba89-bf1d-4e62-a997-795612d85dad",
											"NumeroBilhete": "01142800000123",
											"DataSolicitacao": "2021-11-30T10:07:48.5817372-03:00",
											"DataOwnerClientId": "690d25b183794068a6f6f5596c3d2123",
											"Assistencia": 2,
											"AutorizaEnvioComunicacoes": true,
											"Coberturas": [
											{
													 "Descricao": "Incendio",
													 "LimiteMaximoIndenizacao": 150000.00,
													 "Carencia": "Não há",
													 "Franquia": " Não há",
													 "CustoSeguro": 67.33,
													 "PremioBruto": 67.33,
													 "ImportanciaSegurada": 150000.00,
													 "LimiteMaximoGarantia": 150000.00
											},
											{
													 "Descricao": "RC",
													 "LimiteMaximoIndenizacao": 15000.00,
													 "Carencia": "Não há",
													 "Franquia": "Não há",
													 "CustoSeguro": 5.37,
													 "PremioBruto": 5.37,
													 "ImportanciaSegurada": 15000.00,
													 "LimiteMaximoGarantia": 150000.00
											},
											{
													 "Descricao": "Perda de Aluguel",
													 "LimiteMaximoIndenizacao": 7500.00,
													 "Carencia": "Não há",
													 "Franquia": "Não há",
													 "CustoSeguro": 0.66,
													 "PremioBruto": 0.66,
													 "ImportanciaSegurada": 7500.00,
													 "LimiteMaximoGarantia": 150000.00
										  },
											{
											     "Descricao": "Danos eletricos",
													 "LimiteMaximoIndenizacao": 1500.00,
													 "Carencia": "Não há",
													 "Franquia": "Não há",
													 "CustoSeguro": 10.59,
													 "PremioBruto": 10.59,
													 "ImportanciaSegurada": 1500.00,
													 "LimiteMaximoGarantia": 150000.00
										  },
											{
											     "Descricao": "Vendaval",
													 "LimiteMaximoIndenizacao": 3000.00,
													 "Carencia": "Não há",
													 "Franquia": "Não há",
													 "CustoSeguro": 5.94,
													 "PremioBruto": 5.94,
													 "ImportanciaSegurada": 3000.00,
													 "LimiteMaximoGarantia": 150000.00
										}
							],
							"CodigoProduto": "999",
							"Corretor": {
							      "Nome": "Nome da Corretora LTDA",
										"Cnpj": "99999999000199",
										"CodigoSusep": "999999"
								},
							"Cpf": "99999999999",
							"CustoLiquidoTotal": 83.71,
							"CustoBrutoTotal": 89.89,
							"DataFimVigencia": "2022-11-29T04:10:43.972-03:00",
							"DataInicioVigencia": "2021-11-30T04:10:43.972-03:00",
							"Email": "email@email.com.br",
							"EnderecoRisco": {
							      "Bairro": "Bairro",
										"Cep": "99999-999",
										"Cidade": "Cidade",
										"Complemento": "Complemento",
										"Estado": "SP",
										"Logradouro": "Logradouro",
										"Numero": "123"
							 },
							 "Endereco": {
							      "Bairro": " Bairro ",
										"Cep": "99999-999",
										"Cidade": " Cidade ",
										"Complemento": " Complemento ",
										"Estado": "SP",
										"Logradouro": " Logradouro ",
										"Numero": "123"
							 },								 
							"FormaPagamento": "Fatura",
							"IOF": 6.18,
							"ImportanciaSegurada": 0.0,
							"LimiteMaximoGarantia": 750000.00,
							"LimiteMaximoIndenizacao": 0.0,
							"Nome": "Nome do Segurado",
							"PessoaPoliticamenteExposta": false,
							"Status": 2,
							"Telefone": "47 992157345",
							"ValorPremio": 89.89,
							"CnpjCorretor": null,
							"PercentualCorretagem": 0.0,
							"Ramo": 14,
							"Sexo": 1,
							"DataNascimento": "1980-01-01T00:00:00-03:00",
							"Parcelas": null
							}                    
            
```
   
-  PapelariaBilheteResidencialGerado - ocorre assim que a papelaria do bilhete é gerada com sucesso. 
 Payload 
```

                {
                    "Id":"46059e28-ff44-48b4-bc5f-87811c8e18b0",
                    "IdPapelaria": "844b06f1-2d4b-4d92-85d7-6e501e969400",
                    "IdArquivo": "36630bfe-b5cf-4504-991b-82f7441ae000",
                    "NumeroBilhete": "01142800000123",
                    "LinkPDF": "https://api.tooseguros.com.br/arquivos/36630bfe-b5cf-4504-991b-82f7441ae000",
                    "DataOwnerClientId": "690d25b183794068a6f6f5596c3d2123"          
                 }
            
```
   
-  PapelariaApoliceGerada - ocorre assim que a papelaria do bilhete é gerada com sucesso. 
 Payload 
```

                {
                    "Id": "46059e28-ff44-48b4-bc5f-87811c8e18b0",
                    "IdPapelaria": "844b06f1-2d4b-4d92-85d7-6e501e969400",
                    "NumeroApolice": "01142800000321",
                    "NumeroProposta": "01142800000123",
                    "NumeroCertificado": "01142800000999",
                    "Ramo": 114,
                    "IdArquivo": "36630bfe-b5cf-4504-991b-82f7441ae000",
                    "DataGeracao": "2022-11-30T04:10:43.972-03:00",
                    "LinkPDF": "https://api.tooseguros.com.br/arquivos/36630bfe-b5cf-4504-991b-82f7441ae000",
                    "DataOwnerClientId": "690d25b183794068a6f6f5596c3d2123"
                 }
            
```
   
-  PapelariaCertificadoGerado - ocorre assim que a papelaria do certificado de uma proposta é gerada com sucesso. 
 Payload 
```

                {
                    "Id": "46059e28-ff44-48b4-bc5f-87811c8e18b0",
                    "IdPapelaria": "844b06f1-2d4b-4d92-85d7-6e501e969400",
                    "NumeroProposta": "01142800000123",
                    "Ramo": 114,
                    "NumeroApolice": "01142800000321",
                    "NumeroCertificado": "01142800000999",
                    "IdArquivo": "36630bfe-b5cf-4504-991b-82f7441ae000",
                    "DataGeracao": "2022-11-30T04:10:43.972-03:00",
                    "LinkPDF": "https://api.tooseguros.com.br/arquivos/36630bfe-b5cf-4504-991b-82f7441ae000",
                    "DataOwnerClientId": "690d25b183794068a6f6f5596c3d2123"
                 }
            
```


<!-- ======== too-instrucoes-para-testes.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Instruções para Testes 
Caro parceiro, recomendamos atentar-se aos valores pré-fixados em contrato que deverão ser repassados nos devidos campos de cada requisição (cotação, bilhete/proposta). 

#### Alinhamentos 
Enviar conforme valores pré-fixados e acordados em contrato:  
- CodigoProduto; 
- Assistencia; 
- TipoImovel; 
- Remuneracao; 
- ValorComissao; 
- FatorAgravo; 
- NumeroParcelas; 
- AgenteArrecadador; 
- CNPJAgenteArrecadador; 
- E os campos de coberturas.  
CodigoPlano: enviar sempre como “001”. 
CNPJCorretora, CodigoSusepCorretora, RazaoSocialCorretora: dados da corretora para a emissão da papelaria. 
Valores monetários: devem ser descritos apenas com a separação do decimal com ponto, ou seja, devem estar no seguinte formato: 0000.00. Exemplo: 1234567.89. 
Remuneracao, ValorComissao, FatorAgravo: caso possua decimal, seguir a mesma recomendação de valores monetários. 
CPF: completar com zeros a esquerda e enviar no formato string sem pontos e traços. Exemplo: “01234567890”. 

#### Campos booleanos  
- AutorizaComunicacao; 
- PessoaPoliticamenteExposta; 
- AgenteArrecadador; 
- DanosEletricos; 
- ResponsabilidadeCivil; 
- PerdaOuPagamentoAluguel; 
- VendavalEImpactoVeiculoTerrestre; 
- RouboOuFurto; 
- Incendio; 
- VidrosEspelhosEMarmores; 
- E VazamentoDeTubulacoes.  
 somente enviar true ou false. 

#### Datas 
Devem estar sempre no formato aaaa-mm-dd. Exemplo: 2021-11-30. 

#### Demais domínios 
Seguir as possíveis opções conforme abaixo:  
- Assistencia: SemAssistencia, Basica ou Completa 
- TipoImovel e TipoResidencia: Casa ou Apartamento 
- TipoTelefone: Celular ou Fixo 
- TiposNotificacao: SMS 
- Sexo: Feminino ou Masculino 
- FormaPagamento: Fatura ou Boleto(inativo no momento)  

#### Collection no Postman 
Para auxiliar os testes, preparamos uma Collection no Postman (faça download clicando aqui - clique com o botão direito, depois em Salvar link como e salve o arquivo da Collection). 
Basta você importar para o Postman e fazer as substituições necessárias. 
É importante alertar que essa Collection deverá ser utilizado somente para ajudar a realizar seus testes e entender o comportamento da API.


<!-- ======== too-duvidas-gerais.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Dúvidas Gerais  
-  

#### Por onde eu devo começar? 
Sugerimos iniciar pela leitura da nossa documentação Aqui 
Deverá ser solicitada por você as credenciais de autenticação em nosso canal de suporte Aqui 
Também é possível baixar nossa colection do Postman Aqui para conhecer os endpoints  
-  

#### Após a minha homologação, quais são os próximos passos para utilizar a API em Produção? 
Além de solicitar as credenciais de produção deverá aguardar um prazo máximo de X dias a fim de configurarmos o ambiente de produção com as mesmas taxas utilizadas durante o período de homologação. 
Também será necessário enviar a url de callback para envio do webhook.  
-  

#### Por quanto tempo o token é válido? 
O Token expira a cada 10h ou 36000 milissegundos  
-  

#### É possível realizar o cancelamento de uma apólice via API? 
Somente Cancelamento Sem Restituicao de Prêmio 
Para os demais casos é necessário abrir uma solicitação via suporte neste link e informar o motivo  
-  

#### Quais são os eventos disparados pelo Webhook?  
-  Quando um bilhete é criado  
-  Quando a papelaria de um bilhete é gerada  
-  Quando a papelaria de uma apólice é gerada   
Veja mais em Webhook  
-  

#### Quais são os possíveis status de um Bilhete?  
- 1 - EmissaoSolicitada 
- 2 - Emitido 
- 3 - Cancelado 
- 4 - CancelamentoSolicitado 
- 5 - Criado 
- 6 - ErroEmissao 
- 7 - EmitidoComErro 
- 8 - ErroEmissaoSolicitada  

#### Quais são os possíveis status de uma Proposta?  
- 1 - Criada 
- 2 - EmissaoSolicitada 
- 3 - ErroEmissao 
- 4 - Emitida 
- 5 - EmitidaComErro 
- 6 - Cancelada 
- 7 - CancelamentoSolicitado 
- 8 - ErroEmissaoSolicitada   
-  

#### No ambiente de homologação eu devo utilizar dados reais? 
Não é necessário e também recomendamos que utilize dados fictícios para efeitos de testes. 
Porém os dados de configuração devem ser dados reais já acordados e alinhados com a Too.


<!-- ======== too-disponibilidade-e-performance.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenciais 

### Disponibilidade e Performance 

#### A disponibilidade da API do Residencial é a mesma dos serviços fornecidos por outros canais.   
-  Ambiente de Produção: está disponível 24h, 7 dias por semana.  
-  Ambiente de Homologação: está disponível das 9h às 20h (horário de Brasília), de segunda à sexta)   

#### Costumeiramente, nossos recursos respondem em milissegundos.


<!-- ======== too-especificacao-openapi.html ======== -->

#### 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
- 
-  

## API Seguros Residenicias 

### Especificação OpenAPI 
Você pode fazer download da Especificação OpenAPI 3.0 clicando:   
- aqui para Bilhete 
- aqui para Cotação 
- aqui para Proposta 
- aqui para Proposta_v2  
Para melhor visualização, sugerimos que você copie o conteúdo do link no Swagger Editor (abra clicando aqui) ou na ferramenta de sua preferência. 
Você também pode fazer o download da nossa collection do Postman aqui.
