<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Termos de Serviço — MPS Cotações</title>
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

        <h1 class="auth-institucional-title">Termos de Serviço</h1>
        <p class="auth-institucional-date">Última atualização: junho de 2026</p>

        <div class="auth-institucional-body">

            <h2>1. Aceitação</h2>
            <p>Ao acessar ou utilizar a plataforma <strong>MPS Cotações</strong>, operada pela Mesquita Prime e Sami Corretora de Seguros (&ldquo;MPS&rdquo;), você concorda integralmente com estes Termos. Caso não concorde, não utilize a plataforma.</p>

            <h2>2. Descrição do serviço</h2>
            <p>A MPS Cotações é uma plataforma digital que permite a imobiliárias parceiras solicitar cotações de seguro fiança locatícia (residencial e comercial), seguro auto e demais produtos disponibilizados pela MPS. A contratação está sujeita à análise e aprovação da corretora e das seguradoras parceiras.</p>

            <h2>3. Cadastro e acesso</h2>
            <p>O acesso é restrito às imobiliárias parceiras previamente credenciadas pela MPS. Cada imobiliária recebe credenciais individuais. É responsabilidade do usuário manter o sigilo de sua senha e comunicar imediatamente qualquer acesso não autorizado.</p>

            <h2>4. Uso adequado</h2>
            <p>O usuário compromete-se a fornecer informações verídicas e atualizadas; utilizar a plataforma exclusivamente para os fins a que se destina; não tentar acessar dados de outras imobiliárias; e não realizar ataques ou engenharia reversa no sistema.</p>

            <h2>5. Responsabilidades da MPS</h2>
            <p>A MPS se compromete a manter a plataforma disponível em regime de melhor esforço e a tratar as cotações dentro dos prazos acordados. A MPS não garante a aprovação de qualquer cotação, pois a decisão final cabe às seguradoras parceiras.</p>

            <h2>6. Limitação de responsabilidade</h2>
            <p>A MPS não se responsabiliza por danos decorrentes de: indisponibilidade temporária por manutenção ou força maior; uso indevido das credenciais; informações incorretas fornecidas no momento da cotação.</p>

            <h2>7. Propriedade intelectual</h2>
            <p>Todo o conteúdo da plataforma (código, design, textos, logotipos) é de propriedade exclusiva da MPS ou de seus licenciantes. É proibida qualquer reprodução sem autorização prévia por escrito.</p>

            <h2>8. Privacidade</h2>
            <p>O tratamento dos dados pessoais é regido pela <a href="<?= e(url('/politica-de-privacidade')) ?>">Política de Privacidade</a>, incorporada a estes Termos por referência.</p>

            <h2>9. Alterações</h2>
            <p>A MPS reserva-se o direito de alterar estes Termos a qualquer momento, com comunicação prévia de 15 dias pelo e-mail cadastrado.</p>

            <h2>10. Foro</h2>
            <p>Estes Termos são regidos pelas leis brasileiras. As partes elegem o foro da Comarca de São Paulo/SP para dirimir quaisquer controvérsias.</p>
        </div>

        <div class="auth-institucional-actions">
            <a class="btn btn-primary" href="<?= e(url('/login')) ?>">Entrar no sistema</a>
            <a class="btn btn-secondary" href="<?= e(url('/politica-de-privacidade')) ?>">Política de Privacidade</a>
        </div>
    </div>
</div>

</body>
</html>
