<?php
/**
 * MAPA DE TELAS — Sistema MPS Cotações
 * Gerado em: 2026-06-22
 *
 * Este arquivo é um documento de referência visual para todos os módulos e telas
 * do sistema. Não contém lógica de negócio — apenas documentação de navegação.
 *
 * Rota raiz no Nginx: /telas → mps/telas/index.php
 */

/**
 * Este documento é standalone (fora do bootstrap da aplicação), então usa
 * seu próprio loader de ícones Phosphor em vez do helper icon() do app.
 */
function telas_icon(string $name): string
{
    static $cache = [];
    if (!isset($cache[$name])) {
        $path = __DIR__ . "/../public/assets/icons/{$name}.svg";
        $cache[$name] = is_file($path) ? file_get_contents($path) : '';
    }
    return preg_replace('/<svg /', '<svg class="module-icon-svg" aria-hidden="true" ', $cache[$name], 1);
}

$modules = [

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 1 — ACESSO
    // Telas públicas, sem autenticação. Login único que redireciona por perfil.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 1,
        'nome'     => 'Acesso',
        'icone'    => 'key',
        'cor'      => '#4f46e5',
        'descricao'=> 'Autenticação, recuperação de senha e controle de sessão.',
        'acesso'   => 'público',
        'telas'    => [
            [
                'nome'   => 'Login',
                'url'    => '/login',
                'metodo' => 'GET',
                'arquivo'=> 'login.php',
                'desc'   => 'E-mail + senha. Login único: imobiliária vai para /, admin vai para /admin. reCAPTCHA v3 + bloqueio por tentativas.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Esqueceu a Senha',
                'url'    => '/esqueceu-senha',
                'metodo' => 'GET+POST',
                'arquivo'=> 'esqueceu-senha.php',
                'desc'   => 'Campo de e-mail. Envia link de recuperação com token (TTL 15 min, uso único). Throttle 60 min.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Recuperar Senha',
                'url'    => '/recuperar-senha/{token}',
                'metodo' => 'GET+POST',
                'arquivo'=> 'recuperar-senha.php',
                'desc'   => 'Valida token do link de e-mail. Exibe formulário de nova senha (mín. 10 chars). Marca token como usado somente após POST bem-sucedido.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Sessão Expirada',
                'url'    => '/sessao-expirada',
                'metodo' => 'GET',
                'arquivo'=> 'sessao-expirada.php',
                'desc'   => 'Tela informativa exibida quando o JWT expira sem renovação. Oferece botão "Fazer login novamente".',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Logout',
                'url'    => '/logout',
                'metodo' => 'POST',
                'arquivo'=> 'logout.php',
                'desc'   => 'Encerra sessão, apaga cookie JWT, soma tempo logado. Redireciona para /login.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 2 — DASHBOARD
    // Home de cada perfil. Imobiliária e Admin têm dashboards distintos.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 2,
        'nome'     => 'Dashboard',
        'icone'    => 'gauge',
        'cor'      => '#0891b2',
        'descricao'=> 'Tela inicial pós-login com resumo de atividades.',
        'acesso'   => 'autenticado',
        'telas'    => [
            [
                'nome'   => 'Dashboard Imobiliária',
                'url'    => '/',
                'metodo' => 'GET',
                'arquivo'=> 'index.php',
                'desc'   => 'Cards de resumo: cotações enviadas, em análise, aprovadas, recusadas. Últimas cotações. Notificações não lidas. Campanhas ativas.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Dashboard Admin (MPS)',
                'url'    => '/admin',
                'metodo' => 'GET',
                'arquivo'=> 'admin/index.php',
                'desc'   => 'KPIs: total de cotações, taxa de aprovação, imobiliárias ativas, tempo médio de atendimento. Gráficos por período. Fila de cotações pendentes.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 3 — COTAÇÕES (Imobiliária)
    // Criação e acompanhamento de cotações pela imobiliária.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 3,
        'nome'     => 'Cotações — Imobiliária',
        'icone'    => 'clipboard-text',
        'cor'      => '#059669',
        'descricao'=> 'Formulários de cotação e acompanhamento de status pela imobiliária.',
        'acesso'   => 'imobiliária',
        'telas'    => [
            [
                'nome'   => 'Minhas Cotações',
                'url'    => '/cotacoes',
                'metodo' => 'GET',
                'arquivo'=> 'cotacoes.php',
                'desc'   => 'Lista paginada de todas as cotações da imobiliária. Filtros por tipo (fiança/auto), status, protocolo e data. Indicador de notificações por cotação.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Detalhe da Cotação',
                'url'    => '/cotacao/{protocolo}',
                'metodo' => 'GET+POST',
                'arquivo'=> 'cotacao.php',
                'desc'   => 'Visualiza todos os dados da cotação. Exibe cotação PDF anexada pela MPS. Botões para Aprovar ou Recusar a cotação. Timeline de status.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Nova Cotação — Fiança Residencial',
                'url'    => '/cotacao/fianca/residencial',
                'metodo' => 'GET+POST',
                'arquivo'=> 'cotacao/fianca/residencial.php',
                'desc'   => 'Stepper 4 passos: (1) Dados do pretendente (CPF/CNPJ, nome, renda, vínculo empregatício); (2) Informações adicionais; (3) Endereço do imóvel (busca ViaCEP + ERP Samiloca/Vista); (4) Coberturas (plano Básico/Completo + adicionais). Suporta pré-análise (envio parcial). Salvar rascunho a qualquer momento.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Nova Cotação — Fiança Comercial',
                'url'    => '/cotacao/fianca/comercial',
                'metodo' => 'GET+POST',
                'arquivo'=> 'cotacao/fianca/comercial.php',
                'desc'   => 'Igual à residencial + campos comerciais: CNPJ da empresa, CNAE, ramo de atividade, opção tributária, franquia. Pretendentes adicionais (até 3).',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Nova Cotação — Seguro Auto',
                'url'    => '/cotacao/auto',
                'metodo' => 'GET+POST',
                'arquivo'=> 'cotacao/auto/index.php',
                'desc'   => 'Stepper 3 passos: (1) Veículo (marca/modelo/ano via FIPE, placa, tipo); (2) Dados do segurado (CPF/CNPJ, nascimento, profissão); (3) Perfil de uso (garagem, condutor habitual, KM, utilização). Salvar rascunho.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Rascunhos',
                'url'    => '/cotacao/rascunhos',
                'metodo' => 'GET',
                'arquivo'=> 'cotacao/rascunhos.php',
                'desc'   => 'Lista rascunhos salvos (fiança e auto) e cotações em pré-análise pendentes de liberação. Ações: Continuar, Excluir.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 4 — COTAÇÕES ADMIN (MPS)
    // Gestão completa das cotações recebidas pelo time da corretora.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 4,
        'nome'     => 'Cotações — Admin (MPS)',
        'icone'    => 'chart-bar',
        'cor'      => '#7c3aed',
        'descricao'=> 'Atendimento e gestão de cotações pelo time da MPS.',
        'acesso'   => 'admin',
        'telas'    => [
            [
                'nome'   => 'Lista de Cotações',
                'url'    => '/admin/cotacoes',
                'metodo' => 'GET',
                'arquivo'=> 'admin/cotacoes.php',
                'desc'   => 'Tabela unificada (fiança + auto) com paginação. Filtros: status, tipo, imobiliária, atendente, protocolo, data. Contadores por status no topo. Ordenação por colunas.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Detalhe da Cotação — Admin',
                'url'    => '/admin/cotacao/{protocolo}',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/cotacao.php',
                'desc'   => 'Dados completos da cotação. Card de status com máquina de transições. Upload de PDF/cotação. Biometria (fiança). Gerar PDF da ficha. Histórico de alterações. Transferência de responsabilidade.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Pipeline / Funil Kanban',
                'url'    => '/admin/pipeline',
                'metodo' => 'GET',
                'arquivo'=> 'admin/pipeline.php',
                'desc'   => 'Kanban com colunas: Aguardando → Em Análise → Aprovado → Recusado → Finalizado. Filtros por período e imobiliária. Cards arrastáveis (drag-and-drop).',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 5 — GESTÃO DE IMOBILIÁRIAS E USUÁRIOS (Admin)
    // CRUD de imobiliárias parceiras e usuários do sistema.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 5,
        'nome'     => 'Gestão — Imobiliárias e Usuários',
        'icone'    => 'buildings',
        'cor'      => '#d97706',
        'descricao'=> 'Cadastro e configuração de imobiliárias parceiras e usuários MPS.',
        'acesso'   => 'admin',
        'telas'    => [
            [
                'nome'   => 'Lista de Imobiliárias',
                'url'    => '/admin/imobiliarias',
                'metodo' => 'GET',
                'arquivo'=> 'admin/imobiliarias.php',
                'desc'   => 'Lista de imobiliárias parceiras com status ativo/inativo, total de cotações e comissões configuradas. Filtro por nome/CNPJ.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Criar Imobiliária',
                'url'    => '/admin/imobiliarias/criar',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/imobiliarias/criar.php',
                'desc'   => 'Formulário: CNPJ, razão social, nome fantasia, endereço, contato, logo. Configuração de permissões de produtos (fiança residencial, comercial, auto). Comissões por tipo de seguro.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Editar Imobiliária',
                'url'    => '/admin/imobiliarias/{id}/editar',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/imobiliarias/editar.php',
                'desc'   => 'Mesmos campos do criar + histórico de cotações da imobiliária. Bloquear/ativar acesso. Redefinir senhas de usuários vinculados.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Usuários da Imobiliária',
                'url'    => '/admin/imobiliarias/{id}/usuarios',
                'metodo' => 'GET',
                'arquivo'=> 'admin/imobiliarias/usuarios.php',
                'desc'   => 'Lista usuários vinculados à imobiliária. Criar usuário, ativar/inativar, redefinir senha.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Lista de Usuários MPS',
                'url'    => '/admin/usuarios',
                'metodo' => 'GET',
                'arquivo'=> 'admin/usuarios.php',
                'desc'   => 'Usuários internos da MPS (corretores/atendentes/admins). Filtro por perfil e status.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Criar Usuário MPS',
                'url'    => '/admin/usuarios/criar',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/usuarios/criar.php',
                'desc'   => 'Formulário: nome, e-mail, perfil (admin/atendente), permissões por produto, recebimento de e-mails de cotação.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Editar Usuário MPS',
                'url'    => '/admin/usuarios/{id}/editar',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/usuarios/editar.php',
                'desc'   => 'Mesmos campos do criar + ativar/inativar + redefinir senha.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 6 — RODÍZIO DE ATENDENTES (Admin)
    // Configuração do round-robin de distribuição de cotações.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 6,
        'nome'     => 'Rodízio de Atendentes',
        'icone'    => 'arrows-clockwise',
        'cor'      => '#0d9488',
        'descricao'=> 'Configuração do rodízio (round-robin) de distribuição automática de cotações.',
        'acesso'   => 'admin',
        'telas'    => [
            [
                'nome'   => 'Configurar Rodízio',
                'url'    => '/admin/rodizio',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/rodizio.php',
                'desc'   => 'Um painel por funil (fiança residencial, fiança comercial, auto). Lista de atendentes com ordem drag-and-drop. Ativar/inativar atendente no funil. Indicador de quem será o próximo.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 7 — VISITAS E RELATÓRIOS DE VISTORIA (Admin)
    // Agendamento de visitas a imóveis e registro de relatórios.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 7,
        'nome'     => 'Visitas e Vistorias',
        'icone'    => 'calendar-blank',
        'cor'      => '#be185d',
        'descricao'=> 'Agendamento de visitas a imóveis, gerenciamento de horários e relatórios de vistoria.',
        'acesso'   => 'admin',
        'telas'    => [
            [
                'nome'   => 'Lista de Visitas',
                'url'    => '/admin/visitas',
                'metodo' => 'GET',
                'arquivo'=> 'admin/visitas.php',
                'desc'   => 'Calendário + lista de visitas agendadas. Filtro por data, status e atendente. Status: Agendada, Realizada, Cancelada.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Agendar Visita',
                'url'    => '/admin/visitas/agendar',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/visitas/agendar.php',
                'desc'   => 'Formulário: imobiliária, endereço, data/horário (validado contra horários disponíveis), atendente. Integração com Google Calendar (opcional).',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Relatório de Visita',
                'url'    => '/admin/visitas/{id}/relatorio',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/visitas/relatorio.php',
                'desc'   => 'Formulário de checklist da vistoria por cômodo. Upload de fotos. Status final (aprovado/reprovado). Exportar PDF.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 8 — CHAMADOS DE SUPORTE
    // Sistema de tickets entre imobiliária e MPS.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 8,
        'nome'     => 'Chamados de Suporte',
        'icone'    => 'ticket',
        'cor'      => '#c2410c',
        'descricao'=> 'Sistema de tickets de suporte entre imobiliária e time MPS.',
        'acesso'   => 'ambos',
        'telas'    => [
            [
                'nome'   => 'Meus Chamados (Imobiliária)',
                'url'    => '/chamados',
                'metodo' => 'GET',
                'arquivo'=> 'chamados.php',
                'desc'   => 'Lista os chamados abertos pela imobiliária. Badge de não lido. Status: Aberto, Em Atendimento, Resolvido, Fechado.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Detalhe do Chamado (Imobiliária)',
                'url'    => '/chamado/{id}',
                'metodo' => 'GET+POST',
                'arquivo'=> 'chamado.php',
                'desc'   => 'Thread de mensagens. Upload de anexos. Imobiliária pode fechar o chamado.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Novo Chamado',
                'url'    => '/chamados/novo',
                'metodo' => 'GET+POST',
                'arquivo'=> 'chamados/novo.php',
                'desc'   => 'Formulário: assunto, categoria, descrição, anexo opcional.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Lista de Chamados (Admin)',
                'url'    => '/admin/chamados',
                'metodo' => 'GET',
                'arquivo'=> 'admin/chamados.php',
                'desc'   => 'Todos os chamados de todas as imobiliárias. Filtro por status, imobiliária, atendente. Atribuição de responsável.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Detalhe do Chamado (Admin)',
                'url'    => '/admin/chamado/{id}',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/chamado.php',
                'desc'   => 'Thread de mensagens. Alterar status. Atribuir atendente. Admin pode encerrar.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 9 — CAMPANHAS E COMUNICAÇÃO
    // Banners e pop-ups promocionais enviados às imobiliárias.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 9,
        'nome'     => 'Campanhas',
        'icone'    => 'megaphone',
        'cor'      => '#7c3aed',
        'descricao'=> 'Campanhas promocionais exibidas como banners e pop-ups para imobiliárias selecionadas.',
        'acesso'   => 'ambos',
        'telas'    => [
            [
                'nome'   => 'Campanhas (Imobiliária)',
                'url'    => '/campanhas',
                'metodo' => 'GET',
                'arquivo'=> 'campanhas.php',
                'desc'   => 'Lista das campanhas ativas direcionadas à imobiliária logada. Visualização de banners e detalhes.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Lista de Campanhas (Admin)',
                'url'    => '/admin/campanhas',
                'metodo' => 'GET',
                'arquivo'=> 'admin/campanhas.php',
                'desc'   => 'CRUD de campanhas. Status ativo/inativo. Período de exibição. Segmentação por imobiliária.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Criar / Editar Campanha (Admin)',
                'url'    => '/admin/campanhas/criar',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/campanhas/criar.php',
                'desc'   => 'Título, imagem/banner, data início/fim, tipo (banner ou pop-up), trigger (ao abrir fiança / ao abrir auto / geral), seleção de imobiliárias.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 10 — FAQ
    // Base de perguntas frequentes gerenciada pela MPS.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 10,
        'nome'     => 'FAQ',
        'icone'    => 'question',
        'cor'      => '#0369a1',
        'descricao'=> 'Base de perguntas frequentes.',
        'acesso'   => 'ambos',
        'telas'    => [
            [
                'nome'   => 'FAQ (Imobiliária)',
                'url'    => '/faq',
                'metodo' => 'GET',
                'arquivo'=> 'faq.php',
                'desc'   => 'Lista de perguntas e respostas organizadas por categoria. Busca por palavra-chave. Accordion.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Gerenciar FAQ (Admin)',
                'url'    => '/admin/faq',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/faq.php',
                'desc'   => 'CRUD de perguntas e categorias. Drag-and-drop para ordenar. Ativar/inativar item.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 11 — PERFIL DO USUÁRIO
    // Configurações pessoais de cada usuário logado.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 11,
        'nome'     => 'Perfil',
        'icone'    => 'user',
        'cor'      => '#475569',
        'descricao'=> 'Dados pessoais e configurações do usuário logado.',
        'acesso'   => 'ambos',
        'telas'    => [
            [
                'nome'   => 'Meu Perfil',
                'url'    => '/perfil',
                'metodo' => 'GET+POST',
                'arquivo'=> 'perfil.php',
                'desc'   => 'Nome, e-mail, telefone, foto de perfil, alteração de senha. Preferências de notificação (web push). Disponível para imobiliária e admin.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 12 — NOTIFICAÇÕES
    // Centro de notificações em tempo real.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 12,
        'nome'     => 'Notificações',
        'icone'    => 'bell',
        'cor'      => '#b45309',
        'descricao'=> 'Centro de notificações em tempo real (polling AJAX + Web Push/PWA).',
        'acesso'   => 'ambos',
        'telas'    => [
            [
                'nome'   => 'Central de Notificações',
                'url'    => '/notificacoes',
                'metodo' => 'GET',
                'arquivo'=> 'notificacoes.php',
                'desc'   => 'Lista completa de notificações com paginação. Marcar como lida. Filtrar por tipo (cotação, chamado, campanha). Disponível para imobiliária.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Central de Notificações (Admin)',
                'url'    => '/admin/notificacoes',
                'metodo' => 'GET',
                'arquivo'=> 'admin/notificacoes.php',
                'desc'   => 'Mesmo que o da imobiliária, porém filtra notificações do time MPS (novas cotações, transferências, biometrias, etc.).',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 13 — RELATÓRIOS E GERENCIADOR (Admin)
    // Métricas de negócio, comissões e exportações.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 13,
        'nome'     => 'Relatórios e Gerenciador',
        'icone'    => 'trend-up',
        'cor'      => '#166534',
        'descricao'=> 'Painel de métricas, gestão de comissões e exportações.',
        'acesso'   => 'admin',
        'telas'    => [
            [
                'nome'   => 'Gerenciador',
                'url'    => '/admin/gerenciador',
                'metodo' => 'GET',
                'arquivo'=> 'admin/gerenciador.php',
                'desc'   => 'Visão consolidada: total de cotações por período/produto/status. Ranking de imobiliárias. Desempenho de atendentes. Tempo médio de atendimento. Exportar relatório CSV/PDF.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Comissões',
                'url'    => '/admin/comissoes',
                'metodo' => 'GET+POST',
                'arquivo'=> 'admin/comissoes.php',
                'desc'   => 'Configura percentual de comissão por imobiliária e tipo de seguro (fiança residencial, comercial, auto). Histórico de comissões pagas.',
                'status' => 'a criar',
            ],
        ],
    ],

    // ─────────────────────────────────────────────────────────────────────────
    // MÓDULO 14 — ERROS E PÁGINAS DO SISTEMA
    // Telas de erro e páginas institucionais.
    // ─────────────────────────────────────────────────────────────────────────
    [
        'id'       => 14,
        'nome'     => 'Sistema e Erros',
        'icone'    => 'gear',
        'cor'      => '#6b7280',
        'descricao'=> 'Páginas de erro HTTP, políticas e termos.',
        'acesso'   => 'público',
        'telas'    => [
            [
                'nome'   => 'Erro 404 — Não Encontrado',
                'url'    => '/404',
                'metodo' => 'GET',
                'arquivo'=> 'errors/404.php',
                'desc'   => 'Página amigável para rota não encontrada. Link para voltar ao início.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Erro 403 — Acesso Negado',
                'url'    => '/403',
                'metodo' => 'GET',
                'arquivo'=> 'errors/403.php',
                'desc'   => 'Acesso proibido — permissão insuficiente.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Erro 500 — Erro interno',
                'url'    => '/500',
                'metodo' => 'GET',
                'arquivo'=> 'errors/500.php',
                'desc'   => 'Erro interno do servidor.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Política de Privacidade',
                'url'    => '/politica-de-privacidade',
                'metodo' => 'GET',
                'arquivo'=> 'politica-de-privacidade.php',
                'desc'   => 'Página estática de política de privacidade.',
                'status' => 'a criar',
            ],
            [
                'nome'   => 'Termos de Serviço',
                'url'    => '/termos-de-servico',
                'metodo' => 'GET',
                'arquivo'=> 'termos-de-servico.php',
                'desc'   => 'Página estática de termos de uso.',
                'status' => 'a criar',
            ],
        ],
    ],
];

// ─────────────────────────────────────────────────────────────────────────────
// CONTAGEM TOTAIS
// ─────────────────────────────────────────────────────────────────────────────
$total_telas = array_sum(array_map(fn($m) => count($m['telas']), $modules));
$total_modulos = count($modules);

// ─────────────────────────────────────────────────────────────────────────────
// RENDER HTML
// ─────────────────────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapa de Telas — MPS Cotações</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --bg: #0f172a;
            --surface: #1e293b;
            --surface2: #334155;
            --border: #334155;
            --text: #f1f5f9;
            --muted: #94a3b8;
            --accent: #38bdf8;
            --radius: 12px;
            --font: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        body {
            font-family: var(--font);
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            padding: 2rem 1rem;
        }

        .page-header {
            max-width: 1100px;
            margin: 0 auto 2.5rem;
            border-bottom: 1px solid var(--border);
            padding-bottom: 1.5rem;
        }

        .page-header h1 {
            font-size: 1.75rem;
            font-weight: 700;
            letter-spacing: -0.5px;
            color: var(--text);
        }

        .page-header p {
            color: var(--muted);
            margin-top: .4rem;
            font-size: .95rem;
        }

        .stats {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .stat {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .5rem 1rem;
            font-size: .85rem;
            color: var(--muted);
        }

        .stat strong { color: var(--accent); }

        .grid {
            max-width: 1100px;
            margin: 0 auto;
            display: grid;
            gap: 1.5rem;
        }

        .module {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
        }

        .module-header {
            display: flex;
            align-items: center;
            gap: .75rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--border);
            cursor: pointer;
            user-select: none;
        }

        .module-header:hover { background: var(--surface2); }

        .module-badge {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
            flex-shrink: 0;
        }

        .module-icon-svg {
            width: 18px;
            height: 18px;
            fill: currentColor;
        }

        .module-info { flex: 1; }

        .module-info h2 {
            font-size: 1rem;
            font-weight: 600;
        }

        .module-info p {
            font-size: .82rem;
            color: var(--muted);
            margin-top: 2px;
        }

        .module-meta {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-shrink: 0;
        }

        .tag {
            font-size: .72rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: .5px;
        }

        .tag-publico   { background: #1e3a5f; color: #7dd3fc; }
        .tag-imobiliaria { background: #14432a; color: #6ee7b7; }
        .tag-admin     { background: #3b1f6e; color: #c4b5fd; }
        .tag-ambos     { background: #3d2b0d; color: #fcd34d; }

        .count {
            font-size: .78rem;
            color: var(--muted);
            white-space: nowrap;
        }

        .toggle-icon {
            color: var(--muted);
            transition: transform .2s;
            font-size: .8rem;
        }

        .module-body { display: none; }
        .module.open .module-body { display: block; }
        .module.open .toggle-icon { transform: rotate(180deg); }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
        }

        thead tr {
            background: #0f172a;
        }

        thead th {
            text-align: left;
            padding: .6rem 1.25rem;
            font-size: .72rem;
            text-transform: uppercase;
            letter-spacing: .5px;
            color: var(--muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border);
            white-space: nowrap;
        }

        tbody tr {
            border-bottom: 1px solid var(--border);
            transition: background .15s;
        }

        tbody tr:last-child { border-bottom: none; }
        tbody tr:hover { background: var(--surface2); }

        td {
            padding: .8rem 1.25rem;
            vertical-align: top;
        }

        .td-name {
            font-weight: 600;
            white-space: nowrap;
            color: var(--text);
        }

        .td-url {
            font-family: 'Courier New', monospace;
            font-size: .8rem;
            color: var(--accent);
            white-space: nowrap;
        }

        .td-method {
            white-space: nowrap;
        }

        .method-badge {
            display: inline-block;
            font-size: .68rem;
            font-weight: 700;
            padding: 1px 6px;
            border-radius: 4px;
            background: #1c3d5a;
            color: #7dd3fc;
            text-transform: uppercase;
        }

        .td-arquivo {
            font-family: 'Courier New', monospace;
            font-size: .78rem;
            color: #a78bfa;
            white-space: nowrap;
        }

        .td-desc {
            color: var(--muted);
            font-size: .83rem;
            line-height: 1.45;
            max-width: 380px;
        }

        .status-badge {
            display: inline-block;
            font-size: .68rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: .3px;
            white-space: nowrap;
        }

        .status-criar   { background: #1c3526; color: #6ee7b7; }
        .status-criando { background: #1c2e52; color: #93c5fd; }
        .status-pronto  { background: #14532d; color: #86efac; }

        .module-num {
            font-size: .72rem;
            color: var(--muted);
            font-weight: 700;
            min-width: 22px;
            flex-shrink: 0;
        }
    </style>
</head>
<body>

<header class="page-header">
    <h1>🗺️ Mapa de Telas — MPS Cotações</h1>
    <p>Documento de referência de todos os módulos e telas do sistema. Clique em um módulo para expandir.</p>
    <div class="stats">
        <div class="stat"><strong><?= $total_modulos ?></strong> módulos</div>
        <div class="stat"><strong><?= $total_telas ?></strong> telas no total</div>
        <div class="stat">Stack: <strong>PHP 8.0</strong> · <strong>HTML / CSS / JS</strong> · <strong>Nginx</strong></div>
        <div class="stat">Atualizado em: <strong>2026-06-22</strong></div>
    </div>
</header>

<div class="grid">
    <?php foreach ($modules as $module): ?>
    <?php
        $tag_class = match($module['acesso']) {
            'público'      => 'tag-publico',
            'imobiliária'  => 'tag-imobiliaria',
            'admin'        => 'tag-admin',
            default        => 'tag-ambos',
        };
        $n_telas = count($module['telas']);
    ?>
    <div class="module" id="modulo-<?= $module['id'] ?>">
        <div class="module-header" onclick="this.closest('.module').classList.toggle('open')">
            <span class="module-num">M<?= $module['id'] ?></span>
            <div class="module-badge" style="background: <?= $module['cor'] ?>22; color: <?= $module['cor'] ?>">
                <?= telas_icon($module['icone']) ?>
            </div>
            <div class="module-info">
                <h2><?= htmlspecialchars($module['nome']) ?></h2>
                <p><?= htmlspecialchars($module['descricao']) ?></p>
            </div>
            <div class="module-meta">
                <span class="tag <?= $tag_class ?>"><?= $module['acesso'] ?></span>
                <span class="count"><?= $n_telas ?> tela<?= $n_telas > 1 ? 's' : '' ?></span>
                <span class="toggle-icon">▼</span>
            </div>
        </div>
        <div class="module-body">
            <table>
                <thead>
                    <tr>
                        <th>Tela</th>
                        <th>URL amigável</th>
                        <th>Método</th>
                        <th>Arquivo</th>
                        <th>Descrição</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($module['telas'] as $tela): ?>
                    <?php
                        $status_class = match($tela['status']) {
                            'a criar'  => 'status-criar',
                            'criando'  => 'status-criando',
                            'pronto'   => 'status-pronto',
                            default    => 'status-criar',
                        };
                        $status_label = match($tela['status']) {
                            'a criar'  => 'A criar',
                            'criando'  => 'Em andamento',
                            'pronto'   => 'Pronto',
                            default    => $tela['status'],
                        };
                    ?>
                    <tr>
                        <td class="td-name"><?= htmlspecialchars($tela['nome']) ?></td>
                        <td class="td-url"><?= htmlspecialchars($tela['url']) ?></td>
                        <td class="td-method">
                            <?php foreach (explode('+', $tela['metodo']) as $m): ?>
                            <span class="method-badge"><?= $m ?></span>
                            <?php endforeach; ?>
                        </td>
                        <td class="td-arquivo"><?= htmlspecialchars($tela['arquivo']) ?></td>
                        <td class="td-desc"><?= htmlspecialchars($tela['desc']) ?></td>
                        <td><span class="status-badge <?= $status_class ?>"><?= $status_label ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<script>
    // Abre o primeiro módulo por padrão
    document.querySelector('.module')?.classList.add('open');

    // Atalho: abre todos / fecha todos ao clicar com Alt
    document.addEventListener('click', function(e) {
        if (!e.altKey) return;
        const header = e.target.closest('.module-header');
        if (!header) return;
        const allOpen = [...document.querySelectorAll('.module')].every(m => m.classList.contains('open'));
        document.querySelectorAll('.module').forEach(m => {
            allOpen ? m.classList.remove('open') : m.classList.add('open');
        });
    });
</script>

</body>
</html>
