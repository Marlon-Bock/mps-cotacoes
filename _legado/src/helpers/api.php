<?php
/**
 * API das seguradoras (Fiança) — ponto único de entrada.
 *
 * A ficha de Fiança é cotada direto na API das seguradoras integradas
 * (Pottencial, Too). Fluxo (MPS no meio — igual ao dev-cotacoes-3):
 *
 *   ficha enviada ─► fila 'cotacao' ─► seguradoras ─► api_cotacoes
 *        │      imobiliária vê só a pré-análise (Em análise / Pré-aprovado / Recusado)
 *        │      recusada em todas ─► 'Recusado'
 *        │      análise concluída ─► MPS "Fazer cotação" ─► propostas pro cliente
 *        │      cliente aceita (proprietário) ─► fila 'emissao' ─► 'Aprovado'
 *        │      apólice emitida ─► MPS "Enviar apólice" ─► 'Concluída'
 *        └─ status da ficha: 'Cotação API' até o aceite (etapa real: api_etapa())
 *
 * Módulos (src/helpers/api/):
 *   seguradoras.php    registro das seguradoras + contrato dos adapters
 *   seguradoras/*.php  um adapter por seguradora
 *   status.php         status interno, textos de tela, resumo das listagens
 *   cotacoes.php       cotar, reconsultar, reanalisar, gravar resultados
 *   emissao.php        emissão da apólice, PDFs, biometria
 *   fila.php           fila de trabalhos (worker cron/api-fila.php)
 *   encaminhamento.php entrada da ficha na fila da MPS
 *   notificacoes.php   avisos no sininho
 *   acesso.php         permissão nos endpoints/telas
 *   historico.php      etapas na linha do tempo da cotação (chat)
 *   propostas.php      etapas do fluxo, propostas da MPS pro cliente, aceite, envio da apólice
 *   financeiro.php     página Financeiro (/admin/financeiro) — carregado só por ela
 */

require_once __DIR__ . '/api/seguradoras.php';
require_once __DIR__ . '/api/status.php';
require_once __DIR__ . '/api/cotacoes.php';
require_once __DIR__ . '/api/emissao.php';
require_once __DIR__ . '/api/fila.php';
require_once __DIR__ . '/api/encaminhamento.php';
require_once __DIR__ . '/api/notificacoes.php';
require_once __DIR__ . '/api/acesso.php';
require_once __DIR__ . '/api/historico.php';
require_once __DIR__ . '/api/propostas.php';
