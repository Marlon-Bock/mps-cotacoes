<?php
/**
 * GET /admin/faq/fluxo
 * Construtor visual do fluxo de perguntas do FAQ (chat guiado estilo
 * chatbot) — blocos empilháveis, arrastáveis, editados inline. Salva tudo
 * de uma vez como um JSON só via /api/admin/faq-fluxo/salvar.
 *
 * Estrutura do DOM: dentro de #flow-list (ou de um .mini-flow-list), zonas
 * "adicionar bloco" e blocos (.flow-wrapper) ficam intercalados como irmãos
 * diretos — nunca uma zona aninhada dentro de um bloco. Isso evita precisar
 * rastrear índice/posição pra saber onde inserir: o JS sempre insere logo
 * depois da zona clicada, e a serialização final só lê ':scope > .flow-wrapper'
 * (a ordem real do DOM), ignorando as zonas.
 */
$current_user = require_admin();

function fluxoTipoLabel(string $tipo): string
{
    return match ($tipo) {
        'mensagem'       => 'Mensagem do Bot',
        'mensagem_botao' => 'Mensagem com Botão',
        'resposta_texto' => 'Resposta Livre do Usuário',
        'opcoes'         => 'Lista de Opções',
        'chamado'        => 'Abrir Chamado',
        default          => $tipo,
    };
}

function fluxoTipoIcone(string $tipo): string
{
    return match ($tipo) {
        'mensagem'       => 'chat-circle',
        'mensagem_botao' => 'check-circle',
        'resposta_texto' => 'note-pencil',
        'opcoes'         => 'list',
        'chamado'        => 'ticket',
        default          => 'question',
    };
}

function fluxoRenderCampos(array $bloco, string $tipo): void
{
    $v = fn(string $k) => e($bloco[$k] ?? '');
    switch ($tipo) {
        case 'mensagem': ?>
            <div class="flow-field">
                <label class="flow-field-label">Mensagem do bot</label>
                <textarea class="field-input flow-input" data-campo="mensagem" rows="2" placeholder="Digite a mensagem que o bot vai enviar..."><?= $v('mensagem') ?></textarea>
            </div>
        <?php break;

        case 'mensagem_botao': ?>
            <div class="flow-field">
                <label class="flow-field-label">Mensagem do bot</label>
                <textarea class="field-input flow-input" data-campo="mensagem" rows="2" placeholder="Digite a mensagem..."><?= $v('mensagem') ?></textarea>
            </div>
            <div class="flow-field">
                <label class="flow-field-label">Texto do botão</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('check-circle') ?></span>
                    <input class="field-input flow-input has-icon" type="text" data-campo="texto_botao" value="<?= $v('texto_botao') ?>" placeholder="Ex: Entendi, continuar">
                </div>
            </div>
        <?php break;

        case 'resposta_texto':
            $tipoCaptura = $bloco['tipo_captura'] ?? 'texto';
            $opcoesCaptura = [
                'texto'       => 'Texto livre',
                'nome'        => 'Nome',
                'telefone'    => 'Telefone (com máscara)',
                'imobiliaria' => 'Nome da imobiliária',
            ]; ?>
            <div class="flow-field">
                <label class="flow-field-label">Pergunta para o usuário</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('question') ?></span>
                    <input class="field-input flow-input has-icon" type="text" data-campo="pergunta" value="<?= $v('pergunta') ?>" placeholder="Ex: Descreva sua dúvida...">
                </div>
            </div>
            <div class="flow-field">
                <label class="flow-field-label">Tipo de resposta esperada</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('identification-card') ?></span>
                    <?= custom_select('tipo_captura', $opcoesCaptura, 'Selecione o tipo', $tipoCaptura, 'has-icon flow-input', false, ['data-campo' => 'tipo_captura']) ?>
                </div>
            </div>
        <?php break;

        case 'opcoes': ?>
            <div class="flow-field">
                <label class="flow-field-label">Mensagem antes das opções (opcional)</label>
                <textarea class="field-input flow-input" data-campo="mensagem" rows="2" placeholder="Ex: Sobre qual assunto você quer falar?"><?= $v('mensagem') ?></textarea>
            </div>
            <div class="flow-field">
                <label class="flow-field-label">Opções</label>
                <div class="opcoes-list">
                    <?php foreach (($bloco['opcoes'] ?? []) as $opcao) fluxoRenderOpcao($opcao); ?>
                </div>
                <button type="button" class="btn-add-opcao" data-action="add-opcao"><?= icon('plus') ?> Adicionar opção</button>
            </div>
        <?php break;

        case 'chamado': ?>
            <div class="alert alert-info flow-alert-flush">
                <span class="alert-icon"><?= icon('info') ?></span>
                Aqui o usuário vê um botão que leva pra abrir um chamado com a equipe MPS. Bloco terminal — não precisa de mais nada depois dele nesse ramo.
            </div>
        <?php break;
    }
}

function fluxoRenderOpcao(array $opcao): void
{
    ?>
    <div class="opcao-item">
        <div class="opcao-row">
            <span class="block-drag" title="Arrastar"><?= icon('list') ?></span>
            <div class="field-input-wrap opcao-nome">
                <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                <input class="field-input has-icon" type="text" data-campo="nome" value="<?= e($opcao['nome'] ?? '') ?>" placeholder="Nome da opção">
            </div>
            <button type="button" class="btn-icon-action" data-action="remover-opcao" title="Remover opção"><?= icon('x') ?></button>
        </div>
        <div class="miniflow-body">
            <div class="mini-flow-list">
                <?php fluxoRenderAddZone(); ?>
                <?php foreach (($opcao['minifluxo'] ?? []) as $miniBloco):
                    fluxoRenderBloco($miniBloco);
                    fluxoRenderAddZone();
                endforeach; ?>
            </div>
        </div>
    </div>
    <?php
}

function fluxoRenderBloco(array $bloco): void
{
    $tipo = $bloco['type'] ?? 'mensagem';
    ?>
    <div class="flow-wrapper">
        <div class="flow-block" data-type="<?= e($tipo) ?>">
            <div class="flow-block-header">
                <div class="flow-block-header-left">
                    <span class="block-drag" title="Arrastar"><?= icon('list') ?></span>
                    <span class="flow-type-badge type-<?= e($tipo) ?>"><?= icon(fluxoTipoIcone($tipo)) ?> <?= e(fluxoTipoLabel($tipo)) ?></span>
                </div>
                <button type="button" class="btn-icon-action" data-action="remover-bloco" title="Excluir bloco"><?= icon('x') ?></button>
            </div>
            <div class="flow-block-body">
                <?php fluxoRenderCampos($bloco, $tipo); ?>
            </div>
        </div>
    </div>
    <?php
}

function fluxoRenderAddZone(): void
{
    ?>
    <div class="add-block-zone">
        <div class="add-block-line"></div>
        <button type="button" class="btn-add-block" data-action="toggle-add-menu">
            <?= icon('plus') ?><span>Adicionar bloco</span>
        </button>
        <div class="add-block-panel"><?= fluxoRenderPainelTipos() ?></div>
    </div>
    <?php
}

function fluxoRenderPainelTipos(): string
{
    ob_start();
    ?>
    <div class="add-block-group-title">Mensagens</div>
    <button type="button" class="add-block-option" data-type="mensagem">
        <span class="add-block-option-icon type-mensagem"><?= icon('chat-circle') ?></span>
        <div><strong>Mensagem do Bot</strong><span>Envia texto simples</span></div>
    </button>
    <button type="button" class="add-block-option" data-type="mensagem_botao">
        <span class="add-block-option-icon type-mensagem_botao"><?= icon('check-circle') ?></span>
        <div><strong>Mensagem com Botão</strong><span>Texto com um botão único</span></div>
    </button>
    <button type="button" class="add-block-option" data-type="resposta_texto">
        <span class="add-block-option-icon type-resposta_texto"><?= icon('note-pencil') ?></span>
        <div><strong>Resposta Livre do Usuário</strong><span>Deixa o usuário digitar algo</span></div>
    </button>
    <button type="button" class="add-block-option" data-type="opcoes">
        <span class="add-block-option-icon type-opcoes"><?= icon('list') ?></span>
        <div><strong>Lista de Opções</strong><span>Múltipla escolha, com sub-fluxo</span></div>
    </button>
    <div class="add-block-group-title">Finalização</div>
    <button type="button" class="add-block-option" data-type="chamado">
        <span class="add-block-option-icon type-chamado"><?= icon('ticket') ?></span>
        <div><strong>Abrir Chamado</strong><span>Encerra levando pra /chamados/novo</span></div>
    </button>
    <?php
    return ob_get_clean();
}

$flow = [];
try {
    $row = db_fetch_one('SELECT json_data FROM faq_fluxo_json WHERE id = 1');
    if ($row) {
        $decoded = json_decode($row['json_data'], true);
        $flow = is_array($decoded['flow'] ?? null) ? $decoded['flow'] : [];
    }
} catch (Throwable $e) {
    $flow = [];
}

$page_title   = 'Fluxo do FAQ';
$page_heading = 'Fluxo do FAQ';
$page_back    = ['url' => '/admin/faq', 'label' => 'FAQ'];
$extra_css    = ['dashboard.css', 'faq.css', 'admin-faq-fluxo.css'];
$extra_js     = ['vendor/sortable.min.js', 'admin-faq-fluxo.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content" data-csrf="<?= e(csrf_token()) ?>">

            <div class="alert alert-info">
                <span class="alert-icon"><?= icon('info') ?></span>
                Monte o chat guiado do FAQ empilhando blocos. O bloco "Lista de Opções" ramifica: cada opção pode ter seu próprio sub-fluxo aninhado. Arraste pela alcinha pra reordenar.
            </div>

            <div class="admin-faq-toolbar">
                <a href="<?= e(url('/admin/faq/atendimentos')) ?>" class="btn btn-secondary btn-sm">
                    <?= icon('list') ?> Ver atendimentos
                </a>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-testar-fluxo">
                    <?= icon('chat-circle') ?> Testar fluxo
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-fluxo">
                    <?= icon('check') ?> Salvar fluxo
                </button>
            </div>

            <div id="flow-list">
                <?php if (!$flow): ?>
                <div class="flow-empty" id="flow-empty">
                    <div class="flow-empty-icon"><?= icon('chat-circle') ?></div>
                    <div class="flow-empty-title">Nenhum bloco criado ainda</div>
                    <div class="flow-empty-sub">Use o botão abaixo para adicionar o primeiro bloco do fluxo</div>
                </div>
                <?php endif; ?>
                <?php
                fluxoRenderAddZone();
                foreach ($flow as $bloco) {
                    fluxoRenderBloco($bloco);
                    fluxoRenderAddZone();
                }
                ?>
            </div>

        </main>
    </div>
</div>

<!-- Modal: testar fluxo (roda o fluxo em edição, sem precisar salvar antes) -->
<div class="modal" id="modal-testar-fluxo" hidden>
    <div class="modal-backdrop" data-close="modal-testar-fluxo"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Testar fluxo</h3>
            <button type="button" class="modal-close" data-close="modal-testar-fluxo"><?= icon('x') ?></button>
        </div>
        <div class="modal-body modal-body-flush">
            <div class="chamado-chat-box" id="testar-fluxo-box">
                <div class="chamado-chat-messages">
                    <div class="chamado-thread" id="testar-fluxo-thread"></div>
                </div>
                <div class="chamado-chat-input-bar" id="testar-fluxo-input-bar" hidden>
                    <input type="text" id="testar-fluxo-input" class="chamado-input-field" placeholder="Digite aqui..." maxlength="500" autocomplete="off">
                    <button type="button" class="chamado-send-btn" id="testar-fluxo-send"><?= icon('paper-plane-tilt') ?></button>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
