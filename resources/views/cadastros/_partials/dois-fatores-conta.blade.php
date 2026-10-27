{{-- Verificação em duas etapas de uma conta (Cadastros\DoisFatoresContaController).
     Espera: $df_tabela ('usuario' = MPS | 'users' = imobiliária), $df_usuario_id, $df_proprio (true = Meu Perfil).
     Admin da MPS redefine a de qualquer usuário (perdeu o celular, e-mail errado); no perfil, a pessoa troca a própria. --}}
@php
// O suporte da Sami usa o Authenticator da conta MPS também na de imobiliária (df_conta_config).
[$__dfTabela, $__dfId] = df_conta_config($df_tabela, (int) $df_usuario_id);
$__df = df_config($__dfTabela, $__dfId);
$__dfMetodos = df_metodos_permitidos($__dfTabela, $__dfId);
$__podeRedefinir = $df_proprio || !empty(auth_user()->mps_admin);
@endphp
<div class="panel">
    <div class="panel-header">
        <h2>{!! icon('lock') !!} Verificação em duas etapas</h2>
    </div>
    <div class="panel-body df-conta">
        @if ($__df)
        <span class="df-conta-status is-ativo">{!! icon('check-circle') !!}
            {{ $__df['metodo'] === 'totp' ? 'Google Authenticator' : 'Código por e-mail' }}
        </span>
        @if ($__df['metodo'] === 'email' && $__df['email'])
        <span class="df-conta-detalhe">Códigos enviados para {{ df_email_mascarado((string) $__df['email']) }}</span>
        @endif
        <span class="df-conta-detalhe">Configurada em {{ date('d/m/Y H:i', strtotime((string) $__df['configurado_em'])) }}</span>
        @if ($__podeRedefinir)
        <button type="button" class="btn btn-secondary btn-sm" data-df-redefinir
                data-tabela="{{ $df_tabela }}" data-usuario="{{ (int) $df_usuario_id }}" data-proprio="{{ $df_proprio ? '1' : '0' }}">
            {!! icon('arrows-clockwise') !!} {{ $df_proprio ? 'Trocar método ou e-mail' : 'Redefinir' }}
        </button>
        @endif
        @else
        <span class="df-conta-status">{!! icon('warning') !!} Ainda não configurada</span>
        <span class="df-conta-detalhe">É configurada no próximo login{{ $__dfMetodos === ['totp'] ? ' (Google Authenticator)' : (in_array('totp', $__dfMetodos, true) ? ' (Google Authenticator ou e-mail)' : ' (e-mail que vai receber os códigos)') }}.</span>
        @endif
    </div>
</div>
@once
<script>
document.addEventListener('click', async (e) => {
    const btn = e.target.closest('[data-df-redefinir]');
    if (!btn) return;
    const proprio = btn.dataset.proprio === '1';
    const ok = await mpsAlert.confirm({
        title: proprio ? 'Trocar a verificação em duas etapas?' : 'Redefinir a verificação em duas etapas?',
        message: proprio
            ? 'No próximo login você escolhe de novo como receber o código.'
            : 'No próximo login a pessoa configura de novo (o método atual deixa de valer). Use quando ela perdeu o celular ou o e-mail dos códigos.',
        confirmText: proprio ? 'Trocar' : 'Redefinir',
    });
    if (!ok) return;
    mpsAlert.loading('Salvando...');
    try {
        const resp = await fetch(proprio ? '/api/perfil/2fa/redefinir' : '/api/admin/2fa/redefinir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': document.querySelector('meta[name="csrf-token"]')?.content || '' },
            body: JSON.stringify({ tabela: btn.dataset.tabela, usuario_id: parseInt(btn.dataset.usuario, 10) }),
        });
        const d = await resp.json();
        if (!d.success) return mpsAlert.error(d.message || 'Não foi possível redefinir.');
        await mpsAlert.success(d.message);
        window.location.reload();
    } catch (_) {
        mpsAlert.error('Erro na requisição.');
    }
});
</script>
@endonce
