/**
 * Popup de campanha (telas de produto) — abre uma única vez por sessão de
 * navegador por campanha E por página. O modal só existe no DOM quando o
 * servidor já encontrou uma campanha elegível (ver
 * pages/partials/campanha-popup.php). A chave inclui o pathname porque a
 * mesma campanha pode estar marcada pra aparecer em mais de uma tela de
 * produto (ex: Fiança e Auto) — sem isso, mostrar numa página já marcava
 * como "visto" e a outra nunca abria na mesma sessão.
 */
(function () {
    'use strict';

    var modal = document.querySelector('[data-popup-campanha]');
    if (!modal) return;

    var chave = 'campanha-popup-' + modal.dataset.campanhaId + '-' + window.location.pathname;
    if (sessionStorage.getItem(chave)) return;

    sessionStorage.setItem(chave, '1');
    if (typeof openModal === 'function') openModal(modal.id);
})();
