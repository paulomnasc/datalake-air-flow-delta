<?php
if (!defined('VIEWPATH')) {
    define('VIEWPATH', realpath(APPPATH) . DIRECTORY_SEPARATOR . 'Views');
}
require VIEWPATH . '/header.php';
?>

<div id="content" class="container-fluid py-4" style="background: #f8f9fa; min-height: 85vh;">
    <div class="container">
        <!-- Hero Header -->
        <div class="p-4 mb-4 rounded-3 text-white" style="background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%); box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);">
            <div class="container-fluid py-2">
                <h1 class="display-6 fw-bold"><i class="fas fa-spider me-3"></i>Gerenciamento de URLs do Crawler</h1>
                <p class="col-md-10 fs-6 opacity-75">
                    Adicione categorias (nichos) e URLs personalizadas para enriquecer a busca da DAG do Groq Crawler. 
                    Dessa forma, além dos sites localizados pela Inteligência Artificial, as URLs abaixo também serão rastreadas e analisadas.
                </p>
            </div>
        </div>

        <!-- Alert messages -->
        <div id="alert-container"></div>

        <div class="row g-4">
            <!-- Coluna 1: Adicionar Categoria -->
            <div class="col-lg-4">
                <div class="card border-0 shadow-sm rounded-3">
                    <div class="card-header bg-white py-3 border-0">
                        <h5 class="card-title mb-0 fw-bold text-dark"><i class="fas fa-folder-plus text-primary me-2"></i>Novo Nicho / Categoria</h5>
                    </div>
                    <div class="card-body">
                        <form id="formAddCategory" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="category_name" class="form-label text-muted small fw-bold">Nome do Nicho</label>
                                <input type="text" class="form-control rounded-3" id="category_name" name="nome" placeholder="ex: suplementos, pet shop, eletrônicos" required>
                                <div class="form-text text-muted small">
                                    O nome será salvo em minúsculo e deve corresponder ao nicho pesquisado na DAG.
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 rounded-3 py-2 fw-bold shadow-sm">
                                <i class="fas fa-plus-circle me-2"></i>Cadastrar Nicho
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Coluna 2: Lista de Categorias e URLs -->
            <div class="col-lg-8">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fas fa-list text-primary me-2"></i>Nichos e URLs Cadastrados</h5>
                    <span class="badge bg-secondary rounded-pill"><?= count($categorias) ?> nichos</span>
                </div>

                <?php if (empty($categorias)): ?>
                    <div class="card border-0 shadow-sm text-center py-5 rounded-3 bg-white">
                        <div class="card-body">
                            <i class="fas fa-link fa-3x text-muted mb-3 opacity-50"></i>
                            <h5 class="text-muted">Nenhuma categoria cadastrada</h5>
                            <p class="text-muted small">Use o formulário ao lado para cadastrar seu primeiro nicho e suas URLs customizadas.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row row-cols-1 g-3">
                        <?php foreach ($categorias as $cat): ?>
                            <div class="col" id="category-card-<?= $cat->id ?>">
                                <div class="card border-0 shadow-sm rounded-3">
                                    <!-- Card Header -->
                                    <div class="card-header bg-white border-0 py-3 d-flex justify-content-between align-items-center">
                                        <div class="d-flex align-items-center">
                                            <span class="bg-primary-subtle text-primary p-2 rounded-3 me-3 d-inline-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                <i class="fas fa-tag"></i>
                                            </span>
                                            <div>
                                                <h6 class="mb-0 fw-bold text-dark text-capitalize"><?= htmlspecialchars($cat->nome) ?></h6>
                                                <small class="text-muted"><?= count($cat->urls) ?> URL(s) associada(s)</small>
                                            </div>
                                        </div>
                                        <button class="btn btn-outline-danger btn-sm border-0 rounded-circle" 
                                                onclick="deleteCategory(<?= $cat->id ?>, '<?= htmlspecialchars($cat->nome) ?>')" 
                                                title="Excluir nicho inteiro">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>

                                    <!-- Card Body: URLs List -->
                                    <div class="card-body py-2 border-top border-light">
                                        <?php if (empty($cat->urls)): ?>
                                            <p class="text-muted small my-3 text-center">Nenhuma URL customizada para este nicho. Adicione uma abaixo.</p>
                                        <?php else: ?>
                                            <div class="list-group list-group-flush">
                                                <?php foreach ($cat->urls as $url): ?>
                                                    <div class="list-group-item d-flex justify-content-between align-items-center py-2 px-0 border-0 border-bottom border-light-subtle" id="url-item-<?= $url->id ?>">
                                                        <div class="d-flex align-items-center text-truncate me-2">
                                                            <i class="fas fa-globe text-muted me-2 small"></i>
                                                            <a href="<?= htmlspecialchars($url->url) ?>" target="_blank" class="text-decoration-none text-dark small text-truncate">
                                                                <?= htmlspecialchars($url->url) ?>
                                                            </a>
                                                        </div>
                                                        <button class="btn btn-link text-danger p-0 border-0" 
                                                                onclick="deleteUrl(<?= $url->id ?>)" 
                                                                title="Excluir URL">
                                                            <i class="fas fa-times-circle"></i>
                                                        </button>
                                                    </div>
                                                <?php endforeach; ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Card Footer: Add URL Form -->
                                    <div class="card-footer bg-white border-0 py-3">
                                        <form onsubmit="addUrl(event, <?= $cat->id ?>)" class="input-group">
                                            <input type="url" class="form-control rounded-start-3 form-control-sm" 
                                                   placeholder="Inserir URL (ex: https://loja.com.br)" 
                                                   id="input-url-<?= $cat->id ?>" required>
                                            <button class="btn btn-outline-primary btn-sm rounded-end-3" type="submit">
                                                <i class="fas fa-plus me-1"></i>Adicionar
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script>
    // Função utilitária para exibir alertas de feedback
    function showAlert(type, message) {
        const container = document.getElementById('alert-container');
        const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
        const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-triangle';
        
        container.innerHTML = `
            <div class="alert ${alertClass} alert-dismissible fade show rounded-3 shadow-sm border-0" role="alert">
                <i class="fas ${icon} me-2"></i>${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        `;
        // Auto-close alert after 5 seconds
        setTimeout(() => {
            const alertEl = document.querySelector('.alert');
            if (alertEl) {
                const bsAlert = new bootstrap.Alert(alertEl);
                bsAlert.close();
            }
        }, 5000);
    }

    // Submit do Form para Adicionar Categoria
    document.getElementById('formAddCategory').addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('category_name');
        const name = input.value;

        if (!name) return;

        $.ajax({
            url: '<?= base_url('crawler/category/add') ?>',
            type: 'POST',
            data: { nome: name },
            success: function(response) {
                if (response.status === 'success') {
                    showAlert('success', response.message);
                    input.value = '';
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAlert('danger', response.message || 'Erro ao cadastrar categoria.');
                }
            },
            error: function() {
                showAlert('danger', 'Erro na comunicação com o servidor.');
            }
        });
    });

    // Função para Adicionar URL
    function addUrl(event, categoryId) {
        event.preventDefault();
        const input = document.getElementById(`input-url-${categoryId}`);
        const urlValue = input.value;

        if (!urlValue) return;

        $.ajax({
            url: '<?= base_url('crawler/url/add') ?>',
            type: 'POST',
            data: {
                categoria_id: categoryId,
                url: urlValue
            },
            success: function(response) {
                if (response.status === 'success') {
                    showAlert('success', response.message);
                    input.value = '';
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAlert('danger', response.message || 'Erro ao cadastrar URL.');
                }
            },
            error: function() {
                showAlert('danger', 'Erro na comunicação com o servidor.');
            }
        });
    }

    // Função para Excluir Categoria
    function deleteCategory(id, name) {
        if (!confirm(`Tem certeza que deseja excluir o nicho "${name}" e TODAS as suas URLs associadas?`)) {
            return;
        }

        $.ajax({
            url: `<?= base_url('crawler/category/delete') ?>/${id}`,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function(response) {
                if (response.status === 'success') {
                    showAlert('success', response.message);
                    $(`#category-card-${id}`).fadeOut(400, function() {
                        $(this).remove();
                        if ($('.row-cols-1 .col').length === 0) {
                            window.location.reload();
                        }
                    });
                } else {
                    showAlert('danger', response.message || 'Erro ao excluir categoria.');
                }
            },
            error: function() {
                showAlert('danger', 'Erro ao processar a requisição de exclusão.');
            }
        });
    }

    // Função para Excluir URL específica
    function deleteUrl(id) {
        if (!confirm('Tem certeza que deseja excluir esta URL?')) {
            return;
        }

        $.ajax({
            url: `<?= base_url('crawler/url/delete') ?>/${id}`,
            type: 'POST',
            data: { _method: 'DELETE' },
            success: function(response) {
                if (response.status === 'success') {
                    showAlert('success', response.message);
                    $(`#url-item-${id}`).slideUp(300, function() {
                        $(this).remove();
                        // Recarrega se quiser atualizar o contador de URLs
                        setTimeout(() => window.location.reload(), 500);
                    });
                } else {
                    showAlert('danger', response.message || 'Erro ao excluir URL.');
                }
            },
            error: function() {
                showAlert('danger', 'Erro ao processar a requisição de exclusão.');
            }
        });
    }
</script>

<?php require VIEWPATH . '/footer.php'; ?>
