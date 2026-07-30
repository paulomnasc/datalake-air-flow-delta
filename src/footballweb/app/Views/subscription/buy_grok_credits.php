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
                    <!-- Benefícios e Transparência -->
                    <div class="p-3 mb-4 rounded text-center" style="background: rgba(244, 124, 32, 0.08); border: 1px solid rgba(244, 124, 32, 0.2);">
                        <h5 style="color: #f47c20; font-weight: 700; margin-bottom: 12px;">O que está incluído nesta recarga?</h5>
                        <ul class="text-start mb-0" style="display: inline-block; font-size: 0.95rem; line-height: 1.6; color: #e5e7eb;">
                            <li>🔥 <strong>20 novas consultas</strong> ao assistente inteligente Grok AI.</li>
                            <li>⚽ <strong>Acesso total às estatísticas completas</strong> das ligas principais (Brasileirão Séries A, B e C, Champions League e ligas europeias).</li>
                            <li>⚡ <strong>Ativação instantânea:</strong> Créditos liberados em segundos via Webhook Pix.</li>
                            <li>💎 <strong>Zero fidelidade:</strong> Sem mensalidade oculta, pague somente conforme a sua necessidade.</li>
                        </ul>
                    </div>

                    <!-- Selos de Segurança e Confiança -->
                    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4 text-center p-2 rounded" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; color: #9ca3af;">
                        <span class="d-flex align-items-center gap-1"><i class="bi bi-shield-check text-success" style="font-size: 1.1rem;"></i> Checkout Seguro Mercado Pago</span>
                        <span class="d-flex align-items-center gap-1"><i class="bi bi-lock-fill text-info" style="font-size: 1rem;"></i> Encriptação SSL 256-bit</span>
                        <span class="d-flex align-items-center gap-1"><i class="bi bi-lightning-charge-fill text-warning" style="font-size: 1rem;"></i> Ativação Automática 24/7</span>
                    </div>

                    <div class="text-center mb-4">
                        <h5 style="color: #8a99a8; margin-bottom: 4px;">Valor do Depósito Mínimo:</h5>
                        <h2 style="font-weight: 800; color: #ffffff;">R$ 10,00</h2>
                        <span class="badge bg-success" style="font-size: 0.85rem; padding: 6px 12px;">💰 Apenas R$ 0,50 por consulta</span>
                    </div>

                    <hr style="border-top: 1px solid rgba(255, 255, 255, 0.1);">

                    <!-- Área Mercado Pago Checkout Pix -->
                    <div class="text-center" id="mp-grok-area">
                        <div id="mp-loading" class="py-4">
                            <div class="spinner-border" style="color: #f47c20;" role="status">
                                <span class="visually-hidden">Gerando Pix...</span>
                            </div>
                            <p class="mt-2 font-weight-bold" style="color: #f47c20;">Conectando ao Mercado Pago e gerando QR Code Pix...</p>
                        </div>

                        <div id="mp-pix-content" style="display: none;">
                            <!-- QR Code Mercado Pago -->
                            <div class="my-3 text-center">
                                <div id="mp-qrcode-box" class="p-3 bg-white border rounded d-inline-block shadow" style="min-width: 270px;">
                                    <img id="mp-qrcode-img" src="" alt="QR Code Pix Mercado Pago" style="width: 256px; height: 256px; display: none;" />
                                    <div id="mp-qrcode-fallback" class="d-flex justify-content-center align-items-center"></div>
                                </div>
                            </div>

                            <!-- Status em Tempo Real -->
                            <div class="mb-3">
                                <span id="mp-status-badge" class="badge bg-warning text-dark p-2" style="font-size: 0.95rem;">
                                    ⏳ Aguardando pagamento do Pix...
                                </span>
                            </div>

                            <!-- Pix Copia e Cola -->
                            <div class="mb-4">
                                <label for="mp-pix-copia-cola" class="form-label" style="font-weight: 700; color: #ffffff; display: block; margin-bottom: 8px;">📋 Pix Copia e Cola (Mercado Pago)</label>
                                <div class="input-group">
                                    <input type="text" id="mp-pix-copia-cola" class="form-control" readonly value="Carregando..." style="background: #0f1620; border: 1px solid rgba(255,255,255,0.1); color: #f3f4f6; font-size: 0.9rem;">
                                    <button class="btn btn-primary" type="button" onclick="copiarPixMp()" style="background: #f47c20; border-color: #f47c20; font-weight: 700;">Copiar Código</button>
                                </div>
                                <small class="text-muted" style="margin-top: 4px; display: block;">Copie este código para pagar no aplicativo do seu celular.</small>
                            </div>

                            <?php if (strtolower(ENVIRONMENT) !== 'production'): ?>
                            <div class="mt-4 p-3 border border-warning rounded text-center mx-auto" style="background: rgba(255, 193, 7, 0.1); border-color: #ffc107 !important; max-width: 500px;">
                                <div class="text-warning font-weight-bold mb-1" style="font-weight: 700; color: #ffc107;">⚡ Modo Desenvolvedor (Sandbox)</div>
                                <small class="text-white-50 d-block mb-2">Simule a aprovação do Mercado Pago via API sem usar o aplicativo mobile:</small>
                                <button type="button" id="btn-simulate-mp" class="btn btn-warning text-dark font-weight-bold" onclick="simularPagamentoDev()" style="font-weight: 700;">
                                    ⚡ Simular Pagamento Aprovado no Sandbox
                                </button>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div id="mp-error-area" class="alert alert-danger mt-3" style="display: none; background: rgba(220, 53, 69, 0.2); border-color: #dc3545; color: #f8d7da;">
                            <span id="mp-error-msg">Não foi possível conectar ao Mercado Pago.</span><br>
                            <button class="btn btn-sm btn-outline-light mt-2" onclick="carregarPixMercadoPago()">Tentar Novamente</button>
                        </div>
                    </div>

                    <!-- Botão de Suporte via Chat Tawk.to -->
                    <div class="p-3 my-3 rounded text-center" style="background: rgba(16, 185, 129, 0.08); border: 1px dashed rgba(16, 185, 129, 0.3);">
                        <div class="d-flex align-items-center justify-content-center gap-2 mb-2">
                            <i class="bi bi-chat-dots-fill text-success" style="font-size: 1.3rem;"></i>
                            <span style="font-weight: 700; color: #10b981; font-size: 0.95rem;">Precisa de ajuda ou ficou com dúvidas na compra?</span>
                        </div>
                        <p class="text-muted mb-2" style="font-size: 0.85rem;">Nosso suporte ao vivo pode te auxiliar a concluir seu pagamento agora mesmo.</p>
                        <button type="button" class="btn btn-sm btn-outline-success font-weight-bold px-3 py-1" onclick="if (typeof Tawk_API !== 'undefined') { Tawk_API.maximize(); } else { alert('O chat ao vivo está carregando, por favor aguarde alguns segundos.'); }" style="border-radius: 20px; font-weight: 700;">
                            💬 Abrir Chat ao Vivo Tawk.to
                        </button>
                    </div>

                    <!-- Instruções -->
                    <div class="alert text-start mt-3" role="alert" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); color: #f3f4f6;">
                        <h6 style="font-weight: 700; color: #ffffff; margin-bottom: 10px;">📌 Como funciona a recarga automática?</h6>
                        <ol class="mb-0" style="padding-left: 20px; font-size: 0.9rem; line-height: 1.6;">
                            <li>Abra o aplicativo de pagamentos do seu banco.</li>
                            <li>Escolha a opção de pagar via <strong>PIX</strong> (por QR Code ou Copia e Cola).</li>
                            <li>Confirme o pagamento de <strong>R$ 10,00</strong> no app do banco.</li>
                            <li>O Mercado Pago identificará o pagamento em poucos segundos e adicionará <strong>20 créditos</strong> à sua conta automaticamente!</li>
                        </ol>
                    </div>

                    <!-- Prova Social / Depoimentos de Usuários -->
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-25">
                        <h6 class="text-center mb-3" style="color: #9ca3af; font-weight: 700; font-size: 0.9rem;">⭐ O que dizem quem já utiliza o Grok AI</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="p-2.5 rounded h-100" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); font-size: 0.82rem;">
                                    <div class="text-warning mb-1">★★★★★</div>
                                    <p class="mb-1 text-light">"Liberação do Pix instantânea. O assistente de IA identificou cartões do árbitro com precisão incrível."</p>
                                    <small class="text-muted">— Lucas M., Trader Esportivo</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="p-2.5 rounded h-100" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); font-size: 0.82rem;">
                                    <div class="text-warning mb-1">★★★★★</div>
                                    <p class="mb-1 text-light">"Recarrego R$ 10 sempre que preciso. Custo muito baixo e relatórios de cartões de alto nível."</p>
                                    <small class="text-muted">— Rodrigo S., Analista de Dados</small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Botão de Confirmação Manual / Verificação -->
                    <div class="text-center mt-4">
                        <button id="btn-confirm-payment" class="btn btn-success btn-lg w-100" onclick="verificarPagamentoManual()" style="background: #10b981; border-color: #10b981; font-weight: 700; padding: 12px;">
                            🔄 Já Realizei o PIX - Verificar no Mercado Pago
                        </button>
                        <a href="/football-trends" class="btn btn-link mt-3 text-muted" style="text-decoration: none; font-size: 0.9rem;"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
let currentMpPaymentId = null;
let mpPollingInterval = null;

document.addEventListener('DOMContentLoaded', function() {
    carregarPixMercadoPago();

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

function carregarPixMercadoPago() {
    const loadingEl = document.getElementById('mp-loading');
    if (!loadingEl) return; // Se requer login social, não existe elemento

    loadingEl.style.display = 'block';
    document.getElementById('mp-pix-content').style.display = 'none';
    document.getElementById('mp-error-area').style.display = 'none';

    fetch('/subscription/create-mp-pix', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ tipo: 'grok_credits' })
    })
    .then(res => res.json())
    .then(data => {
        loadingEl.style.display = 'none';

        if (data.status === 'success' && data.payment_id) {
            currentMpPaymentId = data.payment_id;
            document.getElementById('mp-pix-content').style.display = 'block';
            document.getElementById('mp-pix-copia-cola').value = data.qr_code || '';

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

            iniciarPollingMercadoPago(data.payment_id);
        } else {
            document.getElementById('mp-error-area').style.display = 'block';
            document.getElementById('mp-error-msg').innerText = data.message || 'Erro ao gerar cobrança Mercado Pago.';
        }
    })
    .catch(err => {
        console.error('Erro na chamada Mercado Pago:', err);
        loadingEl.style.display = 'none';
        document.getElementById('mp-error-area').style.display = 'block';
        document.getElementById('mp-error-msg').innerText = 'Falha de conexão com o servidor Mercado Pago.';
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
                    
                    if (typeof gtag === 'function') {
                        gtag('event', 'purchase', {
                            'transaction_id': 'grok_' + paymentId,
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

                    setTimeout(() => {
                        alert('🎉 Pagamento aprovado no Mercado Pago! 20 créditos Grok AI foram adicionados à sua conta.');
                        window.location.href = '/football-trends';
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
        btn.innerHTML = '🔄 Já Realizei o PIX - Verificar no Mercado Pago';

        if (data.status === 'success' && data.approved) {
            alert('🎉 Pagamento confirmado e aprovado! 20 créditos foram adicionados.');
            window.location.href = '/football-trends';
        } else {
            alert('ℹ️ O pagamento ainda está pendente de confirmação pelo Mercado Pago (Status: ' + (data.status_mp || 'pendente') + '). Por favor, conclua a transferência no app do seu banco.');
        }
    })
    .catch(err => {
        btn.disabled = false;
        btn.innerHTML = '🔄 Já Realizei o PIX - Verificar no Mercado Pago';
        alert('❌ Erro ao consultar Mercado Pago. Tente novamente em instantes.');
    });
}

function simularPagamentoDev() {
    if (!currentMpPaymentId) {
        alert('Nenhum pagamento ativo para simular.');
        return;
    }
    const btn = document.getElementById('btn-simulate-mp');
    if (btn) {
        btn.disabled = true;
        btn.innerHTML = '⏳ Simulando aprovação no Sandbox...';
    }

    fetch('/subscription/simulate-mp-pix/' + currentMpPaymentId, {
        method: 'POST',
        headers: {
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(res => res.json())
    .then(data => {
        if (data.status === 'success') {
            const badge = document.getElementById('mp-status-badge');
            if (badge) {
                badge.className = 'badge bg-success p-2';
                badge.innerText = '✅ Pagamento Aprovado no Sandbox!';
            }
            if (typeof mpPollingInterval !== 'undefined' && mpPollingInterval) {
                clearInterval(mpPollingInterval);
            }
            verificarPagamentoManual();
        } else {
            alert('❌ Erro na simulação: ' + (data.message || 'Falha ao comunicar com o Sandbox.'));
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = '⚡ Simular Pagamento Aprovado no Sandbox';
            }
        }
    })
    .catch(err => {
        console.error('Erro ao simular:', err);
        alert('❌ Erro ao enviar simulação.');
        if (btn) {
            btn.disabled = false;
            btn.innerHTML = '⚡ Simular Pagamento Aprovado no Sandbox';
        }
    });
}
</script>

<?php
require VIEWPATH . '/footer.php';
?>
