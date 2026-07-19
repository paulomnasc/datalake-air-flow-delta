<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<!-- Bibliotecas para PIX e QR Code -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
    #qrcode img, #qrcode canvas {
        width: 256px !important;
        height: 256px !important;
        display: block;
        margin: 0 auto;
    }
    #qrcode-container {
        display: inline-flex !important;
        align-items: center;
        justify-content: center;
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
                    <h4 class="alert-heading">😊 Gostou do nosso site de automação de pipelines e dos cursos de engenharia de dados? Ajude a manter o MyDataflow no ar!</h4>
                    <p><?= htmlspecialchars($mensagem_bloqueio, ENT_QUOTES, 'UTF-8'); ?></p>
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


                                <!-- Informações de Renovação -->
                                <div class="alert alert-info" role="alert">
                                        <h5 class="alert-heading">📋 Informações da Renovação</h5>
                                        <p><strong>Valor:</strong> USD <?= number_format($valor_usd, 2); ?> (dólares americanos)<?= isset($texto_periodicidade) ? $texto_periodicidade : '' ?></p>
                                        <p class="mb-1"><strong>Conversão câmbio do dia:</strong> USD <?= number_format($valor_usd, 2); ?> × BRL <?= number_format($cotacao_usd_brl, 4); ?> = <strong>R$ <?= number_format($valor_brl, 2, ',', '.'); ?></strong></p>
                                        <?php if (!empty($cotacao_mensagem)): ?>
                                                <small class="text-warning d-block">⚠️ <?= htmlspecialchars($cotacao_mensagem, ENT_QUOTES, 'UTF-8'); ?></small>
                                        <?php endif; ?>
                                </div>



                        <!-- Área Mercado Pago Checkout Pix -->
                        <div class="card my-4 border-primary shadow-sm" id="card-mp-pix">
                            <div class="card-header bg-primary text-white d-flex align-items-center justify-content-between">
                                <h5 class="mb-0">💙 Pagamento Automático via Pix (Mercado Pago)</h5>
                                <span class="badge bg-warning text-dark">Checkout Online</span>
                            </div>
                            <div class="card-body text-center">
                                <p class="text-muted">Pague pelo aplicativo do seu banco com baixa e aprovação instantânea pelo Mercado Pago.</p>

                                <div id="mp-loading" class="py-4">
                                    <div class="spinner-border text-primary" role="status">
                                        <span class="visually-hidden">Gerando Pix...</span>
                                    </div>
                                    <p class="mt-2 text-primary font-weight-bold">Conectando ao Mercado Pago e gerando QR Code Pix...</p>
                                </div>

                                <div id="mp-pix-area" style="display: none;">
                                    <!-- QR Code Mercado Pago -->
                                    <div class="my-3 text-center">
                                        <div id="mp-qrcode-box" class="p-3 bg-white border rounded d-inline-block shadow" style="min-width: 270px;">
                                            <img id="mp-qrcode-img" src="" alt="QR Code Pix Mercado Pago" style="width: 256px; height: 256px; display: none;" />
                                            <div id="mp-qrcode-fallback" class="d-flex justify-content-center align-items-center"></div>
                                        </div>
                                    </div>

                                    <!-- Status em Tempo Real -->
                                    <div class="mb-3">
                                        <span id="mp-status-badge" class="badge bg-warning text-dark p-2" style="font-size: 1rem;">
                                            ⏳ Aguardando pagamento...
                                        </span>
                                    </div>

                                    <!-- Pix Copia e Cola Mercado Pago -->
                                    <div class="mb-4 mx-auto" style="max-width: 500px;">
                                        <label for="mp-pix-copia-cola" class="form-label font-weight-bold">📋 Pix Copia e Cola (Mercado Pago)</label>
                                        <div class="input-group">
                                            <input type="text" id="mp-pix-copia-cola" class="form-control" readonly value="Carregando...">
                                            <button class="btn btn-primary" type="button" onclick="copiarPixMp()">Copiar Código</button>
                                        </div>
                                        <small class="text-muted">Copie e cole no app do seu banco para pagar.</small>
                                    </div>
                                </div>

                                <div id="mp-error-area" class="alert alert-danger mt-3" style="display: none;">
                                    <span id="mp-error-msg">Não foi possível conectar ao Mercado Pago.</span><br>
                                    <button class="btn btn-sm btn-outline-danger mt-2" onclick="carregarPixMercadoPago()">Tentar Novamente</button>
                                </div>
                            </div>
                        </div>

                        <!-- Instruções -->
                        <div class="alert alert-info mt-3 text-start" role="alert">
                            <h6>📌 Como funciona a aprovação automática?</h6>
                            <ol class="mb-0">
                                <li>Abra o aplicativo do seu banco</li>
                                <li>Escaneie o QR Code ou use a opção <strong>Pix Copia e Cola</strong></li>
                                <li>Confirme o pagamento no app do banco</li>
                                <li>O Mercado Pago identificará o pagamento em poucos segundos e liberará o seu acesso automaticamente!</li>
                            </ol>
                        </div>

                        <!-- Botão de Verificação Manual (Fallback) -->
                        <button id="btn-confirm-payment" class="btn btn-outline-success btn-lg mt-2 mb-3" onclick="verificarPagamentoManual()">
                            🔄 Já Paguei - Verificar Pagamento no Mercado Pago
                        </button>
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

<script>
let currentMpPaymentId = null;
let mpPollingInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    carregarPixMercadoPago();
});

function carregarPixMercadoPago() {
    document.getElementById('mp-loading').style.display = 'block';
    document.getElementById('mp-pix-area').style.display = 'none';
    document.getElementById('mp-error-area').style.display = 'none';

    fetch('/subscription/create-mp-pix', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        document.getElementById('mp-loading').style.display = 'none';

        if (data.status === 'success' && data.payment_id) {
            currentMpPaymentId = data.payment_id;
            document.getElementById('mp-pix-area').style.display = 'block';
            document.getElementById('mp-pix-copia-cola').value = data.qr_code || '';

            // Se o Mercado Pago forneceu a imagem base64 do QR code
            const imgEl = document.getElementById('mp-qrcode-img');
            const fallbackDiv = document.getElementById('mp-qrcode-fallback');

            if (data.qr_code_base64) {
                imgEl.src = 'data:image/png;base64,' + data.qr_code_base64;
                imgEl.style.display = 'block';
                fallbackDiv.style.display = 'none';
            } else if (data.qr_code && typeof QRCode !== 'undefined') {
                imgEl.style.display = 'none';
                fallbackDiv.style.display = 'block';
                fallbackDiv.innerHTML = '';
                new QRCode(fallbackDiv, {
                    text: data.qr_code,
                    width: 256,
                    height: 256
                });
            }

            // Inicia o polling a cada 4 segundos para checar aprovação
            iniciarPollingMercadoPago(data.payment_id);
        } else {
            document.getElementById('mp-error-area').style.display = 'block';
            document.getElementById('mp-error-msg').innerText = data.message || 'Erro ao gerar cobrança Mercado Pago.';
        }
    })
    .catch(err => {
        console.error('Erro na chamada Mercado Pago:', err);
        document.getElementById('mp-loading').style.display = 'none';
        document.getElementById('mp-error-area').style.display = 'block';
        document.getElementById('mp-error-msg').innerText = 'Falha de conexão com o servidor.';
    });
}

function copiarPixMp() {
    const input = document.getElementById("mp-pix-copia-cola");
    input.select();
    input.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(input.value).then(() => {
        alert("✅ Pix Copia e Cola do Mercado Pago copiado com sucesso!");
    }).catch(err => {
        console.error('Erro ao copiar:', err);
    });
}

function iniciarPollingMercadoPago(paymentId) {
    if (mpPollingInterval) {
        clearInterval(mpPollingInterval);
    }

    mpPollingInterval = setInterval(() => {
        if (!paymentId) return;

        fetch('/subscription/check-mp-pix/' + paymentId, {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        })
        .then(res => res.json())
        .then(data => {
            if (data.status === 'success') {
                if (data.approved) {
                    clearInterval(mpPollingInterval);
                    const badge = document.getElementById('mp-status-badge');
                    badge.className = 'badge bg-success p-2';
                    badge.innerText = '✅ Pagamento Aprovado pelo Mercado Pago!';
                    
                    setTimeout(() => {
                        alert('🎉 Pagamento aprovado com sucesso no Mercado Pago! Seu acesso foi liberado.');
                        window.location.href = '/dashboard';
                    }, 1000);
                } else if (data.status_mp === 'rejected' || data.status_mp === 'cancelled') {
                    clearInterval(mpPollingInterval);
                    const badge = document.getElementById('mp-status-badge');
                    badge.className = 'badge bg-danger p-2';
                    badge.innerText = '❌ Pagamento ' + (data.status_mp === 'rejected' ? 'rejeitado' : 'cancelado');
                }
            }
        })
        .catch(err => console.error('Erro no polling Mercado Pago:', err));
    }, 4000);
}

function verificarPagamentoManual() {
    if (!currentMpPaymentId) {
        carregarPixMercadoPago();
        return;
    }

    const btn = document.getElementById('btn-confirm-payment');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Verificando no Mercado Pago...';

    fetch('/subscription/check-mp-pix/' + currentMpPaymentId, {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        btn.disabled = false;
        btn.innerHTML = '🔄 Já Paguei - Verificar Pagamento no Mercado Pago';

        if (data.status === 'success' && data.approved) {
            alert('🎉 Pagamento confirmado e aprovado! Seu acesso foi renovado.');
            window.location.href = '/dashboard';
        } else {
            alert('ℹ️ O pagamento ainda está pendente de confirmação pelo Mercado Pago (Status: ' + (data.status_mp || 'pendente') + '). Por favor, conclua a transferência no app do seu banco.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '🔄 Já Paguei - Verificar Pagamento no Mercado Pago';
        alert('❌ Erro ao consultar Mercado Pago. Tente novamente em instantes.');
    });
}
</script>

<?php
require VIEWPATH . '/footer.php';
?>
