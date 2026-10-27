# Partials das telas de cotação (Blade)

Conversão dos partials compartilhados do dev-cotacoes (`_legado/pages/...`) para Blade.
O HTML gerado é **idêntico** ao antigo: cada partial foi renderizado lado a lado (antigo via
`include`, novo via `view()->render()`) com as mesmas variáveis e dados reais do db_seglaravel —
724 casos, todos iguais após normalizar espaços (também com o banco já em tipos nativos,
`ATTR_EMULATE_PREPARES=false`). As comparações (`===`, `(int)`, `(string)`) foram mantidas
exatamente como no antigo.

## Regras para quem for usar

- `@include` passa **todas** as variáveis do pai para o partial (como o `require` antigo), mas
  **o que o partial cria NÃO volta pro pai**. Nenhum dos partials abaixo exporta variável — a
  coluna "Define pro pai" está vazia de propósito. Onde o antigo dependia disso, foi resolvido
  dentro dos próprios partials (ver "Casos de variável que voltava pro pai").
- Onde o antigo fazia `$x = ...; require 'partial.php';`, passe a variável no próprio include:
  `@include('...', ['x' => ...])`.
- `$current_user` precisa estar no escopo da página (o controller já manda via
  `get_defined_vars()` quando faz `$current_user = require_auth();` / `auth_user()`).
- Os helpers (`cv`, `dfv`, `api_*`, `icon`, `custom_select`...) já estão carregados pelo Composer.
- Os partials que tinham `<script>` (JS das seguradoras, `window.__SEGURADORAS_CONFIG`) continuam
  com o `<script>` inline no mesmo lugar — é só incluir onde o antigo incluía.

## Tabela

| Partial antigo | `@include` | Variáveis esperadas | Define pro pai | Usado por (páginas antigas) |
|---|---|---|---|---|
| `pages/cotacao/_partials/detalhe-acoes.php` | `cotacao._partials.detalhe-acoes` | **Obrig.:** `$cotacao`, `$protocolo`, `$status`, `$preAnalisePendente`, `$podeCompletar`, `$urlCompletar`, `$timeline_steps`, `$current_user`. **Opcionais** (default aplicado dentro): `$tipo_key` ('fianca'), `$biometria_status` (null), `$ultima_proposta` (null), `$titulo_anexo_proposta` / `$titulo_anexo_boleto` / `$titulo_anexo_link_pagamento` / `$titulo_anexo_certificado` (null), `$api` (ausente = fluxo manual) | — | cotacao/fianca/detalhe, residencial/detalhe (com `$usou_pre_analise = false`), auto/detalhe, condominial/detalhe, titulo/detalhe |
| `pages/cotacao/_partials/encaminhamento-mps-aviso.php` | `cotacao._partials.encaminhamento-mps-aviso`, normalmente com `['is_admin_aviso' => true]` | `$cotacao`, `$protocolo`, `$is_admin_aviso` (bool). Não renderiza nada se `$cotacao['encaminhado_mps_em']` vazio | — | admin/cotacao/fianca/detalhe (antes: `$is_admin_aviso = true; require ...`) |
| `pages/cotacao/_partials/residencial-dados.php` | `cotacao._partials.residencial-dados` | `$cotacao` (linha de seguro_residencial) | — | cotacao/residencial/detalhe, admin/cotacao/residencial/detalhe |
| `pages/cotacao/_partials/seguradoras-conteudo.php` | `cotacao._partials.seguradoras-conteudo` | `$ficha` (seguro_fianca), `$protocolo`, `$is_admin` (true), `$current_user` (uid). Efeito colateral: `api_marcar_visto()` | — | admin/cotacao/fianca/seguradoras (miolo da página, dentro de `.cotacao-container`) |
| `pages/cotacao/_partials/documentos-incendio.php` | `cotacao._partials.documentos-incendio` | `$anexos` (cotacoes_anexos_incendio), `$imoveis` (seguro_incendio_imoveis) — ambos opcionais (viram `[]`) | — | cotacao/incendio/detalhe, admin/cotacao/incendio/detalhe |
| `pages/admin/cotacao/fianca/_api.php` | `cotacao._partials.admin-api` | `$cotacao` (seguro_fianca ou seguro_residencial), `$protocolo`. Ficha em 'Cotação API' ou 'Aprovado' | — | admin/cotacao/fianca/detalhe, admin/cotacao/residencial/detalhe |
| `pages/cotacao/_partials/seguradoras/aviso.php` | `cotacao._partials.seguradoras.aviso`, **sempre** com `['aviso' => [...]]` | `$aviso` = `['tipo' => 'erro'\|'alerta'\|'info'\|'sucesso', 'titulo' => ?, 'texto' => ?, 'extra' => ?HTML, 'acao' => ?HTML, 'dica' => ?]` | — | seguradoras/card, seguradoras/modal-corrigir |
| `pages/cotacao/_partials/seguradoras/biometria-lista.php` | `cotacao._partials.seguradoras.biometria-lista` | `$__biometrias` (api_biometrias()), `$__telefone` (só dígitos), `$__comSeguradora` (bool) | — | seguradoras/imobiliaria-status, admin-api |
| `pages/cotacao/_partials/seguradoras/card-carregando.php` | `cotacao._partials.seguradoras.card-carregando`, com `['seguradora' => api_seguradora($slug)]` | `$seguradora` | — | seguradoras-conteudo |
| `pages/cotacao/_partials/seguradoras/card.php` | `cotacao._partials.seguradoras.card` | `$c`, `$slug`, `$ficha`, `$fiancaId`, `$is_admin`, `$fichaEncerrada`, `$escolhida`, `$correcoes`, `$podeCorrigir`, `$jaHouveAprovada`, `$tentativas`, `$podeCotar`, `$etapa`, `$naPropostaAtual` (tudo calculado pelo seguradoras-conteudo) | — (ver `$jaHouveAprovada` abaixo) | seguradoras-conteudo |
| `pages/cotacao/_partials/seguradoras/imobiliaria-propostas.php` | `cotacao._partials.seguradoras.imobiliaria-propostas` — fiança: `['ficha' => $cotacao]`; residencial: `$ficha = $cotacao + ['produto' => 'residencial']` (a página já calcula) | `$ficha`, `$api` (`['lote', 'resumo']`), `$current_user` (imob_admin / perm_aceitar) | — | cotacao/fianca/detalhe (antes `if ($api) { $ficha = $cotacao; require ... }`), cotacao/residencial/detalhe (`if ($api)`) |
| `pages/cotacao/_partials/seguradoras/imobiliaria-status.php` | `cotacao._partials.seguradoras.imobiliaria-status`, com `['ficha' => $cotacao + ['produto' => $tipo_key]]` | `$ficha` (com `protocolo`; `produto` opcional), `$api` (`['lote', 'resumo']`) | — | detalhe-acoes |
| `pages/cotacao/_partials/seguradoras/logo.php` | `cotacao._partials.seguradoras.logo` | `$seguradora` (api_seguradora() ou fallback `['nome','sigla','cor','logo' => null]`) | — | card, card-carregando, imobiliaria-propostas |
| `pages/cotacao/_partials/seguradoras/modal-corrigir.php` | `cotacao._partials.seguradoras.modal-corrigir` | `$correcoes`, `$camposCorrigir`, `$ficha` | — | seguradoras-conteudo (se `$podeCorrigir`), imobiliaria-status (etapa 'corrigir') |
| `pages/cotacao/_partials/seguradoras/modal-pagamento-residencial.php` | `cotacao._partials.seguradoras.modal-pagamento-residencial` | `$ficha`, `$api['lote']['cotacoes']` | — | imobiliaria-propostas (produto residencial) |
| `pages/cotacao/_partials/seguradoras/modal-proprietario.php` | `cotacao._partials.seguradoras.modal-proprietario` | `$ficha` | — | imobiliaria-propostas (fiança) |
| `pages/partials/campanha-popup.php` | `partials.campanha-popup` | `$campanha_popup_tipo` ('residencial'\|'comercial'\|'auto'\|'titulo'\|'condominial'), `$current_user` (imobiliaria_id) | — | cotacao/fianca/residencial, cotacao/fianca/comercial, cotacao/auto/index, cotacao/condominial/index, cotacao/titulo/index |
| `pages/partials/embed-head.php` | `partials.embed-head` | `$page_title`, `$embed_token`, `$extra_css` (opcional), `$current_user` (stub do embed_resolver) | — | cotacao/embed/{fianca-residencial, fianca-comercial, auto, titulo, condominial} |
| `pages/partials/embed-foot.php` | `partials.embed-foot` | `$extra_js` (opcional) | — | as mesmas páginas de embed |

`embed-head` abre `<html>`, `<body>` e `<main class="app-content">`; `embed-foot` fecha. Página de embed:

```blade
@include('partials.embed-head')
    ... formulário ...
@include('partials.embed-foot')
```

`detalhe-helpers.php` **não** é partial: virou `app/Legado/detalhe_helpers.php` (cv, cv_money, dfv...).

## Casos de variável que voltava pro pai (e como ficou)

1. **`$jaHouveAprovada` (seguradoras-conteudo ↔ card)** — o card.php fazia
   `$jaHouveAprovada = true` e isso voltava pro loop do pai, para só o 1º card liberado ganhar
   "Melhor oferta". Agora o loop do `seguradoras-conteudo` atualiza a flag logo depois de cada
   `@include('...card')`, com a mesma regra (`api_status_liberado($status) || $status === 'proposta_escolhida'`).
   O card só lê a flag.
2. **`$ficha` (detalhe-acoes → imobiliaria-status)** — o antigo fazia
   `$ficha = $cotacao + ['produto' => $tipo_key]; require ...` dentro do detalhe-acoes, e `$ficha`
   vazava pro detalhe-acoes e pra página. Agora vai no include
   (`['ficha' => $cotacao + ['produto' => $tipo_key]]`). Nenhuma página lia `$ficha` depois disso
   (residencial/detalhe já define o seu `$ficha` antes).
3. **`$aviso` / `$seguradora` (card, modal-corrigir, seguradoras-conteudo, imobiliaria-propostas → aviso/logo/card-carregando)** —
   o antigo atribuía a variável e dava `require`. Agora `$aviso` vai explícito em cada
   `@include('...aviso', ['aviso' => ...])`; `$seguradora` é calculado no próprio partial antes do
   `@include('...logo')` (passa sozinho) ou explícito no `card-carregando`.
4. **`$correcoes` / `$camposCorrigir` / `$__biometrias` / `$__telefone` / `$__comSeguradora`** —
   calculados em `@php` no partial que inclui (imobiliaria-status, admin-api, seguradoras-conteudo)
   antes do `@include` do modal-corrigir / biometria-lista; chegam pelo escopo herdado.
5. **Defaults `??=` do detalhe-acoes** (`$tipo_key`, `$biometria_status`, `$ultima_proposta`,
   `$titulo_anexo_*`) e do documentos-incendio (`$anexos`, `$imoveis`, e o `$a` do foreach) — no
   antigo vazavam pro pai; agora não. Conferido: nenhuma página usa essas variáveis depois do
   partial sem tê-las definido antes (residencial/detalhe usa `$ultima_proposta` depois, mas já a
   define no topo).
6. **`return;` no encaminhamento-mps-aviso** — virou `@if (!empty($cotacao['encaminhado_mps_em'])) ... @endif`.

## Diferença conhecida

- `url()` agora é o do Laravel (absoluto, com `/seg-laravel`); no antigo era relativo
  (`BASE_URI . '/x'`). Mesmo destino — regra 10 do MIGRACAO-LARAVEL.md.
