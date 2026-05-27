<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<style>
    .btn-gradient {
        background: linear-gradient(135deg, #f04e23 0%, #ff8a00 100%);
        border: none !important;
        color: white !important;
        box-shadow: 0 4px 15px rgba(240, 78, 35, 0.4);
        transition: all 0.3s ease-in-out;
        text-decoration: none;
        display: inline-block;
    }
    .btn-gradient:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(240, 78, 35, 0.6);
        background: linear-gradient(135deg, #d83c12 0%, #e67300 100%);
        color: white !important;
    }
    .iframe-wrapper {
        border: 1px solid #e2e8f0;
        border-radius: 12px;
        box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        overflow: hidden;
        height: 750px;
        max-height: 85vh;
        background: #ffffff;
    }
</style>


<div id="content">
    <div class="container mt-5">
        
        <!-- Mensagem de Bloqueio (se houver) -->
        
            <!-- Frase de incentivo no topo -->
            <div class="text-center mb-3">
                <strong style="font-size:1.3em;">Você está prestes a avançar para próxima etapa...</strong>
            </div>
            <!-- Banner do Curso: Como Criar um Data Lake do Zero (Passo a Passo) com Apache Airflow -->
            <div class="text-center my-4">
                <img src="<?= base_url('assets/img/DatalakeDoZero.png'); ?>" alt="Como Criar um Data Lake do Zero (Passo a Passo) com Apache Airflow" style="width: 420px; max-width: 98vw; height: auto; border-radius: 12px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            </div>
            <?php if (!empty($mensagem_bloqueio)): ?>
                <div class="alert alert-danger" role="alert">
                    <h4 class="alert-heading">😊 Gostou da amostra da nossa videoaula de automação do curso de engenharia de dados? </h4>
                    <!--p><!--?= htmlspecialchars($mensagem_bloqueio, ENT_QUOTES, 'UTF-8'); ?></p-->
                </div>
            <?php endif; ?>

                <!-- Ementa do Curso -->
                <div class="card my-4">
                    <div class="card-body">
                        <h3 class="mb-3" style="color:#667eea;">Ementa do Curso</h3>
                        <div style="background: #e0f7fa; color: #00796b; border-radius: 10px; padding: 20px; margin: 20px auto; max-width: 500px; font-size: 18px; font-weight: bold;">
                            Domine a arquitetura de dados moderna aprendendo a construir Data Lakes escaláveis do absoluto zero. Este módulo combina fundamentos teóricos do ecossistema de dados com laboratórios práticos para configurar infraestrutura e camadas Silver com qualidade automatizada. Ideal para quem deseja sair da teoria e resolver problemas reais de ingestão e processamento. Elimine a fricção entre aprender e executar com nosso método assistido passo a passo.
                        </div>
                        <div style="margin: 30px auto; max-width: 700px; text-align: left; font-size: 16px; color: #333;">
                            <b>1. Fundamentos e Visão Arquitetural</b> <span style="color:#888">mod-fund</span><br>
                            Foco: Fundamentos, fluxo de informação e transformação de dados em ativo estratégico.<br>
                            ⏱️ 2 horas estimadas<br>
                            🎬 4 vídeos<br><br>
                            <b>2. Infraestrutura e Setup do Ambiente</b> <span style="color:#888">mod-infra</span><br>
                            Criação de buckets usando MinIO (tecnologia AWS compatível) em ambiente local. Implementação prática via Docker Compose e uso do repositório mini-datalake-stack.<br>
                            ⏱️ 3 horas estimadas<br>
                            🎬 3 vídeos<br><br>
                            <b>3. Orquestração e Fluxo de Trabalho (Workflows)</b> <span style="color:#888">mod-fluxo</span><br>
                            Funcionamento interno do Airflow, console web e integração entre componentes. Execução de pipeline ELT real e uso do ambiente de laboratório para testes de fluxos.<br>
                            ⏱️ 5 horas estimadas<br>
                            🎬 2 vídeos<br><br>
                            <b>4. Engenharia Avançada e Qualidade</b> <span style="color:#888">mod-eng-mais</span><br>
                            Criação de classes customizadas de validação para a arquitetura medalhão (Camada Silver). Conectando o Power BI Desktop no Delta Lake.<br>
                            ⏱️ 5 horas estimadas<br>
                            🎬 2 vídeos
                        </div>
                    </div>
                </div>

                <!-- Card Principal -->
                <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0">💳 Contribuição para uso por 30 dias corridos</h2>
            </div>
            <div class="card-body">
                
                <!-- Informações do Usuário -->
                <div class="mb-4">
                    <h5>Olá, <?= htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>! 👋</h5>
                    <p class="text-muted">Email: <?= htmlspecialchars($usuario_email, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>

                <hr>

                <!-- Status da Assinatura -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card border-<?= ($status_assinatura === 'expired') ? 'danger' : (($status_assinatura === 'trial') ? 'warning' : 'success') ?>">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Status da Assinatura</h6>
                                <h4 class="card-title">
                                    <?php
                                    $badges = [
                                        'trial' => '<span class="badge bg-warning text-dark">🆓 Período de Teste</span>',
                                        'active' => '<span class="badge bg-success">✅ Ativa</span>',
                                        'expired' => '<span class="badge bg-danger">❌ Expirada</span>',
                                        'cancelled' => '<span class="badge bg-secondary">🚫 Cancelada</span>'
                                    ];
                                    echo $badges[$status_assinatura] ?? '<span class="badge bg-secondary">Desconhecido</span>';
                                    ?>
                                </h4>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card border-<?= ($dias_restantes <= 2) ? 'danger' : (($dias_restantes <= 7) ? 'warning' : 'info') ?>">
                            <div class="card-body">
                                <h6 class="card-subtitle mb-2 text-muted">Data de Vencimento</h6>
                                <h4 class="card-title"><?= $data_vencimento_formatada ?></h4>
                                <p class="mb-0">
                                    <?php if ($dias_restantes >= 0): ?>
                                        <strong><?= $dias_restantes ?></strong> dia(s) restante(s)
                                    <?php else: ?>
                                        <span class="text-danger">Venceu há <strong><?= abs($dias_restantes) ?></strong> dia(s)</span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                    </div>
                </div>


                                



                        <!-- Hotmart Checkout -->
                        <div class="my-4 text-center">
                            <h4 class="mb-3" style="color: #f04e23; font-weight: bold;">💳 Finalizar Pagamento com Hotmart</h4>
                            <p class="text-muted mb-4">Selecione o botão abaixo para abrir a página de pagamento em uma nova janela ou utilize a janela integrada logo a seguir:</p>
                            
                            <div class="mb-4">
                                <a href="https://go.hotmart.com/G105919559X?dp=1" target="_blank" class="btn btn-gradient btn-lg px-5 py-3 shadow-lg rounded-pill font-weight-bold">
                                    🚀 Ir para o Checkout da Hotmart
                                </a>
                            </div>

                        </div>
                    </div>
                </div>

                <!-- Histórico de Pagamentos -->
                <?php if (!empty($data_ultimo_pagamento)): ?>
                <div class="card">
                    <div class="card-body">
                        <h6 class="card-subtitle mb-2 text-muted">📅 Último Pagamento</h6>
                        <p class="mb-0">
                            <?php
                            try {
                                $ultimoPgto = new DateTime($data_ultimo_pagamento);
                                echo $ultimoPgto->format('d/m/Y');
                            } catch (Exception $e) {
                                echo 'Data inválida';
                            }
                            ?>
                        </p>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Informações Adicionais -->
        <div class="card mt-4">
            <div class="card-body">
                <h5>💡 Precisa de Ajuda?</h5>
                <p>Se você teve algum problema com o pagamento ou precisa de suporte, entre em contato conosco:</p>
                <a href="<?= base_url('contactUs') ?>" class="btn btn-outline-primary">📧 Entrar em Contato</a>
            </div>
        </div>

    </div>
</div>

<!-- Script do PIX removido por não ser mais utilizado -->

<?php
require VIEWPATH . '/footer.php';
?>
