@php
/*
 * Termos de Serviço — /termos-de-servico (SistemaController@termos).
 * Texto revisado em out/2026 para os produtos atuais; mudanças de conteúdo
 * jurídico devem passar pela revisão da MPS.
 */
$secoes = [
    ['id' => 'aceitacao', 'titulo' => 'Aceitação', 'html' => '
        <p>Ao acessar ou usar a plataforma <strong>MPS Cotações</strong>, operada pela <strong>Mesquita Prime e Sami Corretora de Seguros</strong>
        (“MPS”), você concorda com estes Termos. Se não concordar, não use a plataforma.</p>'],

    ['id' => 'servico', 'titulo' => 'O serviço', 'html' => '
        <p>A MPS Cotações é uma plataforma digital em que imobiliárias parceiras solicitam cotações de seguros para seus clientes:</p>
        <ul>
            <li>seguro fiança locatícia (residencial e comercial);</li>
            <li>seguro residencial, seguro incêndio e seguro condominial;</li>
            <li>seguro auto e título de capitalização.</li>
        </ul>
        <p>As cotações são analisadas pela MPS e pelas seguradoras parceiras. A contratação depende da aprovação delas.</p>'],

    ['id' => 'acesso', 'titulo' => 'Cadastro e acesso', 'html' => '
        <p>O acesso é restrito a imobiliárias credenciadas pela MPS e à equipe da MPS. Cada usuário tem login próprio e é
        responsável por manter sua senha em sigilo e por avisar a MPS sobre qualquer acesso não autorizado.</p>
        <p>O administrador de cada imobiliária pode criar usuários e definir o que cada um pode fazer na plataforma.</p>'],

    ['id' => 'formulario-site', 'titulo' => 'Formulário no site da imobiliária', 'html' => '
        <p>A imobiliária pode publicar os formulários de cotação da MPS no próprio site. Ao fazer isso, ela se responsabiliza
        por usá-los somente no domínio informado à MPS e para os fins previstos nestes Termos.</p>'],

    ['id' => 'uso', 'titulo' => 'Uso adequado', 'html' => '
        <p>Ao usar a plataforma, você se compromete a:</p>
        <ul>
            <li>informar dados verdadeiros e atualizados, com autorização do titular quando forem de terceiros;</li>
            <li>usar a plataforma somente para cotar e acompanhar seguros;</li>
            <li>não tentar acessar dados de outras imobiliárias ou contornar os controles de acesso;</li>
            <li>não fazer ataques, testes de invasão não autorizados ou engenharia reversa do sistema.</li>
        </ul>'],

    ['id' => 'responsabilidades', 'titulo' => 'Responsabilidades da MPS', 'html' => '
        <p>A MPS se compromete a manter a plataforma disponível no melhor esforço e a tratar as cotações nos prazos combinados.</p>
        <div class="legal-destaque">A MPS <strong>não garante a aprovação</strong> de nenhuma cotação: a decisão de aceitar o risco é
        sempre das seguradoras.</div>'],

    ['id' => 'limitacao', 'titulo' => 'Limitação de responsabilidade', 'html' => '
        <p>A MPS não se responsabiliza por danos decorrentes de:</p>
        <ul>
            <li>indisponibilidade temporária por manutenção, falha de terceiros (inclusive das seguradoras) ou força maior;</li>
            <li>uso indevido das credenciais de acesso;</li>
            <li>informações incorretas ou incompletas fornecidas na cotação.</li>
        </ul>'],

    ['id' => 'propriedade', 'titulo' => 'Propriedade intelectual', 'html' => '
        <p>Todo o conteúdo da plataforma — código, design, textos e marcas — pertence à MPS ou a seus licenciantes. É proibida a
        reprodução sem autorização prévia por escrito.</p>'],

    ['id' => 'privacidade', 'titulo' => 'Privacidade', 'html' => '
        <p>O tratamento de dados pessoais segue a <a href="' . e(url('/politica-de-privacidade')) . '">Política de Privacidade</a>,
        que faz parte destes Termos.</p>'],

    ['id' => 'alteracoes', 'titulo' => 'Alterações', 'html' => '
        <p>A MPS pode alterar estes Termos, avisando com 15 dias de antecedência pelo e-mail cadastrado. Continuar usando a
        plataforma depois desse prazo significa aceitar a nova versão.</p>'],

    ['id' => 'foro', 'titulo' => 'Lei aplicável e foro', 'html' => '
        <p>Estes Termos seguem as leis brasileiras. Fica eleito o foro da Comarca de São Paulo/SP para resolver qualquer controvérsia.</p>'],
];
@endphp
@include('legal._layout', [
    'titulo'      => 'Termos de Serviço',
    'resumo'      => 'As regras de uso da plataforma MPS Cotações pelas imobiliárias parceiras e pela equipe da MPS.',
    'atualizacao' => 'outubro de 2026',
    'atual'       => 'termos',
    'secoes'      => $secoes,
])
