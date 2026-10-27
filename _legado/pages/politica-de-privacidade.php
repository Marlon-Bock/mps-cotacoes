<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Política de Privacidade — MPS Cotações</title>
    <link rel="icon" href="<?= e(url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png')) ?>">
    <meta name="base-path" content="<?= e(BASE_URI) ?>">
    <script src="<?= e(asset_v('/assets/js/base-path.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/auth.css')) ?>">
</head>
<body>

<div class="auth-screen-centered">
    <div class="card auth-institucional-box">
        <div class="auth-institucional-logo">
            <img src="<?= e(url('/assets/img/logo.webp')) ?>" alt="MPS Cotações" height="40">
        </div>

        <h1 class="auth-institucional-title">Política de Privacidade</h1>
        <p class="auth-institucional-date">Última atualização: junho de 2026</p>

        <div class="auth-institucional-body">

            <h2>1. Quem somos</h2>
            <p>A <strong>Mesquita Prime e Sami Corretora de Seguros</strong> (&ldquo;MPS&rdquo;) opera a plataforma MPS Cotações e é responsável pelo tratamento dos dados pessoais coletados neste sistema. Somos o <strong>controlador</strong> dos dados nos termos da Lei Geral de Proteção de Dados (LGPD &mdash; Lei 13.709/2018).</p>

            <h2>2. Quais dados coletamos</h2>
            <p>Coletamos apenas os dados necessários para a prestação dos nossos serviços de cotação de seguros:</p>
            <ul>
                <li>Dados de identificação do pretendente (CPF/CNPJ, nome completo, data de nascimento);</li>
                <li>Dados de contato (e-mail, telefone);</li>
                <li>Dados financeiros (renda declarada para análise de elegibilidade);</li>
                <li>Dados do imóvel (endereço, valor do aluguel, coberturas desejadas);</li>
                <li>Dados do veículo e condutor (para seguro auto);</li>
                <li>Dados de acesso (endereço IP, logs de sessão).</li>
            </ul>

            <h2>3. Finalidade do tratamento</h2>
            <p>Os dados são tratados para:</p>
            <ul>
                <li>Análise e emissão de cotações de seguro fiança e seguro auto;</li>
                <li>Comunicação sobre o andamento das cotações;</li>
                <li>Cumprimento de obrigações legais e regulatórias;</li>
                <li>Segurança e prevenção de fraudes.</li>
            </ul>

            <h2>4. Base legal</h2>
            <p>O tratamento é baseado na <strong>execução de contrato</strong> (art. 7º, V da LGPD), no <strong>legítimo interesse</strong> (art. 7º, IX) e, quando aplicável, no <strong>consentimento</strong> do titular.</p>

            <h2>5. Compartilhamento de dados</h2>
            <p>Os dados podem ser compartilhados com seguradoras parceiras para análise e emissão de apólices, prestadores de serviço sob contrato de confidencialidade e autoridades públicas quando exigido por lei. Não vendemos dados pessoais a terceiros.</p>

            <h2>6. Retenção</h2>
            <p>Os dados são retidos pelo prazo necessário ao cumprimento das finalidades declaradas ou exigido por lei, após o qual são anonimizados ou eliminados de forma segura.</p>

            <h2>7. Direitos do titular</h2>
            <p>Nos termos da LGPD, você tem direito a: confirmar a existência de tratamento; acessar, corrigir ou eliminar seus dados; revogar consentimento. Para exercer esses direitos, entre em contato: <strong>privacidade@mesquitaprime.com.br</strong>.</p>

            <h2>8. Segurança</h2>
            <p>Adotamos medidas técnicas e organizacionais para proteger os dados, incluindo autenticação com cookies HttpOnly, comunicação via HTTPS e controle de acesso por perfis.</p>

            <h2>9. Cookies</h2>
            <p>Utilizamos cookies estritamente necessários para manter a sessão autenticada. Não usamos cookies de rastreamento ou publicidade.</p>

            <h2>10. Contato</h2>
            <p>Dúvidas sobre privacidade podem ser enviadas ao encarregado (DPO): <strong>privacidade@mesquitaprime.com.br</strong>.</p>
        </div>

        <div class="auth-institucional-actions">
            <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Entrar no sistema</a>
            <a class="btn btn-secondary" href="<?= e(url('/termos-de-servico')) ?>">Termos de Serviço</a>
        </div>
    </div>
</div>

</body>
</html>
