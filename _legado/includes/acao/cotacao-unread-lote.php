<?php
/**
 * GET /api/cotacao/unread-lote?protocolos=A,B,C
 * Retorna a contagem de não lidas (mensagens + eventos de status) de vários
 * protocolos de uma vez — usado pelo JS pra "revalidar" os badges de chat já
 * renderizados numa página (grid ou detalhe) sempre que ela volta a ficar
 * visível, garantindo que ler o chat em um lugar também limpe o badge em
 * qualquer outro lugar que mostre a mesma cotação, sem precisar recarregar.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');

// Polling (cotacao-chat.js revalida sempre que a página volta a ficar
// visível) — só lê, não escreve na sessão depois daqui. Fecha a sessão logo
// cedo pra liberar o lock do arquivo (mesma sessão em todas as abas/requisições
// do navegador — sem isso, uma chamada lenta enfileira todas as outras).
session_write_close();

$protocolos = array_filter(array_map('trim', explode(',', (string) ($_GET['protocolos'] ?? ''))));
$protocolos = array_slice(array_values(array_unique($protocolos)), 0, 100);

$papel = ($user->role ?? '') === 'admin' ? 'admin' : 'imobiliaria';
$uid   = (int) ($user->uid ?? 0);

// Imobiliária só pode consultar as próprias cotações (operador comum) ou as
// da imobiliária inteira (admin da imobiliária) — admin MPS vê qualquer uma.
if ($papel === 'imobiliaria') {
    $protocolos = array_values(array_filter($protocolos, function ($p) use ($user) {
        $r = cotacao_resolver($p);
        return $r && cotacao_pode_acessar($r, $user);
    }));
}

$mapa = $protocolos ? cotacao_unread_map($protocolos, $papel, $uid) : [];

json_response(['success' => true, 'unread' => $mapa]);
