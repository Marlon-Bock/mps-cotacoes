document.addEventListener('DOMContentLoaded', function () {
    const protocolo = window.cotacaoProtocolo;
    if (!protocolo) return;

    const btnAprovar = document.getElementById('btn-aprovar');
    const btnRecusar = document.getElementById('btn-recusar');

    async function postAcao(endpoint, confirmar) {
        const ok = await mpsAlert.confirm({ message: confirmar });
        if (!ok) return;

        fetch('/api/cotacao/' + encodeURIComponent(protocolo) + '/' + endpoint, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
        })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    window.location.reload();
                } else {
                    mpsAlert.error(data.message || 'Tente novamente.');
                }
            })
            .catch(() => mpsAlert.error('Falha na comunicação com o servidor.'));
    }

    if (btnAprovar) {
        btnAprovar.addEventListener('click', function () {
            postAcao('aprovar', 'Confirma a aprovação da cotação enviada pela MPS?');
        });
    }

    if (btnRecusar) {
        btnRecusar.addEventListener('click', function () {
            postAcao('recusar', 'Confirma a recusa da cotação? A MPS será notificada.');
        });
    }
});
