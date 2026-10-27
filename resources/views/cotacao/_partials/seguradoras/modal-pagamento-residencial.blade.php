{{--
    Modal "Aceitar proposta" do Seguro Residencial: o segurado já está na
    ficha, então o cliente só escolhe o pagamento → emissão. Mesmo id do modal
    da Fiança (modal-proprietario) — o JS distingue pelo data-produto.
    @include('cotacao._partials.seguradoras.modal-pagamento-residencial')

    Pagamento por seguradora: Pottencial = boleto, 1 a 11 parcelas de no
    mínimo R$ 25; Too = fatura com as parcelas do contrato (sem escolha).

    Espera: $ficha, $api['lote']['cotacoes'].
--}}
@php
$__opcoesPagamento = [];
foreach ($api['lote']['cotacoes'] as $__c) {
    $__premio = (float) ($__c['premio_total'] ?? 0);
    if ($__premio <= 0) continue;
    if ($__c['seguradora'] === 'pottencial_res') {
        $__max = max(1, min(11, (int) floor($__premio / 25)));
        $__op = [];
        for ($__n = 1; $__n <= $__max; $__n++) {
            $__op[$__n] = $__n === 1
                ? 'À vista — R$ ' . number_format($__premio, 2, ',', '.')
                : "{$__n}x de R$ " . number_format($__premio / $__n, 2, ',', '.');
        }
        $__opcoesPagamento[$__c['seguradora']] = $__op;
    }
}
@endphp
<div class="modal" id="modal-escolher-proposta" hidden>
    <div class="modal-backdrop" data-close="modal-escolher-proposta"></div>
    <div class="modal-dialog seg-modal-proprietario">
        <div class="modal-header">
            <h3>Aceitar proposta</h3>
            <button type="button" class="modal-close" data-close="modal-escolher-proposta">{!! icon('x') !!}</button>
        </div>
        <form id="form-escolher-proposta" class="seg-modal-form" data-produto="residencial" novalidate>
          <div class="modal-body">
            <input type="hidden" name="seguradora" value="">
            <p class="seg-modal-intro">Confirme a forma de pagamento. Ao confirmar, a proposta é aceita e a apólice emitida na seguradora em nome de <strong>{{ $ficha['nome_completo'] }}</strong> — a MPS envia a apólice quando sair.</p>

            @foreach ($__opcoesPagamento as $__slug => $__op)
            <div class="field" data-somente-seguradora="{{ $__slug }}" hidden>
                <label class="field-label">Parcelamento no boleto <span class="required">*</span></label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                    {!! custom_select('parcelas_' . $__slug, $__op, 'Selecione', '1', 'has-icon', true) !!}
                </div>
            </div>
            @endforeach

            <div class="seg-modal-aviso" data-somente-seguradora="too_res" hidden>
                {!! icon('info') !!} Pagamento por fatura, nas condições do contrato com a Too Seguros.
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close="modal-escolher-proposta">{!! icon('x') !!} Cancelar</button>
            <button type="submit" class="btn btn-primary" id="btn-confirmar-proposta">{!! icon('check') !!} Aceitar proposta</button>
          </div>
        </form>
    </div>
</div>
