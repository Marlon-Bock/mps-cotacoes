<?php
/**
 * Tela de seguradoras da Fiança (API das seguradoras) — SÓ A MPS
 * (pages/admin/cotacao/fianca/seguradoras.php). A imobiliária vê só a
 * pré-análise e as propostas, no detalhe da cotação.
 *
 * Espera no escopo: $ficha (linha de seguro_fianca), $protocolo,
 * $is_admin (bool, sempre true aqui).
 *
 * Fluxo: cotação (fila) → resultado por seguradora (aprovada / biometria /
 * recusada) → com a análise concluída, "Fazer cotação": a MPS marca quais
 * vão pro cliente e envia as propostas. Quem aceita (e emite) é o cliente.
 *
 * Partes em seguradoras/: card.php (resultado de uma seguradora),
 * card-carregando.php, logo.php, modal-proprietario.php, modal-corrigir.php.
 */

require_once BASE_PATH . '/src/helpers/api.php';

$fiancaId   = (int) $ficha['id'];

// Abriu a tela: zera o contador de novidades do botão na listagem.
api_marcar_visto($fiancaId, $is_admin ? 'admin' : 'imobiliaria', (int) ($current_user->uid ?? 0));
$cotacoes   = api_cotacoes_da_ficha($fiancaId);
$tentativas = api_tentativas_da_ficha($fiancaId);

// Cotação/emissão/reanálise rodam no worker (CLI); enquanto houver trabalho
// ativo o JS consulta a fila e recarrega quando terminar.
$filaAtiva       = api_fila_ativa($fiancaId);
$ultimaCotacao   = api_fila_ultimo($fiancaId, 'cotacao');
// Cada seguradora cota num trabalho próprio, em paralelo: as que ainda não
// responderam aparecem como card "Carregando proposta" ao lado das prontas.
// Seguradora sendo recotada depois de um erro (ex: "Corrigir dados") também
// volta pro card de carregando — o erro antigo não vale mais.
$comResposta     = array_column(array_filter($cotacoes, fn($c) => !($c['status'] === 'erro' && empty($c['cotacao_externa_id']))), 'seguradora');
$cotando         = array_values(array_filter(
    array_keys(api_seguradoras()),
    fn($s) => !in_array($s, $comResposta, true) && api_fila_ativa($fiancaId, 'cotacao', $s) !== null
));
$cotacaoFalhou   = $cotacoes === [] && $cotando === [] && ($ultimaCotacao['status'] ?? '') === 'erro';
// Escolha única: o cliente fecha com UMA seguradora.
$escolhida       = api_seguradora_escolhida($fiancaId);
// Desistência / recusada pela MPS / concluída: só leitura, sem ações.
$fichaEncerrada  = api_ficha_encerrada($ficha);

// Etapa do fluxo: com a análise concluída (ou o cliente recusou / quer
// trocar), a MPS monta e envia as propostas. Desistência fica no detalhe.
$loteApi         = api_status_lote([$fiancaId])[$fiancaId];
$etapa           = api_etapa($loteApi['cotacoes'], $loteApi['fila'], $loteApi['propostas']);
$podeCotar       = !$fichaEncerrada && $ficha['status'] === API_STATUS_FICHA && in_array($etapa, [...API_ETAPAS_COTAR, 'proposta'], true);
$naPropostaAtual = array_column(array_filter($loteApi['propostas'], fn($p) => $p['status'] === 'Aguardando'), 'seguradora');
$instrucao = match (true) {
    $etapa === 'analise'             => 'Análise em andamento — o envio das propostas é liberado quando todas as seguradoras responderem.',
    $etapa === 'biometria'           => 'A seguradora pede a biometria facial do pretendente — o envio das propostas é liberado quando ele concluir.',
    $etapa === 'proposta'            => 'Propostas com o cliente. Para trocar, marque outras seguradoras e reenvie — o envio anterior é substituído.',
    $etapa === 'propostas_recusadas' => 'O cliente recusou as propostas anteriores. Envie outras ou encerre a cotação no detalhe.',
    $podeCotar                       => 'Marque as seguradoras que vão para o cliente — ele vê os valores e se pedem biometria.',
    in_array($etapa, ['contratada', 'emitida'], true) => 'O cliente aceitou a proposta. Envie a apólice pelo detalhe da cotação quando ela sair.',
    default                          => null,
};

// "Corrigir dados": a seguradora recusou um dado conhecido da ficha — a
// imobiliária corrige só esses campos e a cotação é reenviada.
$correcoes      = api_correcoes_pendentes($ficha, $cotacoes);
$podeCorrigir   = $correcoes !== [] && $ficha['status'] === API_STATUS_FICHA && !$fichaEncerrada && $filaAtiva === null;
$camposCorrigir = $correcoes !== [] ? array_values(array_unique(array_merge(...array_column($correcoes, 'campos')))) : [];

$jaHouveAprovada = false;
?>
<?php if (!empty($ficha['encaminhado_mps_em'])): ?>
<div class="seg-encaminhada">
    <?= icon('paper-plane-tilt') ?>
    <div>
        <span class="seg-encaminhada-titulo">Encaminhada para a MPS em <?= e(date('d/m/Y \à\s H:i', strtotime($ficha['encaminhado_mps_em']))) ?></span>
        <span class="seg-encaminhada-texto">
            <?= e((string) $ficha['encaminhado_mps_detalhe']) ?>.
            <?= $ficha['encaminhado_mps_motivo'] === 'recusada_api'
                ? 'A MPS vai cotar em outras seguradoras e responder pela cotação.'
                : 'A MPS vai cotar em outras seguradoras; a proposta abaixo continua disponível.' ?>
        </span>
    </div>
</div>
<?php endif; ?>

<div class="seg-toolbar<?= $instrucao ? ' seg-toolbar--instrucao' : '' ?>">
    <?php if ($instrucao): ?>
    <p class="seg-toolbar-texto"><?= icon($etapa === 'analise' ? 'clock' : 'info') ?> <?= e($instrucao) ?></p>
    <?php endif; ?>
    <button type="button" class="btn btn-secondary btn-sm" id="btn-atualizar-seguradoras">
        <?= icon('arrows-clockwise') ?> Atualizar
    </button>
</div>

<?php if ($cotacaoFalhou): ?>
<div class="alert alert-danger">
    <?= icon('warning') ?> Não foi possível enviar a cotação para a seguradora: <?= e((string) $ultimaCotacao['erro']) ?> Use "Atualizar" para tentar de novo.
</div>
<?php elseif ($cotacoes === [] && $cotando === []): ?>
<div class="alert alert-info">
    <?= icon('info') ?> Essa cotação ainda não foi enviada para as seguradoras.
</div>
<?php else: ?>
<div class="seg-grid" id="lista-seguradoras">
    <?php foreach ($cotando as $slugCotando):
        $seguradora = api_seguradora($slugCotando);
        require __DIR__ . '/seguradoras/card-carregando.php';
    endforeach; ?>

    <?php foreach ($cotacoes as $c):
        $slug = (string) $c['seguradora'];
        if (in_array($slug, $cotando, true)) continue; // recotando: card de carregando acima
        require __DIR__ . '/seguradoras/card.php';
    endforeach; ?>
</div>

    <?php if ($podeCotar): ?>
    <!-- Barra de envio: fica presa no rodapé enquanto rola pelos cards. -->
    <div class="seg-cotar-barra">
        <div class="seg-cotar-resumo">
            <span class="seg-cotar-contador" id="propostas-selecionadas"></span>
            <span class="seg-cotar-resumo-sub">para enviar ao cliente</span>
        </div>
        <div class="field-input-wrap seg-cotar-obs">
            <span class="field-input-icon"><?= icon('note-pencil') ?></span>
            <input type="text" class="field-input has-icon" id="propostas-observacao" maxlength="300" placeholder="Observação para o cliente (opcional)">
        </div>
        <button type="button" class="btn btn-primary" id="btn-enviar-propostas"><?= icon('paper-plane-tilt') ?> <?= $etapa === 'proposta' ? 'Reenviar propostas' : 'Enviar propostas' ?></button>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if ($podeCorrigir): require __DIR__ . '/seguradoras/modal-corrigir.php'; endif; ?>

<script src="<?= e(asset_v('/assets/js/masks.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/cotacao-fianca-seguradoras.js')) ?>"></script>
<script>
window.__SEGURADORAS_CONFIG = {
    protocolo: <?= json_encode($protocolo) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    aguardando: <?= json_encode(api_fila_aguardando($fiancaId)) ?>,
};
</script>
