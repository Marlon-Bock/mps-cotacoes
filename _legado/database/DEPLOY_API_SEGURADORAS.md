# Deploy — API das seguradoras (Fiança)

Passo a passo pra levar a API das seguradoras (Pottencial) pra produção.

## 1. `.env` de produção

```
POTTENCIAL_API_URL=https://api.pottencial.com.br
POTTENCIAL_CLIENT_ID=...          # credencial de PRODUÇÃO (a de homologação não funciona)
POTTENCIAL_CLIENT_SECRET=...
POTTENCIAL_CNPJ_BROKER=...
POTTENCIAL_CNPJ_ESTIPULANTE=...
POTTENCIAL_EMAIL_ESTIPULANTE=...

# Too Seguros (opcional — sem credencial fica desligada). Só PF residencial.
TOO_API_URL=https://openapi.tooseguros.com.br   # produção (homologação: openapi-uat)
TOO_CLIENT_ID=...
TOO_CLIENT_SECRET=...
TOO_CNPJ_CORRETOR=...
TOO_RAZAO_SOCIAL_CORRETOR=...
TOO_COMISSAO=0.20              # 0.10 a 0.40
TOO_PERIODO_INDENITARIO=30     # 12/15/18/24/30
```

## 2. Migrations

```
php database/migrate_all.php --prod --pretend   # confere: 085 a 088 pendentes
php database/migrate_all.php --prod             # aplica (faz backup antes)
```

- `085_api_seguradoras`: tabelas `api_cotacoes`, `api_cotacoes_tentativas` e `api_fila`, mais as colunas `origem`, `api_enviado_em` e `encaminhado_mps_*` em `seguro_fianca`.
- `086_api_vistos`: guarda quando cada usuário abriu a tela das seguradoras (contador do botão nas listagens).
- `087_api_erro_codigo_e_eventos`: `erro_codigo` (erro classificado pela seguradora) e `historico.api_evento`.
- `088_seguro_fianca_profissao`: coluna `profissao` (exigida pela Too).

## 3. Crons

Troque `/caminho/do/projeto` e o arquivo de log:

```
# Fila: cotação, reanálise, emissão e avisos (4 vagas em paralelo)
* * * * * cd /caminho/do/projeto && (for i in 1 2 3 4; do /usr/bin/php cron/api-fila.php & done; wait) >> /var/log/mps-api-fila.log 2>&1

# Acompanhamento: status das cotações/apólices na seguradora + PDFs
*/2 * * * * /usr/bin/php /caminho/do/projeto/cron/api-acompanhamento.php >> /var/log/mps-api-acompanhamento.log 2>&1
```

Sem o cron da fila, as cotações ficam paradas em "Carregando proposta".

## 4. Permissão de escrita

O usuário do PHP-FPM **e** o do cron precisam escrever em `storage/cache/`: token da seguradora, PDFs, locks da fila e estado do acompanhamento.

## 5. Conferir depois do deploy

1. Enviar uma ficha de Fiança: ela deve abrir a tela das seguradoras com "Carregando proposta" e o resultado aparecer em até 1 minuto.
2. Ver o log da fila: `Iniciando #N (cotacao, ...)` seguido de `Fim #N ... — ok`.
3. Na listagem, o botão das seguradoras aparece na ficha, com o contador de novidades.
