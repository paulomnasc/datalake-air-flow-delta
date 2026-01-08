<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div id="content">
    <div class="container mt-5">
        <div class="card shadow-lg">
            <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                <h2 class="mb-0">💳 Pagamento via PIX</h2>
                <span class="badge bg-light text-success">Plano USD 7,00</span>
            </div>
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-6 mb-4 mb-md-0">
                        <h5>Olá, <?= htmlspecialchars($usuario_nome, ENT_QUOTES, 'UTF-8'); ?>! 👋</h5>
                        <p class="text-muted mb-1">Email: <?= htmlspecialchars($usuario_email, ENT_QUOTES, 'UTF-8'); ?></p>
                        <p class="mb-3">Valor original: <strong>USD <?= number_format($valor_usd, 2); ?></strong></p>

                        <div class="alert alert-info">
                            <p class="mb-1"><strong>Cotação USD → BRL:</strong> <?= number_format($cotacao_usd_brl, 4); ?></p>
                            <p class="mb-0">Valor a pagar: <strong>R$ <?= number_format($valor_brl, 2, ',', '.'); ?></strong></p>
                            <?php if (!empty($cotacao_mensagem)): ?>
                                <small class="text-warning d-block mt-2">⚠️ <?= htmlspecialchars($cotacao_mensagem, ENT_QUOTES, 'UTF-8'); ?></small>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <h6 class="mb-1">Chave PIX (CPF)</h6>
                            <code><?= htmlspecialchars($pix_key, ENT_QUOTES, 'UTF-8'); ?></code>
                        </div>

                        <button class="btn btn-success" onclick="confirmarPagamento()">✅ Já paguei</button>
                        <a class="btn btn-link" href="<?= base_url('subscription'); ?>">Voltar</a>
                    </div>

                    <div class="col-md-6 text-center">
                        <p class="text-muted mb-2">Escaneie o QR Code no app do seu banco</p>
                        <div class="p-3 bg-white border rounded d-inline-block">
                            <img src="<?= htmlspecialchars($qr_code_url, ENT_QUOTES, 'UTF-8'); ?>" alt="QR Code PIX" width="300" height="300">
                        </div>
                        <div class="mt-3">
                            <small class="text-muted">Ou copie o código PIX abaixo:</small>
                            <textarea class="form-control mt-2" rows="3" readonly onclick="this.select();"><?= htmlspecialchars($pix_payload, ENT_QUOTES, 'UTF-8'); ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function confirmarPagamento() {
    if (!confirm('Você confirma que realizou o pagamento via PIX?')) {
        return;
    }

    fetch('<?= base_url('subscription/confirmPayment'); ?>', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(data => {
        if (data.status === 'success') {
            alert('✅ ' + data.message + '\nNovo vencimento: ' + data.novo_vencimento);
            window.location.href = '<?= base_url('subscription'); ?>';
        } else {
            alert('❌ ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('❌ Erro ao confirmar pagamento.');
    });
}
</script>

<?php
require VIEWPATH . '/footer.php';
?>
