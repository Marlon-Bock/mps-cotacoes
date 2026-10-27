<?php
/**
 * GET /admin/log
 * Log de notificações WhatsApp (Z-API) — quem recebeu, qual mensagem, se deu
 * certo. Acesso só por link direto: não tem item no menu (ver
 * pages/partials/nav-admin.php), mas a segurança de verdade é o require_admin()
 * abaixo — sem sessão de admin válida, cai em 403 igual qualquer outra tela.
 */
$current_user = require_admin();

$logs = db_fetch_all(
    "SELECT z.id, z.evento, z.protocolo, z.telefone, z.mensagem, z.sucesso, z.erro, z.criado_em,
            u.username AS atendente_nome
       FROM zapi_notificacoes z
       LEFT JOIN usuario u ON u.id = z.atendente_id
      ORDER BY z.id DESC
      LIMIT 300"
);

$eventos_label = [
    'nova_cotacao'        => 'Nova cotação',
    'biometria_concluida' => 'Biometria concluída',
    'chat_mensagem'       => 'Mensagem no chat',
    'proposta_aceita'     => 'Proposta aceita',
    'proposta_recusada'   => 'Proposta recusada',
    'desistencia'         => 'Desistência',
];

$page_title      = 'Log de WhatsApp';
$page_heading    = 'Log de notificações WhatsApp';
$page_subheading = 'Últimos 300 envios — quem recebeu, o que foi mandado e se deu certo';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-head" style="grid-template-columns: 150px 130px 140px 160px 1fr 220px; min-width: 1000px;">
                        <div class="admin-cot-cell">Data/hora</div>
                        <div class="admin-cot-cell">Evento</div>
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell">Enviado para</div>
                        <div class="admin-cot-cell">Mensagem</div>
                        <div class="admin-cot-cell">Status</div>
                    </div>

                    <?php if (!$logs): ?>
                    <div class="admin-cot-empty">Nenhum envio registrado ainda.</div>
                    <?php endif; ?>

                    <?php foreach ($logs as $log): ?>
                    <div class="admin-cot-row" style="grid-template-columns: 150px 130px 140px 160px 1fr 220px; min-width: 1000px;">
                        <div class="admin-cot-cell" style="color:var(--color-gray-500)">
                            <?= e(date('d/m/Y H:i:s', strtotime($log['criado_em']))) ?>
                        </div>
                        <div class="admin-cot-cell">
                            <?= e($eventos_label[$log['evento']] ?? $log['evento']) ?>
                        </div>
                        <div class="admin-cot-cell"><span class="proto-code"><?= e($log['protocolo'] ?: '—') ?></span></div>
                        <div class="admin-cot-cell">
                            <?= e($log['atendente_nome'] ?? '—') ?>
                            <?php if (!empty($log['telefone'])): ?>
                            <br><span style="color:var(--color-gray-400); font-size:var(--font-size-xs)"><?= e($log['telefone']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-cot-cell" title="<?= e($log['mensagem']) ?>" style="white-space:normal; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; font-size:var(--font-size-xs)">
                            <?= nl2br(e($log['mensagem'])) ?>
                        </div>
                        <div class="admin-cot-cell">
                            <?php if ($log['sucesso']): ?>
                            <span class="badge badge-primary">Sucesso</span>
                            <?php else: ?>
                            <span class="badge" style="background-color:var(--color-danger-bg);color:var(--color-danger)" title="<?= e($log['erro'] ?? '') ?>">
                                Erro<?= $log['erro'] ? ' - ' . e(mb_substr($log['erro'], 0, 80)) . (mb_strlen($log['erro']) > 80 ? '…' : '') : '' ?>
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
