<?php
if (defined('MODAL_STATUS_REPORT_IA_INCLUDED')) {
    return;
}
define('MODAL_STATUS_REPORT_IA_INCLUDED', true);
?>
<!-- Modal Status Report com IA Gemini -->
<div class="modal fade" id="modalStatusReportIa" tabindex="-1" aria-labelledby="modalStatusReportIaLabel" aria-hidden="true" style="z-index: 1055;">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content" style="background: #1e2230; color: #e8eaed; border: 1px solid rgba(255, 215, 0, 0.35); border-radius: 14px; box-shadow: 0 15px 50px rgba(0,0,0,0.6);">
            <div class="modal-header" style="border-bottom: 1px solid rgba(255, 215, 0, 0.2); background: rgba(0,0,0,0.25); padding: 18px 24px;">
                <h5 class="modal-title d-flex align-items-center gap-2" id="modalStatusReportIaLabel" style="color: #FFD700; font-weight: 700;">
                    <i class="bi bi-robot fs-4 text-warning"></i> Status Report Executivo por Sistema (Gemini AI)
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar"></button>
            </div>
            <div class="modal-body" style="padding: 24px;">
                <!-- Filtros e Ações de Disparo -->
                <div class="row align-items-end g-3 mb-4 p-3 rounded" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(74, 144, 226, 0.25);">
                    <div class="col-md-7">
                        <label for="selectSistemaReport" class="form-label fw-bold text-light mb-1">
                            <i class="bi bi-diagram-3-fill text-info me-1"></i> Selecione o Sistema de Informação:
                        </label>
                        <select class="form-select bg-dark text-light border-secondary" id="selectSistemaReport" style="font-size: 0.95rem;">
                            <option value="all" selected>Todos os Sistemas (Consolidado Global)</option>
                        </select>
                    </div>
                    <div class="col-md-5 d-flex gap-2">
                        <button type="button" class="btn btn-warning flex-grow-1 fw-bold d-flex align-items-center justify-content-center gap-2" id="btnDispararGeracaoIa" style="padding: 10px 18px; color: #1a1d29;">
                            <i class="bi bi-stars"></i> <span id="btnGerarTexto">Gerar Relatório com IA</span>
                        </button>
                    </div>
                </div>

                <!-- Feedback de Carregamento -->
                <div id="loadingStatusReport" class="text-center py-5 d-none">
                    <div class="spinner-border text-warning" role="status" style="width: 3.5rem; height: 3.5rem;">
                        <span class="visually-hidden">Carregando...</span>
                    </div>
                    <p class="mt-3 text-light fw-bold" style="font-size: 1.15rem;">
                        <i class="bi bi-cpu text-warning me-1"></i> A IA Gemini está analisando os Sistemas, Ordens de Serviço e Demandas...
                    </p>
                    <small class="text-muted d-block mt-1">Auditando prazos, horas alocadas, estágios de homologação e riscos contratuais.</small>
                </div>

                <!-- Painel de Exibição do Relatório -->
                <div id="containerRelatorioGerado" class="d-none">
                    <div class="d-flex justify-content-between align-items-center mb-2 px-1 flex-wrap gap-2">
                        <span class="badge bg-primary px-3 py-2" id="badgeSistemaNome" style="font-size: 0.9rem;">Todos os Sistemas</span>
                        <small class="text-muted"><i class="bi bi-clock-history me-1"></i> Gerado em: <strong id="spanDataGeracao" class="text-light">-</strong></small>
                    </div>
                    <div id="relatorioIaOutput" class="p-4 rounded" style="background: #131620; border: 1px solid rgba(255,255,255,0.08); font-family: 'Consolas', 'Courier New', monospace, sans-serif; line-height: 1.65; white-space: pre-wrap; font-size: 0.95rem; max-height: 520px; overflow-y: auto; color: #f8f9fa;">
                    </div>
                </div>

                <!-- Placeholder inicial antes de gerar -->
                <div id="placeholderStatusReport" class="text-center py-5 text-muted">
                    <i class="bi bi-file-earmark-text display-3 d-block mb-3 opacity-50 text-warning"></i>
                    <p class="mb-1 text-light">Selecione o escopo do sistema acima e clique em <strong>"Gerar Relatório com IA"</strong>.</p>
                    <small class="text-secondary">O relatório correlacionará todas as Ordens de Serviço emitidas e suas demandas relacionadas.</small>
                </div>
            </div>
            <div class="modal-footer" style="border-top: 1px solid rgba(255, 215, 0, 0.2); background: rgba(0,0,0,0.25); padding: 16px 24px;">
                <div class="d-flex justify-content-between w-100 flex-wrap gap-2">
                    <div class="d-flex align-items-center gap-2">
                        <button type="button" class="btn btn-outline-light d-flex align-items-center gap-1" id="btnCopiarReport" disabled>
                            <i class="bi bi-clipboard"></i> <span id="btnCopiarTexto">Copiar Texto</span>
                        </button>
                        <button type="button" class="btn btn-primary fw-bold d-flex align-items-center gap-1" id="btnExportarDocx" disabled style="background-color: #2b579a; border-color: #2b579a;">
                            <i class="bi bi-file-earmark-word-fill"></i> Exportar (.docx)
                        </button>
                        <button type="button" class="btn btn-outline-secondary btn-sm d-flex align-items-center gap-1" id="btnExportarTxt" disabled>
                            <i class="bi bi-file-text"></i> .txt
                        </button>
                    </div>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    function initStatusReportModal() {
        if (typeof jQuery === 'undefined') {
            setTimeout(initStatusReportModal, 100);
            return;
        }

        const $ = jQuery;
        let relatorioAtualTexto = '';
        let nomeSistemaAtual = 'Todos os Sistemas';

        function carregarSistemasCombo() {
            $.ajax({
                url: '<?= base_url("statusReportIa/sistemas") ?>',
                type: 'GET',
                dataType: 'json',
                success: function(resp) {
                    if (resp && resp.status === 'success' && resp.data) {
                        const $select = $('#selectSistemaReport');
                        $select.find('option:not([value="all"])').remove();
                        resp.data.forEach(function(sis) {
                            $select.append($('<option>', {
                                value: sis.id,
                                text: sis.nome + (sis.sigla ? ' (' + sis.sigla + ')' : '')
                            }));
                        });
                    }
                },
                error: function(err) {
                    console.warn('Erro ao carregar lista de sistemas para o Status Report:', err);
                }
            });
        }

        $(document).on('click', '#btnAbrirModalReportIa, [data-bs-target="#modalStatusReportIa"]', function() {
            carregarSistemasCombo();
        });

        $(document).on('click', '#btnDispararGeracaoIa', function() {
            const idSistema = $('#selectSistemaReport').val();
            nomeSistemaAtual = $('#selectSistemaReport option:selected').text();

            $('#placeholderStatusReport').addClass('d-none');
            $('#containerRelatorioGerado').addClass('d-none');
            $('#loadingStatusReport').removeClass('d-none');
            $('#btnDispararGeracaoIa').prop('disabled', true);
            $('#btnGerarTexto').text('Processando com IA...');
            $('#btnCopiarReport').prop('disabled', true);
            $('#btnExportarDocx').prop('disabled', true);
            $('#btnExportarTxt').prop('disabled', true);

            $.ajax({
                url: '<?= base_url("statusReportIa/gerar") ?>',
                type: 'POST',
                data: { id_sistema: idSistema },
                dataType: 'json',
                timeout: 60000,
                success: function(res) {
                    $('#loadingStatusReport').addClass('d-none');
                    $('#btnDispararGeracaoIa').prop('disabled', false);
                    $('#btnGerarTexto').text('Gerar Relatório com IA');

                    if (res && res.status === 'success' && res.report) {
                        relatorioAtualTexto = res.report;
                        $('#badgeSistemaNome').text(res.nome_sistema || nomeSistemaAtual);
                        $('#spanDataGeracao').text(res.gerado_em || new Date().toLocaleString('pt-BR'));
                        $('#relatorioIaOutput').text(res.report);
                        $('#containerRelatorioGerado').removeClass('d-none');
                        $('#btnCopiarReport').prop('disabled', false);
                        $('#btnExportarDocx').prop('disabled', false);
                        $('#btnExportarTxt').prop('disabled', false);
                    } else {
                        $('#containerRelatorioGerado').removeClass('d-none');
                        const msgErro = (res && res.mensagem) ? res.mensagem : 'Não foi possível gerar o relatório com a IA.';
                        $('#relatorioIaOutput').html('<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>' + msgErro + '</div>');
                    }
                },
                error: function(xhr, status, error) {
                    $('#loadingStatusReport').addClass('d-none');
                    $('#btnDispararGeracaoIa').prop('disabled', false);
                    $('#btnGerarTexto').text('Gerar Relatório com IA');
                    $('#containerRelatorioGerado').removeClass('d-none');
                    $('#relatorioIaOutput').html('<div class="alert alert-danger mb-0"><i class="bi bi-exclamation-triangle-fill me-2"></i>Erro na comunicação com o servidor (' + status + '): ' + error + '</div>');
                }
            });
        });

        $(document).on('click', '#btnCopiarReport', function() {
            if (!relatorioAtualTexto) return;

            if (navigator.clipboard && window.isSecureContext) {
                navigator.clipboard.writeText(relatorioAtualTexto).then(function() {
                    mostrarFeedbackCopia();
                }).catch(function() {
                    copiarFallback(relatorioAtualTexto);
                });
            } else {
                copiarFallback(relatorioAtualTexto);
            }
        });

        function mostrarFeedbackCopia() {
            const $btn = $('#btnCopiarTexto');
            const originalText = $btn.text();
            $btn.text('Copiado!');
            setTimeout(function() {
                $btn.text(originalText);
            }, 2000);
        }

        function copiarFallback(texto) {
            const textArea = document.createElement("textarea");
            textArea.value = texto;
            textArea.style.position = "fixed";
            textArea.style.left = "-999999px";
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            try {
                document.execCommand('copy');
                mostrarFeedbackCopia();
            } catch (err) {
                alert('Não foi possível copiar o texto automaticamente.');
            }
            document.body.removeChild(textArea);
        }

        $(document).on('click', '#btnExportarDocx', function() {
            if (!relatorioAtualTexto) return;

            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '<?= base_url("statusReportIa/exportarDocx") ?>';
            form.target = '_blank';

            const inputConteudo = document.createElement('input');
            inputConteudo.type = 'hidden';
            inputConteudo.name = 'conteudo';
            inputConteudo.value = relatorioAtualTexto;
            form.appendChild(inputConteudo);

            const inputSistema = document.createElement('input');
            inputSistema.type = 'hidden';
            inputSistema.name = 'nome_sistema';
            inputSistema.value = nomeSistemaAtual;
            form.appendChild(inputSistema);

            document.body.appendChild(form);
            form.submit();
            document.body.removeChild(form);
        });

        $(document).on('click', '#btnExportarTxt', function() {
            if (!relatorioAtualTexto) return;

            const agora = new Date();
            const dataFormatada = agora.getFullYear().toString() +
                String(agora.getMonth() + 1).padStart(2, '0') +
                String(agora.getDate()).padStart(2, '0') + '_' +
                String(agora.getHours()).padStart(2, '0') +
                String(agora.getMinutes()).padStart(2, '0');

            const siglaLimpa = (nomeSistemaAtual || 'Sistemas').replace(/[^a-zA-Z0-9_-]/g, '_').substring(0, 30);
            const nomeArquivo = 'Status_Report_' + siglaLimpa + '_' + dataFormatada + '.txt';

            try {
                const blob = new Blob([relatorioAtualTexto], { type: 'text/plain;charset=utf-8' });
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = nomeArquivo;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
                URL.revokeObjectURL(link.href);
            } catch (e) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = '<?= base_url("statusReportIa/exportarTxt") ?>';
                form.target = '_blank';

                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'conteudo';
                input.value = relatorioAtualTexto;
                form.appendChild(input);

                document.body.appendChild(form);
                form.submit();
                document.body.removeChild(form);
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initStatusReportModal);
    } else {
        initStatusReportModal();
    }
})();
</script>
