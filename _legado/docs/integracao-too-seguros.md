# Integração Too Seguros — Fiança Locatícia (pendente)

**Situação (16/10/2026):** **integrada e desligada** — liga sozinha quando as credenciais entrarem no `.env`.
Testada de ponta a ponta contra um simulador da API (formatos da doc V2): ficha → análise → biometria → prêmio →
proprietário/pagamento → transmissão → apólice → PDFs. O homologação real respondeu `401 invalid_client` sem
credencial (endereço e formato do login confirmados). **Falta:** credenciais, razão social da corretora, confirmar o
CNPJ da corretora (preenchido com o mesmo da Pottencial) e a logo oficial da Too.

Pra ligar, preencher no `.env`: `TOO_CLIENT_ID`, `TOO_CLIENT_SECRET`, `TOO_RAZAO_SOCIAL_CORRETOR` (e conferir
`TOO_CNPJ_CORRETOR`, `TOO_COMISSAO`, `TOO_PERIODO_INDENITARIO`). Pontos a validar no 1º teste real: formato das
datas (usamos `aaaa-mm-dd`), `tipoPessoa` do proprietário (`Fisica`/`Juridica`) e o campo do número da proposta
na resposta do registro da ficha (lemos `numeroProposta`).

## Documentação

- Portal: https://developers.tooseguros.com.br/documentacao/api-fianca-locaticia-visao-geral-do-produto
- Cópia local: `mps/api-too-fianca.md` (Regras e Payloads V2, webhook, status, regras) e `mps/api-too-fianca-openapi.yaml`.
  - A OpenAPI publicada é a **V1**; a V2 está "em breve". Seguir a página **Regras e Payloads V2**.
- Suporte e pedido de credenciais: https://tooseguros.atlassian.net/servicedesk/customer/portal/23
- Homologação: `https://openapi-uat.tooseguros.com.br` — funciona **só em dias úteis, das 8h30 às 20h**.
  - Crédito é aprovado automaticamente com CEP de SP ou MG e reprovado com CEP de PE.
  - Biometria aprovada com os CPFs 093.892.550-40 e 390.794.867-01.

## O que falta para começar

1. **Credenciais de homologação** (Client ID e Client Secret), pedidas no suporte da Too.
2. **Dados comerciais:**
   - CNPJ e razão social da corretora;
   - percentual de comissão (10% a 40%);
   - período indenitário padrão (12, 15, 18, 24 ou 30 meses).
3. **Aprovação para pedir mais dados no formulário e no modal do proprietário** (ver abaixo).

## Fluxo da Too × nosso adapter

| Contrato do adapter | Endpoint(s) da Too |
|---|---|
| login | `POST /authentication` (Basic clientId:secret, client_credentials; token de 10 h) |
| `cotar` | `POST /fianca/proposta/ficha` → `POST /fianca/credito/{cpf}/{proposta}/analisar` (assíncrono, até 2 h úteis) |
| `consultar` | `GET /fianca/proposta/v3/{cpf}/status/{proposta}`; quando aprovada: `POST /fianca/proposta/cotacao` (prêmio) |
| `biometria` | link em `biometria.pretendente.url` na consulta de status |
| `reanalisar` | `PUT /fianca/proposta/ficha/{n}/dados-basicos` + `POST .../solicitar-reanalise` (precisa de um motivo de `GET /fianca/credito/motivos-reanalise`) |
| `emitir` | `GET /fianca/proposta/formaspagamento/{cotacao}` → `PUT /fianca/proposta/ficha/{proposta}` (pagamento + pretendentes + proprietário) → `POST /fianca/proposta/{n}/transmitir` |
| `consultar_apolice` | `GET /fianca/apolice/{numero}` (ou status 10 na consulta da proposta) |
| `documento` | proposta `GET /fianca/proposta/{cpf}/{n}/pdf`; apólice `GET /fianca/apolice/{n}/pdf`; também endosso e boletos |

**Status da proposta → status interno:**

| Código Too | Significado | Status interno |
|---|---|---|
| 1, 2, 5 | criada / em análise | `em_analise` |
| 16 | pré-aprovada; biometria pendente | `aguardando_biometria` |
| 8 | aprovada | `aprovada` |
| 6, 15 | reprovada | `recusada` |
| 7 | pendenciada (documentos) | `em_analise` + pendência |
| 9 | aguardando emissão | apólice `em_emissao` |
| 10 | emitida | apólice `emitida` |
| 11 | expirada | `expirada` |
| 12 | cancelada | `cancelada` |

**Webhook (opcional):** a Too avisa quando a análise começa, é aprovada, é reprovada ou é cancelada, e quando a apólice é emitida. Só aceita URL HTTPS. Para se inscrever é preciso pedir no suporte. O cron de acompanhamento já cobre isso consultando o status.

## Mudanças no sistema

- **Adapter novo:** `src/helpers/api/seguradoras/too.php`, mais a entrada em `api_seguradoras()`, a logo e o `.env` (`TOO_API_URL`, `TOO_CLIENT_ID`, `TOO_CLIENT_SECRET`, `TOO_CNPJ_CORRETOR`, `TOO_RAZAO_CORRETOR`, `TOO_COMISSAO`, `TOO_PERIODO_INDENITARIO`).
- **Formulário da Fiança (API):** voltar a pedir do pretendente responsável a **data de nascimento, a renda mensal, o vínculo empregatício** (Clt, Autonomo, Empresario, FuncionarioPublico, Aposentado, RendaProvenienteAluguel, Estudante) **e a profissão** (até 40 caracteres).
- **Modal do proprietário:**
  - pessoa física: data de nascimento, estado civil, nacionalidade e renda;
  - pessoa jurídica: CNPJ, razão social e faturamento;
  - pessoa politicamente exposta;
  - **e-mail e telefone passam a ser obrigatórios**.
  - Do pretendente: pessoa politicamente exposta e CEP da residência.
- **Validações da Too:**
  - aluguel de no mínimo R$ 200;
  - cada encargo informado de no mínimo R$ 15;
  - aluguel mais encargos até R$ 24.999;
  - vigência de 4 a 60 meses;
  - de 1 a 4 pretendentes, só pessoa física;
  - danos, multa e pintura com valor igual ao aluguel ou zero.
- **Erros:** classificar no adapter (`too_classificar_erro`) usando os códigos internos de `api_erro_codigos()`.
- **Fora da API:** cancelar apólice não existe pela API, só por chamado no suporte da Too.
