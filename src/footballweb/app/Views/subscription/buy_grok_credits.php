<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<!-- Bibliotecas para PIX, QR Code e Mercado Pago SDK v2 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<script src="https://sdk.mercadopago.com/js/v2"></script>

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

    /* Estilização moderna das Abas de Pagamento */
    .payment-tab-nav {
        background: rgba(10, 16, 26, 0.7);
        border: 1px solid rgba(255, 255, 255, 0.1);
        border-radius: 12px;
        padding: 5px;
        display: flex;
        gap: 6px;
    }
    .payment-tab-nav .nav-link {
        color: #9ca3af;
        border-radius: 9px;
        font-weight: 700;
        font-size: 0.95rem;
        padding: 10px 16px;
        transition: all 0.25s ease;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }
    .payment-tab-nav .nav-link:hover {
        color: #ffffff;
        background: rgba(255, 255, 255, 0.05);
    }
    .payment-tab-nav .nav-link.active {
        color: #ffffff;
        background: #f47c20;
        box-shadow: 0 4px 14px rgba(244, 124, 32, 0.35);
    }
    .payment-tab-nav .nav-link.active#tab-card-btn {
        background: #2563eb;
        box-shadow: 0 4px 14px rgba(37, 99, 235, 0.35);
    }

    /* Formulário de Cartão */
    .card-input-dark {
        background: #0f1620 !important;
        border: 1px solid rgba(255, 255, 255, 0.12) !important;
        color: #f3f4f6 !important;
        border-radius: 10px !important;
        padding: 11px 14px !important;
        font-size: 0.95rem !important;
        transition: border-color 0.2s, box-shadow 0.2s;
    }
    .card-input-dark:focus {
        border-color: #3b82f6 !important;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2) !important;
        outline: none !important;
    }
    .card-input-dark::placeholder {
        color: #6b7280 !important;
    }
    .card-form-label {
        font-size: 0.85rem;
        font-weight: 700;
        color: #d1d5db;
        margin-bottom: 6px;
        display: block;
    }
    .card-brand-badge {
        position: absolute;
        right: 12px;
        top: 50%;
        transform: translateY(-50%);
        font-size: 0.8rem;
        font-weight: 700;
        color: #9ca3af;
        background: rgba(255, 255, 255, 0.08);
        padding: 4px 8px;
        border-radius: 6px;
        pointer-events: none;
    }
</style>

<div id="content" style="font-family: 'Outfit', sans-serif; background: #0e1620; min-height: 100vh; padding: 40px 15px; color: #f3f4f6;">
    <div class="container" style="max-width: 620px;">
        
        <div class="text-center mb-4">
            <h1 style="font-weight: 800; color: #ffffff; margin-bottom: 8px;">🚀 Créditos Grok AI</h1>
            <p class="text-muted" style="font-size: 1.1rem;">Recarregue seu saldo para desbloquear o assistente e estatísticas das ligas principais.</p>
        </div>

        <div class="card shadow-lg" style="background: #172230; border: 1px solid rgba(255, 255, 255, 0.05); border-radius: 16px;">
            <div class="card-header text-center" style="background: #f47c20; border-top-left-radius: 15px; border-top-right-radius: 15px; padding: 20px;">
                <h3 class="mb-0 text-white" style="font-weight: 700;">💳 Recarga de Créditos</h3>
            </div>
            <div class="card-body" style="padding: 28px 24px;">
                
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
                            <li>⚡ <strong>Ativação instantânea:</strong> Créditos liberados em segundos via Mercado Pago.</li>
                            <li>💎 <strong>Zero fidelidade:</strong> Sem mensalidade oculta, pague somente conforme a sua necessidade.</li>
                        </ul>
                    </div>

                    <!-- Selos de Segurança e Confiança -->
                    <div class="d-flex flex-wrap justify-content-center gap-3 mb-4 text-center p-2 rounded" style="background: rgba(255,255,255,0.02); border: 1px solid rgba(255,255,255,0.05); font-size: 0.85rem; color: #9ca3af;">
                        <span class="d-flex align-items-center gap-1"><i class="bi bi-shield-check text-success" style="font-size: 1.1rem;"></i> Checkout Oficial Mercado Pago</span>
                        <span class="d-flex align-items-center gap-1"><i class="bi bi-lock-fill text-info" style="font-size: 1rem;"></i> Encriptação SSL 256-bit</span>
                        <span class="d-flex align-items-center gap-1"><i class="bi bi-lightning-charge-fill text-warning" style="font-size: 1rem;"></i> Ativação Automática 24/7</span>
                    </div>

                    <div class="text-center mb-4">
                        <h5 style="color: #8a99a8; margin-bottom: 4px;">Valor da Recarga:</h5>
                        <h2 style="font-weight: 800; color: #ffffff;">R$ 10,00</h2>
                        <span class="badge bg-success" style="font-size: 0.85rem; padding: 6px 12px;">💰 Apenas R$ 0,50 por consulta</span>
                    </div>

                    <hr style="border-top: 1px solid rgba(255, 255, 255, 0.1);">

                    <!-- ABAS DE SELEÇÃO: PIX OU CARTÃO DE CRÉDITO -->
                    <div class="mb-4">
                        <div class="payment-tab-nav" role="tablist">
                            <button class="nav-link w-50 active" id="tab-pix-btn" data-bs-toggle="pill" data-bs-target="#pane-pix" type="button" role="tab" aria-controls="pane-pix" aria-selected="true" onclick="selecionarMetodo('pix')">
                                <i class="bi bi-qr-code-scan"></i> ⚡ PIX Instantâneo
                            </button>
                            <button class="nav-link w-50" id="tab-card-btn" data-bs-toggle="pill" data-bs-target="#pane-card" type="button" role="tab" aria-controls="pane-card" aria-selected="false" onclick="selecionarMetodo('card')">
                                <i class="bi bi-credit-card-2-front-fill"></i> 💳 Cartão de Crédito
                            </button>
                        </div>
                    </div>

                    <div class="tab-content" id="paymentTabContent">
                        
                        <!-- ABA 1: PIX MERCADO PAGO -->
                        <div class="tab-pane fade show active" id="pane-pix" role="tabpanel" aria-labelledby="tab-pix-btn">
                            
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

                            <!-- Instruções Pix -->
                            <div class="alert text-start mt-3" role="alert" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.06); color: #f3f4f6;">
                                <h6 style="font-weight: 700; color: #ffffff; margin-bottom: 10px;">📌 Como funciona o Pix?</h6>
                                <ol class="mb-0" style="padding-left: 20px; font-size: 0.9rem; line-height: 1.6;">
                                    <li>Abra o aplicativo de pagamentos do seu banco.</li>
                                    <li>Escolha pagar via <strong>PIX</strong> (QR Code ou Copia e Cola).</li>
                                    <li>Confirme o valor de <strong>R$ 10,00</strong>.</li>
                                    <li>O Mercado Pago confirma a transação em segundos e seus <strong>20 créditos</strong> são creditados na hora!</li>
                                </ol>
                            </div>

                            <!-- Botão de Confirmação Manual PIX -->
                            <div class="text-center mt-4">
                                <button id="btn-confirm-payment" class="btn btn-success btn-lg w-100" onclick="verificarPagamentoManual()" style="background: #10b981; border-color: #10b981; font-weight: 700; padding: 12px; border-radius: 12px;">
                                    🔄 Já Realizei o PIX - Verificar no Mercado Pago
                                </button>
                            </div>

                        </div>

                        <!-- ABA 2: CARTÃO DE CRÉDITO MERCADO PAGO -->
                        <div class="tab-pane fade" id="pane-card" role="tabpanel" aria-labelledby="tab-card-btn">
                            
                            <form id="form-mp-card" onsubmit="processarPagamentoCartao(event)" class="text-start">
                                
                                <div class="mb-3">
                                    <label class="card-form-label" for="cardholderName">👤 Nome do Titular (como no cartão)</label>
                                    <input type="text" id="cardholderName" class="form-control card-input-dark" placeholder="Ex: NOME COMPLETO SOBRENOME" value="<?= esc($usuario_nome ?? '') ?>" required autocomplete="cc-name">
                                </div>

                                <div class="mb-3">
                                    <label class="card-form-label" for="docNumber">📄 CPF do Titular</label>
                                    <input type="text" id="docNumber" class="form-control card-input-dark" placeholder="000.000.000-00" maxlength="14" required>
                                </div>

                                <div class="mb-3 position-relative">
                                    <label class="card-form-label" for="cardNumber">💳 Número do Cartão</label>
                                    <input type="text" id="cardNumber" class="form-control card-input-dark" placeholder="0000 0000 0000 0000" maxlength="19" required autocomplete="cc-number">
                                    <span id="card-brand-badge" class="card-brand-badge">Bandeira</span>
                                </div>

                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <label class="card-form-label" for="cardExpiry">📅 Validade</label>
                                        <input type="text" id="cardExpiry" class="form-control card-input-dark" placeholder="MM/AA" maxlength="5" required autocomplete="cc-exp">
                                    </div>
                                    <div class="col-6">
                                        <label class="card-form-label" for="securityCode">🔒 Código CVV</label>
                                        <input type="text" id="securityCode" class="form-control card-input-dark" placeholder="123" maxlength="4" required autocomplete="cc-csc">
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label class="card-form-label" for="installments">🔢 Parcelamento</label>
                                    <select id="installments" class="form-select card-input-dark" style="color: #ffffff;">
                                        <option value="1" selected>1x de R$ 10,00 (sem juros)</option>
                                    </select>
                                </div>

                                <!-- Feedback do Cartão -->
                                <div id="card-alert" class="alert mt-3" style="display: none;" role="alert"></div>

                                <button id="btn-submit-card" type="submit" class="btn btn-primary btn-lg w-100 mt-2" style="background: #2563eb; border-color: #2563eb; font-weight: 700; padding: 13px; border-radius: 12px;">
                                    <i class="bi bi-shield-lock-fill me-1"></i> Pagar R$ 10,00 no Cartão de Crédito
                                </button>
                            </form>

                            <!-- Selo de Tokenização Segura -->
                            <div class="text-center mt-3 text-muted" style="font-size: 0.8rem;">
                                <i class="bi bi-shield-check text-success"></i> Seus dados são criptografados de ponta a ponta e tokenizados diretamente pelo Mercado Pago. Nenhum dado do cartão é salvo em nossos servidores.
                            </div>

                        </div>

                    </div>

                    <!-- Prova Social / Depoimentos de Usuários -->
                    <div class="mt-4 pt-3 border-top border-secondary border-opacity-25">
                        <h6 class="text-center mb-3" style="color: #9ca3af; font-weight: 700; font-size: 0.9rem;">⭐ O que dizem quem já utiliza o Grok AI</h6>
                        <div class="row g-2">
                            <div class="col-md-6">
                                <div class="p-2.5 rounded h-100" style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.05); font-size: 0.82rem;">
                                    <div class="text-warning mb-1">★★★★★</div>
                                    <p class="mb-1 text-light">"Liberação instantânea. O assistente de IA identificou cartões do árbitro com precisão incrível."</p>
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

                    <div class="text-center mt-3">
                        <a href="/football-trends" class="btn btn-link text-muted" style="text-decoration: none; font-size: 0.9rem;"><i class="bi bi-arrow-left"></i> Voltar ao Dashboard</a>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
let currentMpPaymentId = null;
let mpPollingInterval = null;
let mpInstance = null;
let detectedPaymentMethodId = 'visa'; // Fallback padrão
let detectedIssuerId = null;

const MP_PUBLIC_KEY = '<?= esc($mp_public_key ?? 'APP_USR-4558feb7-9f62-4fd8-9e21-76a11730b68f') ?>';

document.addEventListener('DOMContentLoaded', function() {
    // Inicializa SDK do Mercado Pago para Tokenização Client-Side
    if (typeof MercadoPago !== 'undefined') {
        try {
            mpInstance = new MercadoPago(MP_PUBLIC_KEY, { locale: 'pt-BR' });
        } catch (e) {
            console.error('Falha ao inicializar Mercado Pago SDK:', e);
        }
    }

    // Inicia geração do Pix padrão
    carregarPixMercadoPago();

    // Máscaras e formatações de formulário do cartão
    initCardInputMasks();

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

function selecionarMetodo(metodo) {
    if (metodo === 'card') {
        const paneCard = document.getElementById('pane-card');
        const panePix = document.getElementById('pane-pix');
        if (paneCard && panePix) {
            panePix.classList.remove('show', 'active');
            paneCard.classList.add('show', 'active');
        }
        document.getElementById('tab-pix-btn')?.classList.remove('active');
        document.getElementById('tab-card-btn')?.classList.add('active');
    } else {
        const paneCard = document.getElementById('pane-card');
        const panePix = document.getElementById('pane-pix');
        if (paneCard && panePix) {
            paneCard.classList.remove('show', 'active');
            panePix.classList.add('show', 'active');
        }
        document.getElementById('tab-card-btn')?.classList.remove('active');
        document.getElementById('tab-pix-btn')?.classList.add('active');
    }
}

function initCardInputMasks() {
    const cardInput = document.getElementById('cardNumber');
    const expiryInput = document.getElementById('cardExpiry');
    const cpfInput = document.getElementById('docNumber');

    if (cardInput) {
        cardInput.addEventListener('input', function(e) {
            let val = e.target.value.replace(/\D/g, '').substring(0, 16);
            let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
            e.target.value = formatted;

            // Detecta bandeira com os primeiros 6 dígitos (BIN)
            if (val.length >= 6 && mpInstance) {
                const bin = val.substring(0, 6);
                mpInstance.getPaymentMethods({ bin: bin })
                .then(res => {
                    if (res && res.results && res.results.length > 0) {
                        const pm = res.results[0];
                        detectedPaymentMethodId = pm.id;
                        const badge = document.getElementById('card-brand-badge');
                        if (badge) {
                            badge.innerText = pm.name.toUpperCase();
                            badge.style.color = '#3b82f6';
                            badge.style.background = 'rgba(59, 130, 246, 0.2)';
                        }
                    }
                })
                .catch(err => console.debug('BIN lookup error:', err));
            }
        });
    }

    if (expiryInput) {
        expiryInput.addEventListener('input', function(e) {
            let val = e.target.value.replace(/\D/g, '').substring(0, 4);
            if (val.length >= 3) {
                e.target.value = val.substring(0, 2) + '/' + val.substring(2);
            } else {
                e.target.value = val;
            }
        });
    }

    if (cpfInput) {
        cpfInput.addEventListener('input', function(e) {
            let val = e.target.value.replace(/\D/g, '').substring(0, 11);
            if (val.length > 9) {
                val = val.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, "$1.$2.$3-$4");
            } else if (val.length > 6) {
                val = val.replace(/(\d{3})(\d{3})(\d{1,3})/, "$1.$2.$3");
            } else if (val.length > 3) {
                val = val.replace(/(\d{3})(\d{1,3})/, "$1.$2");
            }
            e.target.value = val;
        });
    }
}

/* ==========================================================
   FLUXO PIX (MERCADO PAGO)
   ========================================================== */
function carregarPixMercadoPago() {
    const loadingEl = document.getElementById('mp-loading');
    if (!loadingEl) return;

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

/* ==========================================================
   FLUXO CARTÃO DE CRÉDITO (MERCADO PAGO TOKENIZAÇÃO)
   ========================================================== */
function processarPagamentoCartao(event) {
    event.preventDefault();

    const alertBox = document.getElementById('card-alert');
    const submitBtn = document.getElementById('btn-submit-card');

    alertBox.style.display = 'none';

    if (!mpInstance) {
        alertBox.className = 'alert alert-danger';
        alertBox.innerText = 'Mercado Pago SDK não carregado. Recarregue a página e tente novamente.';
        alertBox.style.display = 'block';
        return;
    }

    const cardholderName = document.getElementById('cardholderName').value.trim();
    const docNumber = document.getElementById('docNumber').value.replace(/\D/g, '');
    const cardNumber = document.getElementById('cardNumber').value.replace(/\D/g, '');
    const cardExpiry = document.getElementById('cardExpiry').value.trim();
    const securityCode = document.getElementById('securityCode').value.trim();
    const installments = document.getElementById('installments').value;

    if (cardNumber.length < 13) {
        mostrarErroCartao('Por favor, informe um número de cartão de crédito válido.');
        return;
    }

    const expiryParts = cardExpiry.split('/');
    if (expiryParts.length !== 2 || expiryParts[0].length !== 2 || expiryParts[1].length !== 2) {
        mostrarErroCartao('Informe a validade no formato MM/AA (ex: 12/28).');
        return;
    }

    const month = expiryParts[0];
    const year = '20' + expiryParts[1];

    if (docNumber.length !== 11) {
        mostrarErroCartao('Informe um CPF válido com 11 dígitos.');
        return;
    }

    if (securityCode.length < 3) {
        mostrarErroCartao('Informe o código de segurança (CVV).');
        return;
    }

    submitBtn.disabled = true;
    submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Criptografando e Processando Cartão...';

    // Cria token seguro nos servidores do Mercado Pago
    mpInstance.createCardToken({
        cardNumber: cardNumber,
        cardholderName: cardholderName,
        cardExpirationMonth: month,
        cardExpirationYear: year,
        securityCode: securityCode,
        identificationType: 'CPF',
        identificationNumber: docNumber
    })
    .then(tokenData => {
        if (!tokenData || !tokenData.id) {
            throw new Error('Não foi possível gerar a autorização do cartão. Revise os dados.');
        }

        const cardToken = tokenData.id;

        // Envia o token para o endpoint do FootballWeb
        return fetch('/subscription/process-mp-card', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({
                token: cardToken,
                payment_method_id: detectedPaymentMethodId || 'visa',
                installments: parseInt(installments, 10) || 1,
                issuer_id: detectedIssuerId,
                doc_number: docNumber,
                cardholder_name: cardholderName,
                tipo: 'grok_credits'
            })
        });
    })
    .then(res => res.json())
    .then(data => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-shield-lock-fill me-1"></i> Pagar R$ 10,00 no Cartão de Crédito';

        if (data.status === 'success' && data.approved) {
            alertBox.className = 'alert alert-success';
            alertBox.innerHTML = '<strong>🎉 Parabéns!</strong> ' + (data.message || 'Pagamento com cartão aprovado! 20 créditos adicionados.');
            alertBox.style.display = 'block';

            if (typeof gtag === 'function') {
                gtag('event', 'purchase', {
                    'transaction_id': 'grok_card_' + (data.payment_id || Date.now()),
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
            }, 1200);

        } else if (data.status === 'pending') {
            alertBox.className = 'alert alert-warning';
            alertBox.innerHTML = '<strong>⏳ Em análise:</strong> ' + (data.message || 'O pagamento está em análise pelo emissor do cartão.');
            alertBox.style.display = 'block';
        } else {
            mostrarErroCartao(data.message || 'Transação não autorizada. Revise os dados ou tente outro cartão.');
        }
    })
    .catch(err => {
        submitBtn.disabled = false;
        submitBtn.innerHTML = '<i class="bi bi-shield-lock-fill me-1"></i> Pagar R$ 10,00 no Cartão de Crédito';
        console.error('Erro no processamento do cartão:', err);
        mostrarErroCartao(err.message || 'Falha na comunicação com o Mercado Pago. Tente novamente ou use o PIX.');
    });
}

function mostrarErroCartao(msg) {
    const alertBox = document.getElementById('card-alert');
    if (alertBox) {
        alertBox.className = 'alert alert-danger';
        alertBox.innerHTML = '<i class="bi bi-exclamation-triangle-fill me-1"></i> ' + msg;
        alertBox.style.display = 'block';
    } else {
        alert(msg);
    }
}
</script>

<?php
require VIEWPATH . '/footer.php';
?>

