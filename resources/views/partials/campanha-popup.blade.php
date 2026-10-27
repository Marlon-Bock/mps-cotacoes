{{--
    Popup de campanha por tela de produto (era pages/partials/campanha-popup.php).
    @include('partials.campanha-popup')

    Espera:
      $campanha_popup_tipo  'residencial'|'comercial'|'auto'|'titulo'|'condominial' (definida pela página)
      $current_user         usuário autenticado (usa imobiliaria_id)
    Define (só aqui dentro; não volta pro pai): $campanha_popup, $campanha_popup_obs.

    Renderiza o modal só se houver uma campanha ativa elegível — sem JS extra
    (campanha-popup.js) o modal fica parado, escondido.
--}}
@php
    $campanha_popup     = campanha_popup_ativa((int) $current_user->imobiliaria_id, $campanha_popup_tipo ?? '');
    $campanha_popup_obs = trim((string) ($campanha_popup['observacoes'] ?? ''));
@endphp
@if ($campanha_popup)
<div class="modal modal-campanha-popup" id="modal-campanha-popup" data-popup-campanha data-campanha-id="{{ (int) $campanha_popup['id'] }}" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-popup"></div>
    <div class="modal-dialog">
        <button type="button" class="modal-close" data-close="modal-campanha-popup" aria-label="Fechar">{!! icon('x') !!}</button>
        @if ($campanha_popup_obs !== '')
        <button type="button" class="modal-campanha-popup-obs-btn" data-open="modal-campanha-popup-obs">{!! icon('file-text') !!} Observações</button>
        @endif
        <a href="{{ url('/campanhas') }}" class="modal-campanha-popup-link">
            <img src="{{ $campanha_popup['imagem'] }}" alt="{{ $campanha_popup['titulo'] }}">
        </a>
    </div>
</div>
@endif
@if ($campanha_popup && $campanha_popup_obs !== '')
<div class="modal modal-campanha-obs" id="modal-campanha-popup-obs" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-popup-obs"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>{!! icon('file-text') !!} Observações</h3>
            <button type="button" class="modal-close" data-close="modal-campanha-popup-obs" aria-label="Fechar">{!! icon('x') !!}</button>
        </div>
        <div class="modal-body">
            <p>{!! nl2br(e($campanha_popup_obs)) !!}</p>
        </div>
    </div>
</div>
@endif
