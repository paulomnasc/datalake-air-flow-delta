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
        <?php if (!empty($mensagem_bloqueio)): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">😊 Gostou do nosso site de automação de pipelines e dos cursos de engenharia de dados? Ajude a manter o MyDataflow no ar!</h4>
                <p><?= htmlspecialchars($mensagem_bloqueio, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>
        <?php endif; ?>

        <!-- Card Principal -->
        <div class="card shadow-lg">
            <div class="card-header bg-primary text-white">
                <h2 class="mb-0">💳 Renovação de Assinatura</h2>
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
                    <!-- p><strong>Como Founder Member, este valor está travado para você!</strong></p>
                    <p class="mb-0">
                        <strong>Ao renovar agora:</strong> Sua assinatura será válida até 
                        <strong><?= $proximo_vencimento_formatado ?></strong>
                    </p>
                </div>

                        <!-- Área do QR Code Dinâmico -->
                        <div class="my-4 text-center">
                            <div id="qrcode-container" class="p-4 bg-white border rounded d-inline-block shadow" style="min-width: 290px; min-height: 290px;">
                                <div id="qrcode" class="d-flex justify-content-center align-items-center"></div>
                            </div>
                        </div>

                        <!-- Pix Copia e Cola -->
                        <div class="mb-4 mx-auto" style="max-width: 500px;">
                            <label for="pix-copia-e-cola" class="form-label font-weight-bold">📋 Pix Copia e Cola</label>
                            <div class="input-group">
                                <input type="text" id="pix-copia-e-cola" class="form-control" readonly value="Gerando código...">
                                <button class="btn btn-outline-primary" type="button" onclick="copiarPix()">Copiar</button>
                            </div>
                            <small class="text-muted">Use esta opção se estiver acessando pelo celular.</small>
                        </div>

                        <!-- Instruções -->
                        <div class="alert alert-warning mt-3 text-start" role="alert">
                            <h6>📌 Instruções:</h6>
                            <ol class="mb-0">
                                <li>Abra o aplicativo do seu banco</li>
                                <li>Selecione a opção <strong>PIX</strong></li>
                                <li>Escolha <strong>Ler QR Code</strong> ou <strong>Pix Copia e Cola</strong></li>
                                <li>Confirme o pagamento de <strong>R$ <?= number_format($valor_brl, 2, ',', '.'); ?></strong></li>
                                <li>Após o pagamento, clique no botão "Já paguei" abaixo</li>
                            </ol>
                            <div class="mt-2 small border-top pt-2">
                                <strong>Dados para conferência:</strong><br>
                                Chave CPF: 032.067.407-03<br>
                                Nome: Cristiane B. L. do Nascimento<br>
                                Enviar comprovante: <strong>admin@estudotabela.com.br</strong>
                            </div>
                        </div>

                        <!-- Botão de Confirmação de Pagamento -->
                        <button id="btn-confirm-payment" class="btn btn-success btn-lg mt-3" onclick="confirmarPagamento()">
                            ✅ Já Paguei - Confirmar Pagamento
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
document.addEventListener('DOMContentLoaded', function() {
    gerarPix();
});

function gerarPix() {
    try {
        // Usa o payload pré-gerado pelo backend para maior confiabilidade
        const payload = '<?= $pix_payload ?? '' ?>';
        
        if (!payload) {
            throw new Error("Payload do PIX não foi fornecido pelo servidor.");
        }

        // 1. Gera o QR Code
        const qrcodeDiv = document.getElementById("qrcode");
        qrcodeDiv.innerHTML = ""; // Limpa antes de gerar
        
        if (typeof QRCode === 'undefined') {
            throw new Error("Biblioteca de QR Code não carregada.");
        }

        new QRCode(qrcodeDiv, {
            text: payload,
            width: 256,
            height: 256,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

        // 2. Preenche o campo Copia e Cola
        document.getElementById("pix-copia-e-cola").value = payload;
    } catch (e) {
        console.error("Erro detalhado ao gerar PIX:", e);
        const errorMsg = e.message || "Erro desconhecido";
        document.getElementById("pix-copia-e-cola").value = "Erro: " + errorMsg;
    }
}

function copiarPix() {
    const input = document.getElementById("pix-copia-e-cola");
    input.select();
    input.setSelectionRange(0, 99999); // Para dispositivos móveis
    
    navigator.clipboard.writeText(input.value).then(() => {
        alert("✅ Código Pix copiado com sucesso!");
    }).catch(err => {
        console.error('Erro ao copiar:', err);
    });
}

function confirmarPagamento() {
    // Confirma com o usuário
    if (!confirm('Você confirma que realizou o pagamento ?')) {
        return;
    }

    // Desabilita o botão
    const btn = document.getElementById('btn-confirm-payment');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processando...';

    // Faz a requisição para confirmar o pagamento
    fetch('<?= base_url('subscription/confirmPayment') ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Exibe mensagem de sucesso
            //alert('✅ ' + data.message + '\nNovo vencimento: ' + data.novo_vencimento);
            
            alert('✅ ' + '\nAssim que confirmarmos o pagamento, seu acesso será liberado');
                    

            // Recarrega a página
            window.location.reload();
        } else {
            // Exibe mensagem de erro
            alert('❌ ' + data.message);
            
            // Reabilita o botão
            btn.disabled = false;
            btn.innerHTML = '✅ Já Paguei - Confirmar Pagamento';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro ao confirmar pagamento. Tente novamente.');
        
        // Reabilita o botão
        btn.disabled = false;
        btn.innerHTML = '✅ Já Paguei - Confirmar Pagamento';
    });
}
</script>

<?php
require VIEWPATH . '/footer.php';
?>
