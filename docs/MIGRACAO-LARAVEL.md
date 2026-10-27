# Migração dev-cotacoes (PHP puro) → seguradora (Laravel 12)

O código antigo está em `_legado/` (cópia fiel do dev-cotacoes — **só leitura, referência**).
O sistema novo é este projeto Laravel. Visual e regras de negócio **idênticos** ao antigo.

URL: https://devmarlon.samisales.com.br/seguradora — banco `db_seglaravel` (clone do db_mpsapis).
O antigo continua no ar em https://devmarlon.samisales.com.br/dev-cotacoes (banco db_mpsapis — dados REAIS; nunca grave nele).

## Arquitetura

- **Helpers do sistema antigo** em `app/Legado/*.php`, carregados pelo Composer (`autoload.files`).
  São as mesmas funções de antes (functions.php, api/*, seguradoras, PDFs, mailer, zapi...).
  **Não edite `app/Legado/`, `app/Legado/nucleo.php`, layouts, middlewares nem config** — se achar
  bug ou precisar de mudança neles, descreva no seu relatório final.
- **`app/Legado/nucleo.php`** reimplementa sobre o Laravel: `db_fetch_all/db_fetch_one/db_execute`
  (mesma assinatura `['tipos', ...valores]`; tipos nativos iguais ao mysqli antigo: INT → int,
  DECIMAL/datas → string), `json_response()`,
  `auth_user()`, `require_auth()`, `require_admin()`, `require_perm()`, `require_painel()`,
  `auth_logout()`, `request_path()`, `ir_para()`, `csrf_verify()` (no-op: o middleware valida).
- **Login**: guards de sessão `admin` (tabela `usuario`) e `imobiliaria` (tabela `users`).
  `auth_user()` devolve o MESMO objeto do antigo JWT (uid, nome, role, imob_admin, mps_admin,
  imobiliaria_id, perm_*). Não existe mais JWT/cookie mps_token.
- **Layout**: `resources/views/layouts/app.blade.php` (menu, topo, chat, scripts comuns).
  Variáveis que o layout lê (opcionais): `$page_title`, `$page_heading`, `$page_subheading`,
  `$page_back`, `$extra_css`, `$extra_js`, `$main_attrs` (atributos extras do `<main>`, string),
  `$current_user`. Stacks: `@push('head')`, `@push('antes-scripts')` (modais depois do shell),
  `@push('scripts')`.
- **Rotas**: um arquivo por módulo em `routes/modulos/NN-modulo.php` (carregados por routes/web.php).
  Mesmo caminho e método HTTP do antigo (`_legado/public/index.php`). `GET+POST` → `Route::match(['get','post'], ...)`.
- **Controllers**: `app/Http/Controllers/<Modulo>/XxxController.php` (namespace `App\Http\Controllers\<Modulo>`),
  um método por página/endpoint antigo. Views em `resources/views/<modulo>/...`.
- JS/CSS/ícones: `public/assets/` (idênticos ao antigo, NÃO mexer). O JS chama `/api/...` e o
  `base-path.js` acrescenta `/seguradora` sozinho.

## Exemplo pronto pra copiar o padrão

- `app/Http/Controllers/DashboardController.php` + `resources/views/dashboard/*.blade.php` (página com layout + endpoints JSON).
- `app/Http/Controllers/AuthController.php` + `resources/views/auth/*` (páginas sem layout).
- `routes/modulos/00-auth.php`, `routes/modulos/10-dashboard.php`.

## Ferramentas (`_ferramentas/`)

- `python3 _ferramentas/separar_pagina.py _legado/pages/x.php /tmp/saida`
  → `/tmp/saida.metodo.php` (lógica do topo pro método) e `/tmp/saida.blade.php` (view que estende o layout).
  Termina em `return view('VIEW', get_defined_vars());` — troque VIEW pelo nome da view.
  A view recebe TODAS as variáveis locais (inclusive closures como `$tem_perm`), igual ao `require` antigo.
- `python3 _ferramentas/php2blade.py arquivo.php` → converte um template/partial PHP em Blade (só sintaxe).
- `python3 _ferramentas/paridade.py imob|admin /caminho?query` → busca a MESMA rota GET no antigo e no
  novo com o mesmo usuário e mostra a diferença do conteúdo visível (ou do JSON). **Meta: "== conteúdo igual =="**
  (diferenças só de dado que mudou num dos bancos são aceitáveis). Só GET.
- Teste de POST/ações: só no novo, com curl. Login de teste: `python3 _ferramentas/paridade.py ...` já
  deixa sessões em `/tmp/paridade-seguradora/jar-imob.txt` e `jar-admin.txt` (cookie jar Netscape).
  Para POST use o header `X-CSRF-Token` com o valor da meta `csrf-token` de qualquer página logada e
  `Accept: application/json`.

## Regras de conversão (IMPORTANTES — são as armadilhas reais)

1. **Lógica da página → controller; template → Blade.** `<?= e(x) ?>` → `{{ x }}`; HTML pronto (`icon()`,
   `custom_select()`, `date_picker()`, `avatar_sm()`) → `{!! ... !!}`. Pode usar `@php ... @endphp` em
   trechos de template que já tinham lógica pequena; lógica grande vai pro controller.
2. **`redirect('/x');` como instrução** (o antigo dava exit) → no controller `return redirect(url('/x'));`;
   dentro de helper/closure ou fundo de `if` → `ir_para('/x');` (lança e encerra).
   Nunca deixe `redirect(...)` solto sem return — o do Laravel não encerra.
3. **`json_response()`** continua valendo (lança a resposta). MAS: se estiver dentro de `try { }` com
   `catch (Throwable|Exception $e)`, o catch engoliria — acrescente antes do catch genérico:
   `catch (\Illuminate\Http\Exceptions\HttpResponseException $e) { throw $e; }`.
   O mesmo vale pra `ir_para()`, `require_*()` e `api_ficha_autorizada()` dentro de try.
4. **`$_SESSION['x']`** → `session('x')` / `session(['x' => v])` / `session()->pull('x')` / `session()->forget('x')`.
5. **`$params['x']`** (parâmetro de rota) → parâmetro do método (`public function detalhe(string $protocolo)`)
   e, se o código usa `$params`, crie `$params = ['protocolo' => $protocolo];` no início.
6. **`$_POST`/`$_GET`/`$_FILES`/`php://input`** continuam funcionando, mas prefira `$request->input()`,
   `$request->query()`, `$request->file()`. JSON do corpo: `$request->input('campo')` lê JSON também.
7. **Remova**: `header('Content-Type: ...')`, `csrf_verify();` (pode deixar — é no-op), `require_once BASE_PATH . '/src/...'`,
   `exit`. `http_response_code(N); json_response(x)` → `json_response(x, N)`.
8. **`const NOME = ...;` solto no arquivo** não vale dentro de método → variável local ou `const` da classe.
9. **Funções declaradas dentro do arquivo da página** (`function x() {}`) → método privado do controller
   ou closure; se forem usadas por várias páginas, coloque num helper do módulo
   `app/Legado/modulos/<modulo>.php` E me avise (precisa entrar no composer.json — não edite o composer.json;
   alternativa: `private static` no controller).
10. **`APP_URL . url('/x')`** → só `url('/x')` (o `url()` do Laravel já é absoluto e já tem o /seguradora).
    `BASE_URI . '/x'` em view → `url('/x')`.
11. **Partials** `require __DIR__ . '/_partials/x.php'` → `@include('modulo._partials.x')`. O `@include` passa
    as variáveis do pai, mas **variáveis criadas DENTRO do partial não voltam pro pai** (o require voltava) —
    se o pai usa algo que o partial define, calcule no controller ou no próprio pai.
12. **`db()` direto (API mysqli: `$conn->prepare/bind_param/insert_id/begin_transaction`)** → reescreva com
    `db_fetch_*`/`db_execute` ou `DB::` do Laravel; transação → `DB::transaction(fn () => ...)`;
    `db()->insert_id` → use o retorno de `db_execute()` do INSERT (já é o id).
13. **Upload de arquivo**: `media_upload($_FILES['arquivo'], ...)` funciona igual.
14. **Página sem layout** (login, embed, painel TV, PDFs): view com o HTML completo, como `auth/login.blade.php`.
    Download (PDF/CSV/XLSX): `return response($conteudo, 200, [headers])` ou `response()->streamDownload(...)`
    — nunca `echo` + `exit`.
15. **Mesma URL, mesmo método, mesmo JSON de resposta** (o JS antigo depende das chaves exatas).
16. Comentários: mantenha os comentários úteis do antigo (o "porquê"); em português.

## Middlewares de rota

- `->middleware('mps.auth')` = require_auth · `'mps.admin'` = require_admin · `'mps.perm:perm_cotar,perm_fianca'` = require_perm.
  Como o código antigo já chama `require_*()` no início, pode só manter a chamada no método
  (`$current_user = require_admin();`) — os dois jeitos funcionam; não precisa dos dois.
- CSRF: automático em POST (exceto `api/webhook/*`, `api/embed/*`, `api/auth/login`).

## Checklist por arquivo antigo

- [ ] rota em `routes/modulos/` com o mesmo caminho/método
- [ ] método no controller, sem `$_SESSION`, sem `redirect(` solto, try/catch tratado, sem `exit`/`header()`
- [ ] view Blade sem `<?`, includes resolvidos, `{{ }}` vs `{!! !!}` corretos
- [ ] `php -l` em tudo; `php artisan route:list --path=...` mostra a rota
- [ ] GET: `paridade.py` → conteúdo igual (ou diferença explicada)
- [ ] POST/ações: testado no novo (curl) — e o efeito no banco db_seglaravel conferido
- [ ] `sistema_erros` sem erro novo do seu módulo:
      `php artisan tinker --execute="dump(db_fetch_all('SELECT id,mensagem,arquivo,linha FROM sistema_erros ORDER BY id DESC LIMIT 10'));"`

## Peças globais adicionadas no fim da migração

- **`CabecalhosSeguranca`** (middleware web): X-Frame-Options, nosniff, Referrer-Policy, CSP (`csp_header()`)
  e HSTS — como o bloco de headers do `src/bootstrap.php` antigo. Página de embed chama
  `embed_apply_frame_headers($dominio)`, que só marca o request; o middleware libera o domínio no
  `frame-ancestors` e tira o X-Frame-Options.
- **`PainelPersistente`** (middleware web): a conta do painel de TV fica logada 30 dias (antigo
  `JWT_TTL_PAINEL`) via cookie criptografado `mps_painel`; quando a sessão de 3h expira, reloga sozinha.
- **CSRF inválido** → JSON 403 `session_expirada` (o front já trata). Atenção: no Laravel 12 a
  `TokenMismatchException` chega aos callbacks de render já convertida em `HttpException` 419 — o
  callback testa `getPrevious()`.
- Exceção de CSRF só para `api/embed/*/cotacao/*` (o `/api/embed/ativar` é logado e valida CSRF).
- Banco `db_seglaravel` re-clonado do db_mpsapis com `--default-character-set=utf8mb4` em 22/10/2026
  (o primeiro clone tinha perdido emojis/caracteres de 4 bytes).
- Depois de editar `bootstrap/app.php`, o php-fpm pode levar alguns segundos (opcache) para pegar a mudança.

## Segurança (correções de 23/10/2026)

- **`require_mps_admin()`** (nucleo.php): usuário da MPS com `admin = 'S'`. Usado em gestão da equipe MPS
  (UsuariosMpsController) e financeiro. **`require_mps_admin_ou_comercial()`**: comissões (tela /admin/comissoes
  e salvar comissão de visita — ler continua liberado pro relatório); `auth_user()->equipe_comercial`.
  Itens de menu com `'so_mps_admin' => true` somem para quem não é admin (navegacao.php).
- **Painel de TV** (`/painel`, `/api/painel/dados`): público, só leitura (decisão do usuário). Mostra os 6 produtos,
  o status de cada cotação recente e avisa também o resultado da API (aprovada / recusada por todas as seguradoras).
- **Login**: além de 5 falhas/10 min por e-mail, 20 falhas/10 min por IP.
- **IP do visitante**: `trustProxies` só para a rede do Cloudflare (bootstrap/app.php). Use `request()->ip()`
  ou `cliente_ip()` — nunca leia `CF-Connecting-IP`/`X-Forwarded-For` direto (dá pra falsificar).
- **Recuperar senha**: resposta sempre igual (não revela se o e-mail existe); um e-mail com um link por conta.
- **E-mail de usuário de imobiliária**: pode repetir dentro da MESMA imobiliária, nunca em outra —
  `email_usado_em_outra_imobiliaria()` (app/Legado/modulos/cadastros.php). Equipe MPS: e-mail único.
- **URL informada pelo usuário** (embed, ERP SAMILOCA): `http_get_url_publica()` segue redirecionamentos
  conferindo cada destino (sem SSRF); nunca use `CURLOPT_FOLLOWLOCATION` com URL de formulário.
- **Embed**: envios limitados (`throttle:embed-envio`: 20/h por IP, 100/h por imobiliária).
- **`mpsAlert.confirm`** escapa a mensagem; passe `html: true` só para HTML montado pelo próprio código.
- **Flags** (independentes do `APP_ENV`): embed sempre ligado; Z-API liga sozinha quando `ZAPI_INSTANCE_ID` e
  `ZAPI_TOKEN` estão no `.env`. Dá pra forçar com `FEATURE_EMBED_UI` / `FEATURE_ZAPI_NOTIFICACOES` = 1|0.
- Ao rodar `artisan view:cache` como marlon, os arquivos precisam ficar legíveis pelo www-data (não use umask 077).

## Canais de cotação: Manual, API das seguradoras e Segimob (23/10/2026)

Cada imobiliária escolhe, por produto, por onde a cotação vai (cadastro da imobiliária → "Canais de cotação").
Por enquanto só o Seguro Fiança tem canal; sem configuração = **Manual**.

| Canal | O que acontece com a ficha | Módulo |
|---|---|---|
| `manual` | vai direto para a fila da MPS ('Aguardando análise'), sem integração | — |
| `api` | Pottencial / Too (e as próximas) — 'Cotação API', worker `api:fila` | `app/Legado/api/` |
| `segimob` | agregador Segimob — 'Cotação API', comando `segimob:acompanhamento` | `app/Legado/segimob/` |

- **Por enquanto só Manual e Segimob**: o canal "API das seguradoras" só aparece no cadastro com
  `FEATURE_CANAL_API=1` no `.env` (desligado por padrão). Imobiliária nova e sem configuração = Manual.
- **Quem decide**: só `app/Legado/canais.php` (`canal_da_imobiliaria()`, tabela `imobiliaria_canais`).
  O envio grava o canal em `seguro_fianca.origem` e, daí em diante, cada tela/ação usa o módulo da
  origem da ficha (nunca o canal atual da imobiliária — trocar o canal não mexe nas fichas já enviadas).
- **Para liberar canal em outro produto**: incluir o produto em `canais_por_produto()` e fazer o envio
  daquele produto consultar `canal_da_imobiliaria()`.
- **Para uma nova seguradora direta**: entra no módulo `api` (não é canal novo).
- **Segimob precisa**: `SEGIMOB_API_URL`, `SEGIMOB_EMAIL`, `SEGIMOB_SENHA`, `SEGIMOB_CODIGO_CORRETORA` no `.env`
  e o código da imobiliária na Segimob (estipulante, `imobiliarias.segimob_estipulante`). Sem credenciais,
  a ficha de imobiliária Segimob cai no Manual. API sem seguradora que atenda o pretendente também cai no Manual.
- **Pontos com despacho por canal**: formulário (`fianca_partial_formulario()` — Segimob tem o seu),
  `FiancaController::enviar/recotar`, detalhe da imobiliária e da MPS, tela Seguradoras
  (`Segimob\SeguradorasController`, também `/seguradoras/{slug}`), desistência, listagens
  (MPS e imobiliária), dashboards, Central de Gestão, painel de TV e situação do embed
  (`canal_status_exibicao()`, `canal_automatico()`, `segimob_etapas_ativas()`).
- **Segimob — só proposta em PDF** (decisão de 23–26/10/2026): a API da Segimob só diz se cada seguradora
  recusou ou pré-aprovou (lista no painel "Análise nas seguradoras" durante a análise). Concluída, o botão
  **"Enviar cotação"** do painel abre as seguradoras da cotação (só os nomes — `segimob_seguradoras_pdf()`),
  cada uma com "Enviar ao cliente" e "Anexar PDF" (inclusive a que recusou pela API). Os PDFs ficam em
  `segimob_propostas_pdf` até o envio (`segimob/proposta_pdf.php`). O cliente vê só as seguradoras
  ("Ver proposta" / "Escolher"), a etapa vira `escolhida` e a MPS contrata no portal e anexa a apólice
  (conclui). As seguradoras vêm da configuração da imobiliária na Segimob (ex.: Pottencial só se habilitada).
  **Biometria** (`segimob/biometria.php`, tabela `segimob_biometrias`): toda seguradora tem "Adicionar
  link" (a com `pendente_biometria` mostra "Pede biometria"). O link fica guardado (`liberado_em` NULL) e
  a seguradora já vem marcada em "Enviar ao cliente": cada seguradora marcada vai com o que tem pronto (o
  link da biometria ou o PDF), tudo num envio, numa linha de histórico e num aviso só. A imobiliária vê "Biometria das seguradoras" (uma por seguradora, igual às propostas) com
  "Copiar link" pra mandar ao pretendente e "Concluída". Concluída quando a Segimob tira a pendência ou
  quando a MPS ou a imobiliária marca (a da imobiliária avisa a MPS) — só então o PDF daquela seguradora
  é liberado.
  Fichas Segimob não têm mais botão "Seguradoras" (detalhe e listagem). A contratação pela API
  (`segimob/contratacao.php`, rotas selecionar/contratar/apolice/enviar) ficou no código, sem uso nas telas.
  Homologação: `php artisan segimob:mock <protocolo> porto=aprovar tokio=recusar too=aprovar`
  (atalho `php bin/segimob-mock.php`).
- **Financeiro** (`/admin/financeiro`): soma os dois canais automáticos. Cada módulo entrega as linhas
  no formato comum (`api_financeiro_linhas`, `segimob_financeiro_linhas`); `app/Legado/financeiro.php`
  junta, filtra (canal, seguradora `canal:chave`, situação…) e totaliza. Novo canal com contratação =
  uma função `*_financeiro_linhas` no mesmo formato + entrada em `financeiro_linhas()`.
- **Filtro "Canal"** em Todas as Cotações (só admin da MPS — `mps_admin`) e na Central de Gestão:
  `canais_filtro()`, `canal_filtro_valido()`, `admin_cotacoes_filtrar_canal()`. O dashboard da MPS mostra
  sempre todos os canais. Produto sem canal configurável conta como Manual.
- **A imobiliária não vê o canal**: nenhuma tela dela cita API, Segimob ou manual; eventos automáticos do
  histórico saem com o autor "Seguradoras" (`API_HISTORICO_AUTOR` / `SEGIMOB_HISTORICO_AUTOR`).

## Auditoria (26/10/2026)

Tudo o que acontece fica registrado pra responder "o que foi, como foi e quem foi". Tela **/admin/auditoria**
(menu Configurações → Auditoria), só pro suporte da Sami — os mesmos e-mails da senha do dia
(`require_suporte_sami()`; os outros recebem 404).

- **Requisições** (`auditoria_requisicoes`, middleware `RegistrarAuditoria`): quem (MPS, imobiliária, embed,
  painel, sem login), se entrou com a senha do dia, IP, navegador, URL, `Controller@método`, dados enviados
  (senha/token/cartão mascarados), status e duração. Entra toda escrita, tela aberta e download; consulta JSON
  sem alteração (polling) não entra.
- **Alterações** (`auditoria_alteracoes`): todo INSERT/UPDATE/DELETE que mudou o banco, com o antes e o depois
  por campo (pela chave primária; upsert pela chave única). Capturado em `db_execute()`/`db_query()`
  (`app/Legado/auditoria.php`) — vale pra todas as telas, APIs e comandos, sem mexer em cada controller.
- **Cron/comandos**: um registro por execução, só quando mudou dado. Mudança só em datas de controle
  (`atualizado_em`, `respondido_em`...) não entra.
- Não apaga nada sozinho (cresce com o uso) — se precisar, criar uma limpeza por data.

## Login: Turnstile + dois fatores (26/10/2026)

- **Turnstile** (Cloudflare) em todo login, antes de conferir a senha (`TURNSTILE_SITE_KEY`/`TURNSTILE_SECRET_KEY`
  no `.env`; sem a chave, o login não pede). O token vale uma vez: a tela gera outro depois de erro; a escolha
  de perfil (conta MPS + imobiliária) reaproveita a verificação por 2 minutos.
- **Dois fatores em todo login** (`app/Legado/dois_fatores.php`, `Auth\DoisFatoresController`):
  MPS e imobiliária escolhem Google Authenticator (TOTP, QR code) ou e-mail. No primeiro login a pessoa
  configura (no e-mail, informa o e-mail dos códigos, mesmo que já tenha um no cadastro). A verificação é por
  login: mesmo e-mail com dois logins = duas configurações; o e-mail do código ("Olá, Fulano… conta Fulano
  (Imobiliária X)") e o nome no Authenticator ("Fulano (Imobiliária X)") dizem de qual login é (`df_conta_rotulo`). Código por e-mail:
  6 dígitos, vale 10 min, 5 tentativas, reenvio a cada 60 s (máx. 10/hora), guardado só como hash. TOTP: segredo
  criptografado com a APP_KEY, não aceita o mesmo código duas vezes. Código errado conta como falha de login.
- **Suporte da Sami** (e-mails de `senha_do_dia_emails_autorizados()`): só Google Authenticator, e um só por
  pessoa — o da conta MPS vale também na conta de imobiliária dela (`df_conta_config`).
- **Senha do dia**: pede o código do Google Authenticator de alguém do suporte (`df_verificar_totp_suporte`); o
  aviso, o log e a auditoria dizem quem liberou ("senha do dia: Fulano").
- **Fora dos dois fatores**: só a conta do painel de TV.
- **Redefinir**: a própria pessoa em Meu Perfil ("Trocar método ou e-mail") ou o admin da MPS na edição do
  usuário (MPS ou imobiliária) — configura de novo no próximo login.
- **Depende do e-mail**: quem escolheu e-mail recebe o código pelo SMTP (`send_email`, `SMTP_*` do `.env`); se o
  provedor cair, essas contas não entram (quem usa o Authenticator não depende disso).
- Biblioteca nova: `bacon/bacon-qr-code` (QR code) — rodar `composer install` na produção.

## Mudanças de banco pendentes para produção

O seguradora ainda não tem um mecanismo próprio de migração do banco existente (as migrations padrão do
Laravel em `database/migrations` NÃO devem ser rodadas — a primeira tenta criar a tabela `users`, que já existe).
Até lá, cada mudança fica em `database/sql/NNN_*.sql` e precisa ser aplicada uma vez no banco de produção:

- `095_horarios_incendio_residencial.sql` — horário de atendimento e bloqueios para Incêndio e Residencial
  (aplicado no db_seglaravel em 23/10/2026).
- `096_segimob_canais.sql` — canais de cotação por imobiliária (`imobiliaria_canais`), estipulante da
  Segimob, `seguro_fianca.tipo_imovel` e as tabelas `segimob_*`. Depois de aplicar, preencher
  `segimob_dominios` (listas do formulário Segimob): copiar do db_seglaravel ou rodar
  `php artisan tinker --execute="segimob_dominio_sincronizar_todos();"` (aplicado no db_seglaravel em 23/10/2026).
- `097_historico_autor_seguradoras.sql` — autor dos eventos automáticos do histórico passa de
  "API das seguradoras" para "Seguradoras" (a imobiliária não deve saber o canal da ficha)
  (aplicado no db_seglaravel em 23/10/2026).
- `098_seguro_incendio_rascunho.sql` — rascunho do Seguro Incêndio (trazido do cotacoes/, commit 09f074d)
  (aplicado no db_seglaravel em 23/10/2026).
- `099_segimob_proposta_pdf_comissao.sql` — propostas da Segimob em PDF (modo, PDF da proposta por
  seguradora e da apólice) (aplicado no db_seglaravel em 23/10/2026).
- `100_segimob_biometrias.sql` — biometria do pretendente por seguradora na Segimob (aplicado no
  db_seglaravel em 26/10/2026).
- `101_auditoria.sql` — tabelas da auditoria (aplicado no db_seglaravel em 26/10/2026).
- `102_dois_fatores.sql` — dois fatores no login (aplicado no db_seglaravel em 26/10/2026).
- `103_segimob_biometria_liberada.sql` — link da biometria só vai pro cliente no "Enviar ao cliente"
  (aplicado no db_seglaravel em 26/10/2026).
