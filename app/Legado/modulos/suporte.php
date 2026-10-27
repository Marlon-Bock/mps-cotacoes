<?php
/**
 * Helpers do módulo Suporte (chamados, FAQ, campanhas).
 *
 * Renderização do construtor visual do fluxo do FAQ (/admin/faq/fluxo) — eram
 * funções declaradas dentro de pages/admin/faq-fluxo.php. Elas dão echo do HTML
 * (a view chama dentro de @php), igual ao template antigo.
 */

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

