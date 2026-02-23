<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div id="content">
    <div class="container mt-5">
        
        <!-- Mensagem de Bloqueio (se houver) -->
        <?php if (!empty($mensagem_bloqueio)): ?>
            <div class="alert alert-danger" role="alert">
                <h4 class="alert-heading">⚠️ Acesso Bloqueado</h4>
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
                    <p><strong>Valor:</strong> USD <?= number_format($valor_usd, 2); ?> (sete dólares americanos) por mês</p>
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

                <!-- Área do QR Code -->
                <div class="card bg-light mb-4">
                    <div class="card-body text-center">
                        <h5>💰 Pague via PIX</h5>
                        
                        <!-- p class="text-muted">Escaneie o QR Code abaixo com o app do seu banco</p-->
                        
                        <!-- ÁREA PARA INSERIR O QR CODE 
                        <div id="qrcode-area" class="my-4 p-3 bg-white border rounded d-inline-block" style="width: 320px; height: 320px;">
                            <img src="<?= base_url('assets/img/qr-pix-mydataflow.png'); ?>" alt="QR Code PIX" width="300" height="300" style="width: 300px; height: 300px; max-width: 100%; max-height: 100%; display: block; margin: 0 auto;">
                        </div>
                        -->
                        <!-- Instruções -->
                        <div class="alert alert-warning mt-3" role="alert">
                            <h6>📌 Instruções:</h6>
                            <div class="mb-2">
                                <strong>Chave pix Cpf:</strong> 032.067.407-03<br>
                                <strong>Nome:</strong> Cristiane B**** L**** do Nascimento<br>
                                <strong>Enviar o comprovante para:</strong> admin@estudotabela.com.br<br>
                                <span class="text-muted">A partir do envio estaremos liberando o acesso em até 24 horas do recebimento do comprovante.</span>
                            </div>
                            <ol class="text-start mb-0">
                                <li>Abra o aplicativo do seu banco</li>
                                <li>Selecione a opção PIX</li>
                                <li>Informe o Cpf acima</li>
                                <li>Confirme o pagamento de <strong>R$ <?= number_format($valor_brl, 2, ',', '.'); ?></strong></li>
                                <li>Após o pagamento, clique no botão "Já paguei" abaixo</li>
                            </ol>
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
