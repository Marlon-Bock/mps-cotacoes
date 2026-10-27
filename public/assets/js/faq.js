document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-toggle="faq-item"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            btn.closest('.faq-item').classList.toggle('is-open');
        });
    });

    const busca = document.getElementById('faq-busca');
    if (!busca) return;

    busca.addEventListener('input', () => {
        const termo = busca.value.trim().toLowerCase();
        document.querySelectorAll('.faq-item').forEach((item) => {
            const texto = item.textContent.toLowerCase();
            item.classList.toggle('is-hidden', termo !== '' && !texto.includes(termo));
        });
        document.querySelectorAll('.faq-bloco').forEach((bloco) => {
            const algumVisivel = [...bloco.querySelectorAll('.faq-item')].some((i) => !i.classList.contains('is-hidden'));
            bloco.classList.toggle('is-hidden', !algumVisivel);
        });
    });
});
