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

<div id="content" style="font-family: 'Outfit', sans-serif; background: #0e1620; min-height: 100vh; padding: 40px 15px; color: #f3f4f6;">
    <div class="container" style="max-width: 600px;">
        
        <div class="text-center mb-4">
            <h1 style="font-weight: 800; color: #ffffff; margin-bottom: 8px;">🚀 Créditos Grok AI</h1>
            <p class="text-muted" style="font-size: 1.1rem;">Recarregue seu saldo para desbloquear o assistente e estatísticas das ligas principais.</p>
        </div>

        <div class="card shadow-lg" style="background: #172230; border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px;">
            <div class="card-header text-center" style="background: #f47c20; border-top-left-radius: 15px; border-top-right-radius: 15px; padding: 20px;">
                <h3 class="mb-0 text-white" style="font-weight: 700;">💳 Recarga de Créditos</h3>
            </div>
            <div class="card-body" style="padding: 30px;">
                
                <?php if ($needs_google_login): ?>
                    <!-- Google Login Prompt -->
                    <div class="text-center py-4">
                        <i class="bi bi-shield-lock-fill" style="font-size: 3.5rem; color: #f47c20; margin-bottom: 20px; display: block;"></i>
                        <h4 style="font-weight: 700; color: #ffffff; margin-bottom: 12px;">Login com Google Requerido</h4>
                        <p class="text-muted" style="font-size: 0.95rem; line-height: 1.6; margin-bottom: 25px; padding: 0 10px;">
                            Para comprar créditos ou usar o sistema de cotas, você deve se cadastrar com seu login social do Google.
                        </p>
                        
                        <a href="/auth/google-login" class="btn btn-primary btn-lg w-100" style="background: #4285f4; border-color: #4285f4; font-weight: 700; padding: 12px; display: inline-flex; align-items: center; justify-content: center; gap: 10px; border-radius: 12px; color: #ffffff;">
                            <i class="bi bi-google" style="font-size: 1.2rem;"></i> Entrar com o Google
                        </a>
                        
                        <a href="/football-trends" class="btn btn-link mt-3 text-muted" style="text-decoration: none; font-size: 0.9rem;"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
                    </div>
                <?php else: ?>
                    <!-- Benefícios -->
                    <div class="p-3 mb-4 rounded text-center" style="background: rgba(244, 124, 32, 0.08); border: 1px solid rgba(244, 124, 32, 0.2);">
                        <h5 style="color: #f47c20; font-weight: 700; margin-bottom: 12px;">O que está incluído nesta recarga?</h5>
                        <ul class="text-start mb-0" style="display: inline-block; font-size: 0.95rem; line-height: 1.6; color: #e5e7eb;">
                            <li>🔥 <strong>20 novas consultas</strong> ao assistente inteligente Grok AI.</li>
                            <li>⚽ <strong>Acesso total às estatísticas completas</strong> das ligas principais (Brasileirão Séries A, B e C, Champions League e ligas europeias).</li>
                            <li>⚡ Ativação imediata após a confirmação do pagamento.</li>
                        </ul>
                    </div>

                    <div class="text-center mb-4">
                        <h5 style="color: #8a99a8; margin-bottom: 4px;">Valor do Depósito Mínimo:</h5>
                        <h2 style="font-weight: 800; color: #ffffff;">R$ 10,00</h2>
                        <span class="badge bg-success" style="font-size: 0.85rem; padding: 6px 12px;">💰 Custo: R$ 0,50 por consulta</span>
                    </div>

                    <hr style="border-top: 1px solid rgba(255, 255, 255, 0.1);">

                    <!-- Área do QR Code Dinâmico -->
                    <div class="my-4 text-center">
                        <div id="qrcode-container" class="p-3 bg-white border rounded d-inline-block shadow" style="min-width: 280px; min-height: 280px;">
                            <div id="qrcode" class="d-flex justify-content-center align-items-center"></div>
                        </div>
                    </div>

                    <!-- Pix Copia e Cola -->
                    <div class="mb-4">
                        <label for="pix-copia-e-cola" class="form-label" style="font-weight: 700; color: #ffffff; display: block; margin-bottom: 8px;">📋 Pix Copia e Cola</label>
                        <div class="input-group">
                            <input type="text" id="pix-copia-e-cola" class="form-control" readonly value="Gerando código..." style="background: #0f1620; border: 1px solid rgba(255,255,255,0.1); color: #f3f4f6; font-size: 0.9rem;">
                            <button class="btn btn-primary" type="button" onclick="copiarPix()" style="background: #f47c20; border-color: #f47c20;">Copiar</button>
                        </div>
                        <small class="text-muted" style="margin-top: 4px; display: block;">Copie este código para pagar no aplicativo do seu celular (Pix Copia e Cola).</small>
                    </div>

                    <!-- Instruções -->
                    <div class="alert text-start" role="alert" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); color: #f3f4f6;">
                        <h6 style="font-weight: 700; color: #ffffff; margin-bottom: 10px;">📌 Instruções de Pagamento:</h6>
                        <ol class="mb-0" style="padding-left: 20px; font-size: 0.9rem; line-height: 1.6;">
                            <li>Abra o aplicativo de pagamentos do seu banco.</li>
                            <li>Escolha a opção de pagar via <strong>PIX</strong> (por QR Code ou Copia e Cola).</li>
                            <li>Valide o valor exato de <strong>R$ 10,00</strong>.</li>
                            <li>Após finalizar, clique no botão de confirmação abaixo.</li>
                        </ol>
                        <div class="mt-3 small border-top pt-2" style="border-color: rgba(255,255,255,0.1) !important; color: #8a99a8;">
                            <strong>Dados da conta recebedora:</strong><br>
                            Chave CPF: 032.067.407-03<br>
                            Nome: Cristiane B. L. do Nascimento<br>
                            Suporte: admin@estudotabela.com.br
                        </div>
                    </div>

                    <!-- Botão de Confirmação de Pagamento -->
                    <div class="text-center mt-4">
                        <button id="btn-confirm-payment" class="btn btn-success btn-lg w-100" onclick="confirmarPagamento()" style="background: #10b981; border-color: #10b981; font-weight: 700; padding: 12px;">
                            ✅ Já Realizei o PIX - Confirmar Recarga
                        </button>
                        <a href="/football-trends" class="btn btn-link mt-3 text-muted" style="text-decoration: none; font-size: 0.9rem;"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    gerarPix();
    // Dispara evento GA4 de checkout para compra de créditos
    if (typeof gtag === 'function') {
        gtag('event', 'begin_checkout', {
            'value': 10.00,
            'currency': 'BRL',
            'items': [{
                'item_id': 'grok_credits_20',
                'item_name': '20 Créditos Grok AI',
                'price': 10.00,
                'quantity': 1
            }]
        });
    }
});

function gerarPix() {
    try {
        const payload = '<?= $pix_payload ?? '' ?>';
        if (!payload) return;
        const qrcodeDiv = document.getElementById("qrcode");
        if (!qrcodeDiv) return;
        qrcodeDiv.innerHTML = "";
        
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

        document.getElementById("pix-copia-e-cola").value = payload;
    } catch (e) {
        console.error("Erro ao gerar PIX:", e);
        document.getElementById("pix-copia-e-cola").value = "Erro: " + e.message;
    }
}

function copiarPix() {
    const input = document.getElementById("pix-copia-e-cola");
    input.select();
    input.setSelectionRange(0, 99999);
    
    navigator.clipboard.writeText(input.value).then(() => {
        alert("✅ Código Pix copiado com sucesso!");
    }).catch(err => {
        console.error('Erro ao copiar:', err);
    });
}

function confirmarPagamento() {
    if (!confirm('Você confirma que realizou o Pix de R$ 10,00? Os créditos serão creditados em sua conta.')) {
        return;
    }

    const btn = document.getElementById('btn-confirm-payment');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Processando...';

    fetch('/subscription/confirmGrokPayment', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            // Dispara evento GA4 de compra com sucesso
            if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
                    'transaction_id': 'grok_' + Date.now(),
                    'value': 10.00,
                    'currency': 'BRL',
                    'items': [{
                        'item_id': 'grok_credits_20',
                        'item_name': '20 Créditos Grok AI',
                        'price': 10.00,
                        'quantity': 1
                    }]
                });
            }

            alert('✅ Sucesso!\nSeus 20 créditos foram adicionados à sua conta. Agora você possui ' + data.novo_saldo + ' consultas ao Grok AI e acesso às estatísticas liberados.');
            window.location.href = '/football-trends';
        } else {
            alert('❌ Erro: ' + data.message);
            btn.disabled = false;
            btn.innerHTML = '✅ Já Realizei o PIX - Confirmar Recarga';
        }
    })
    .catch(error => {
        console.error('Erro:', error);
        alert('❌ Erro ao confirmar recarga. Tente novamente.');
        btn.disabled = false;
        btn.innerHTML = '✅ Já Realizei o PIX - Confirmar Recarga';
    });
}
</script>

<?php
require VIEWPATH . '/footer.php';
?>
