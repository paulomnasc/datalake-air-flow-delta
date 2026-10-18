<?php
if (! defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div id="content" style="font-family: 'Outfit', sans-serif; background: #0e1620; min-height: 80vh; display: flex; align-items: center; justify-content: center; padding: 40px 15px; color: #f3f4f6;">
    <div class="container text-center" style="max-width: 520px;">
        <div class="card shadow-lg p-4" style="background: #172230; border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 16px;">
            <div class="mb-3">
                <span style="font-size: 3.5rem;">🚧</span>
            </div>
            <h2 style="font-weight: 800; color: #ffffff; margin-bottom: 12px;">Página em Manutenção</h2>
            <p style="color: #9ca3af; font-size: 1.05rem; line-height: 1.6; margin-bottom: 25px;">
                O módulo de recarga de créditos para o assistente <strong>Grok AI</strong> está temporariamente em manutenção para melhorias técnicas. Em breve estará disponível novamente!
            </p>
            <div>
                <a href="<?= base_url('football-trends') ?>" class="btn btn-warning font-weight-bold" style="background: #f47c20; border-color: #f47c20; color: #ffffff; padding: 10px 24px; border-radius: 10px; font-weight: 700; text-decoration: none; display: inline-flex; align-items: center; gap: 8px;">
                    <i class="bi bi-arrow-left"></i> Voltar ao Dashboard
                </a>
            </div>
        </div>
    </div>
</div>

<?php
require VIEWPATH . '/footer.php';
?>
