<?php
/** Modal "Corrigir dados": só os campos que a seguradora recusou. */
    $definicoes = api_campos_corrigiveis();
?>
<div class="modal" id="modal-corrigir-dados" hidden>
    <div class="modal-backdrop" data-close="modal-corrigir-dados"></div>
    <div class="modal-dialog seg-modal-proprietario">
        <div class="modal-header">
            <h3>Corrigir dados</h3>
            <button type="button" class="modal-close" data-close="modal-corrigir-dados"><?= icon('x') ?></button>
        </div>
        <form id="form-corrigir-dados" class="seg-modal-form" novalidate>
          <div class="modal-body">
            <?php foreach (array_unique(array_column($correcoes, 'mensagem')) as $mensagem): ?>
            <?php $aviso = ['tipo' => 'erro', 'titulo' => 'Dado recusado', 'texto' => $mensagem]; require __DIR__ . '/aviso.php'; ?>
            <?php endforeach; ?>
            <p class="seg-modal-intro">Ao salvar, a cotação é reenviada.</p>

            <div class="seg-correcao-campos">
            <?php foreach ($camposCorrigir as $campo):
                [$rotulo, $icone, $tipo, $placeholder, $max] = $definicoes[$campo];
                $valor = (string) ($ficha[$campo] ?? '');
                if ($tipo === 'cep') $valor = preg_replace('/(\d{5})(\d{3})/', '$1-$2', $valor);
                if ($tipo === 'cpf') $valor = api_formatar_documento($valor);
                if (in_array($tipo, ['moeda', 'aluguel'], true)) $valor = (float) $valor > 0 ? 'R$ ' . number_format((float) $valor, 2, ',', '.') : '';
                $mascara = ['cep' => 'cep', 'cpf' => 'cpfcnpj'][$tipo] ?? null;
                $largo   = in_array($campo, ['nome_completo', 'email', 'endereco'], true);
            ?>
                <div class="field <?= $largo ? 'seg-correcao-campo--largo' : '' ?>">
                    <label class="field-label" for="corrigir-<?= e($campo) ?>"><?= e($rotulo) ?><?= in_array($tipo, ['opcional', 'moeda'], true) ? '' : ' <span class="required">*</span>' ?></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon($icone) ?></span>
                        <input class="field-input has-icon" type="<?= $tipo === 'email' ? 'email' : 'text' ?>" id="corrigir-<?= e($campo) ?>" name="<?= e($campo) ?>"
                               maxlength="<?= (int) $max ?>" placeholder="<?= e($placeholder) ?>" value="<?= e($valor) ?>"
                               data-tipo="<?= e($tipo) ?>"<?= $mascara ? ' data-mask="' . $mascara . '"' : '' ?><?= in_array($tipo, ['moeda', 'aluguel', 'cep', 'cpf'], true) ? ' inputmode="numeric"' : '' ?>>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
          </div>

          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-close="modal-corrigir-dados">Cancelar</button>
              <button type="submit" class="btn btn-primary">
                  <?= icon('arrows-clockwise') ?> Salvar e reenviar
              </button>
          </div>
        </form>
    </div>
</div>
