<?php
$current_user = require_auth();
if (($current_user->role ?? '') === 'admin') redirect('/admin/cotacoes');

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/cotacoes');

if (str_starts_with($protocolo, 'AUTO'))   redirect('/cotacao/auto/'        . urlencode($protocolo));
if (str_starts_with($protocolo, 'TC'))     redirect('/cotacao/titulo/'      . urlencode($protocolo));
if (str_starts_with($protocolo, 'COND'))   redirect('/cotacao/condominial/' . urlencode($protocolo));
if (str_starts_with($protocolo, 'INC'))    redirect('/cotacao/incendio/'    . urlencode($protocolo));
if (str_starts_with($protocolo, 'RES'))    redirect('/cotacao/residencial/' . urlencode($protocolo));
redirect('/cotacao/fianca/' . urlencode($protocolo));
