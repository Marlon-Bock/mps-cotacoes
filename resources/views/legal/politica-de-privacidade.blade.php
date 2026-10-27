@php
/*
 * Política de Privacidade — /politica-de-privacidade (SistemaController@politica).
 * Texto revisado em out/2026 para os produtos e integrações atuais do sistema;
 * mudanças de conteúdo jurídico devem passar pela revisão da MPS.
 */
$email_dpo = '<a href="mailto:privacidade@mesquitaprime.com.br">privacidade@mesquitaprime.com.br</a>';

$secoes = [
    ['id' => 'quem-somos', 'titulo' => 'Quem somos', 'html' => '
        <p>A <strong>Mesquita Prime e Sami Corretora de Seguros</strong> (“MPS”) opera a plataforma <strong>MPS Cotações</strong> e é a
        <strong>controladora</strong> dos dados pessoais tratados nela, nos termos da Lei Geral de Proteção de Dados
        (LGPD — Lei nº 13.709/2018).</p>
        <p>A plataforma é usada por imobiliárias parceiras para solicitar cotações de seguros em nome de seus clientes, e pela
        equipe da MPS para analisá-las junto às seguradoras.</p>'],

    ['id' => 'dados-coletados', 'titulo' => 'Quais dados coletamos', 'html' => '
        <p>Coletamos apenas os dados necessários para cotar e contratar os seguros solicitados:</p>
        <ul>
            <li><strong>Identificação:</strong> nome, CPF ou CNPJ, data de nascimento, estado civil e profissão do pretendente, segurado ou proprietário;</li>
            <li><strong>Contato:</strong> e-mail e telefone;</li>
            <li><strong>Dados financeiros:</strong> renda declarada e vínculo empregatício, usados na análise de crédito do seguro fiança;</li>
            <li><strong>Dados do imóvel:</strong> endereço, valores de aluguel e encargos, características do imóvel e coberturas desejadas;</li>
            <li><strong>Dados do veículo e do condutor</strong>, para o seguro auto;</li>
            <li><strong>Documentos enviados</strong> à plataforma (propostas, apólices, comprovantes e outros arquivos);</li>
            <li><strong>Dados de acesso:</strong> endereço IP, data e hora de acesso e registros de uso do sistema.</li>
        </ul>
        <div class="legal-destaque"><strong>Biometria facial.</strong> Algumas seguradoras exigem a validação facial do pretendente
        do seguro fiança. Nesse caso, o pretendente recebe um link e faz a biometria <strong>diretamente no ambiente da seguradora</strong>;
        a MPS não armazena a imagem do rosto, apenas o resultado da validação.</div>'],

    ['id' => 'finalidades', 'titulo' => 'Para que usamos os dados', 'html' => '
        <ul>
            <li>Cotar, contratar e acompanhar seguros: fiança locatícia (residencial e comercial), residencial, incêndio,
            condominial, auto e título de capitalização;</li>
            <li>Enviar a ficha às seguradoras parceiras para análise de crédito e cadastro e para a emissão da apólice;</li>
            <li>Avisar sobre o andamento das cotações, por e-mail, WhatsApp e notificações na plataforma;</li>
            <li>Organizar as visitas da equipe comercial da MPS e, para quem conectar a conta Google, registrá-las no Google Agenda;</li>
            <li>Cumprir obrigações legais e regulatórias do mercado de seguros;</li>
            <li>Manter a segurança da plataforma e prevenir fraudes.</li>
        </ul>'],

    ['id' => 'base-legal', 'titulo' => 'Base legal', 'html' => '
        <p>Tratamos os dados com base na <strong>execução de contrato e de procedimentos preliminares</strong> a ele (art. 7º, V da LGPD),
        no <strong>cumprimento de obrigação legal ou regulatória</strong> (art. 7º, II), na <strong>proteção do crédito</strong> (art. 7º, X),
        no <strong>legítimo interesse</strong> (art. 7º, IX) e, quando aplicável, no <strong>consentimento</strong> do titular.</p>
        <p>Dados sensíveis, como a biometria facial, são tratados para a <strong>prevenção à fraude e segurança do titular</strong>
        na identificação (art. 11, II, “g”).</p>'],

    ['id' => 'compartilhamento', 'titulo' => 'Com quem compartilhamos', 'html' => '
        <ul>
            <li><strong>Seguradoras parceiras</strong>, para análise, cotação e emissão de apólices — inclusive de forma automática, por integração entre sistemas;</li>
            <li><strong>Imobiliária</strong> que solicitou a cotação, que acompanha o andamento e os documentos;</li>
            <li><strong>Prestadores de serviço</strong> que viabilizam a plataforma (hospedagem, envio de e-mail e de mensagens de WhatsApp), sob dever de confidencialidade;</li>
            <li><strong>Autoridades públicas</strong>, quando exigido por lei ou ordem judicial.</li>
        </ul>
        <p><strong>Não vendemos dados pessoais.</strong></p>'],

    ['id' => 'dados-google', 'titulo' => 'Dados de contas Google (Google Agenda)', 'html' => '
        <p>Usuários da <strong>equipe da MPS</strong> podem, se quiserem, conectar a própria conta Google na tela de visitas, para que
        as visitas agendadas na plataforma apareçam automaticamente no Google Agenda deles. Imobiliárias e seus clientes não
        conectam contas Google.</p>
        <p><strong>O que acessamos:</strong> somente a permissão de eventos do Google Agenda
        (<code>calendar.events</code>), usada exclusivamente para <strong>criar, atualizar e excluir os eventos das visitas
        agendadas na própria plataforma</strong> — título, data, horário, local e observações da visita. Não lemos, listamos nem
        alteramos outros compromissos da agenda, e não acessamos e-mail, contatos, arquivos ou qualquer outro dado da conta.</p>
        <p><strong>O que guardamos:</strong> os tokens de acesso fornecidos pelo Google (para manter a conexão ativa) e o
        identificador de cada evento criado (para poder atualizá-lo ou excluí-lo quando a visita mudar ou for cancelada).</p>
        <p><strong>O que nunca fazemos:</strong> não vendemos nem compartilhamos dados obtidos do Google com terceiros, não os
        usamos para publicidade nem para treinar modelos de inteligência artificial, e eles não são lidos por pessoas, exceto
        quando necessário por segurança, para cumprir a lei ou com o seu consentimento.</p>
        <p><strong>Como desconectar:</strong> use o botão “Desconectar Google” na tela de visitas — o acesso é revogado no Google
        e os tokens são apagados da plataforma na hora. Você também pode remover o acesso em
        <a href="https://myaccount.google.com/permissions" target="_blank" rel="noopener">myaccount.google.com/permissions</a>.</p>
        <div class="legal-destaque">O uso e a transferência, para qualquer outro aplicativo, de informações recebidas das APIs do
        Google pela MPS Cotações seguem a <a href="https://developers.google.com/terms/api-services-user-data-policy" target="_blank" rel="noopener">Política
        de Dados do Usuário dos Serviços de API do Google</a>, incluindo os requisitos de Uso Limitado.</div>'],

    ['id' => 'formulario-imobiliaria', 'titulo' => 'Formulário no site da imobiliária', 'html' => '
        <p>Algumas imobiliárias exibem o formulário de cotação da MPS dentro do próprio site. Os dados digitados nesse formulário
        são enviados <strong>diretamente à MPS</strong>, com o mesmo tratamento e a mesma segurança descritos nesta política, e ficam
        disponíveis para a imobiliária que o publicou.</p>'],

    ['id' => 'retencao', 'titulo' => 'Por quanto tempo guardamos', 'html' => '
        <p>Os dados ficam guardados enquanto forem necessários para as finalidades acima e pelos prazos exigidos pela legislação
        de seguros, fiscal e de defesa do consumidor. Depois disso, são eliminados ou anonimizados de forma segura.</p>'],

    ['id' => 'direitos', 'titulo' => 'Seus direitos', 'html' => '
        <p>Pela LGPD (art. 18), você pode, a qualquer momento:</p>
        <ul>
            <li>confirmar se tratamos seus dados e acessá-los;</li>
            <li>corrigir dados incompletos, inexatos ou desatualizados;</li>
            <li>pedir a anonimização, o bloqueio ou a eliminação de dados desnecessários ou tratados em desacordo com a lei;</li>
            <li>pedir a portabilidade dos dados a outro fornecedor;</li>
            <li>saber com quem compartilhamos seus dados;</li>
            <li>revogar o consentimento, quando ele for a base do tratamento.</li>
        </ul>
        <p>Para exercer qualquer desses direitos, escreva para ' . $email_dpo . '.</p>'],

    ['id' => 'seguranca', 'titulo' => 'Segurança', 'html' => '
        <p>Adotamos medidas técnicas e organizacionais para proteger os dados, entre elas: comunicação criptografada (HTTPS),
        acesso por usuário e senha com perfis de permissão, bloqueio automático após tentativas de acesso indevidas,
        registro dos acessos e separação dos dados de cada imobiliária.</p>'],

    ['id' => 'cookies', 'titulo' => 'Cookies', 'html' => '
        <p>Usamos apenas <strong>cookies estritamente necessários</strong>: para manter você conectado e para proteger os formulários
        contra envios falsificados. Não usamos cookies de rastreamento nem de publicidade.</p>'],

    ['id' => 'contato', 'titulo' => 'Encarregado e alterações', 'html' => '
        <p>O encarregado pelo tratamento de dados (DPO) da MPS pode ser contatado pelo e-mail ' . $email_dpo . '.</p>
        <p>Esta política pode ser atualizada para refletir mudanças na plataforma ou na legislação. A data da última
        atualização fica sempre no topo desta página.</p>'],
];
@endphp
@include('legal._layout', [
    'titulo'      => 'Política de Privacidade',
    'resumo'      => 'Como a MPS coleta, usa, compartilha e protege os dados pessoais tratados na plataforma MPS Cotações — e como você exerce seus direitos pela LGPD.',
    'atualizacao' => 'outubro de 2026',
    'atual'       => 'privacidade',
    'secoes'      => $secoes,
])
