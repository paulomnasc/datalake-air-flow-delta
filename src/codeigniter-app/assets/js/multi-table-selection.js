/**
 * Multi-Table Selection UI
 * Gerencia a interface de seleção de múltiplas tabelas para DAGs
 */

// Estado global
let availableTables = [];
let selectedTables = new Set();

/**
 * Carrega tabelas disponíveis via AJAX
 */
function loadAvailableTables(connectionId, databaseName) {
    const loadingDiv = document.getElementById('tables-loading');
    const tablesContainer = document.getElementById('tables-container');
    
    if (loadingDiv) loadingDiv.style.display = 'block';
    if (tablesContainer) tablesContainer.innerHTML = '';
    
    fetch('/config/getAvailableTables', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: `connection_id=${encodeURIComponent(connectionId)}&database_name=${encodeURIComponent(databaseName)}`
    })
    .then(response => response.json())
    .then(data => {
        if (loadingDiv) loadingDiv.style.display = 'none';
        
        if (data.status === 'success') {
            availableTables = data.tables;
            renderTablesList(data.tables);
        } else {
            showError('Erro ao carregar tabelas: ' + data.mensagem);
        }
    })
    .catch(error => {
        if (loadingDiv) loadingDiv.style.display = 'none';
        showError('Erro de conexão: ' + error.message);
    });
}

/**
 * Renderiza lista de tabelas com checkboxes
 */
function renderTablesList(tables) {
    const container = document.getElementById('tables-container');
    
    if (!tables || tables.length === 0) {
        container.innerHTML = '<p class="no-tables">Nenhuma tabela encontrada</p>';
        return;
    }
    
    let html = '<div class="tables-grid">';
    html += '<div class="table-selection-header">';
    html += '<input type="checkbox" id="select-all-tables" onchange="toggleSelectAll(this.checked)">';
    html += '<label for="select-all-tables"><strong>Selecionar Todas</strong></label>';
    html += `<span class="table-count">(${tables.length} tabelas disponíveis)</span>`;
    html += '</div>';
    
    tables.forEach(table => {
        const tableId = `table-${table.table_name}`;
        const rowCountText = table.row_count ? ` (${formatNumber(table.row_count)} linhas)` : '';
        const sizeText = table.table_size_mb ? ` - ${table.table_size_mb} MB` : '';
        
        html += `
            <div class="table-item">
                <input type="checkbox" 
                       id="${tableId}" 
                       name="selected_tables[]" 
                       value="${table.table_name}"
                       onchange="updateTableSelection('${table.table_name}', this.checked)">
                <label for="${tableId}">
                    <span class="table-name">${table.table_name}</span>
                    <span class="table-info">${rowCountText}${sizeText}</span>
                </label>
            </div>
        `;
    });
    
    html += '</div>';
    html += '<div class="selection-summary">';
    html += '<span id="selected-count">0 tabelas selecionadas</span>';
    html += '</div>';
    
    container.innerHTML = html;
}

/**
 * Atualiza o set de tabelas selecionadas
 */
function updateTableSelection(tableName, isSelected) {
    if (isSelected) {
        selectedTables.add(tableName);
    } else {
        selectedTables.delete(tableName);
    }
    updateSelectionSummary();
}

/**
 * Seleciona/desseleciona todas as tabelas
 */
function toggleSelectAll(selectAll) {
    const checkboxes = document.querySelectorAll('input[name="selected_tables[]"]');
    
    selectedTables.clear();
    
    checkboxes.forEach(checkbox => {
        checkbox.checked = selectAll;
        if (selectAll) {
            selectedTables.add(checkbox.value);
        }
    });
    
    updateSelectionSummary();
}

/**
 * Atualiza o resumo de seleção
 */
function updateSelectionSummary() {
    const summaryElement = document.getElementById('selected-count');
    if (summaryElement) {
        const count = selectedTables.size;
        summaryElement.textContent = `${count} ${count === 1 ? 'tabela selecionada' : 'tabelas selecionadas'}`;
        
        // Atualiza cor baseado na seleção
        if (count > 0) {
            summaryElement.style.color = '#28a745';
            summaryElement.style.fontWeight = 'bold';
        } else {
            summaryElement.style.color = '#666';
            summaryElement.style.fontWeight = 'normal';
        }
    }
}

/**
 * Mostra/esconde seção de seleção de múltiplas tabelas
 */
function toggleMultiTableMode(enabled) {
    const multiTableSection = document.getElementById('multi-table-section');
    const singleTableField = document.getElementById('target_table_name');
    const parallelTasksField = document.getElementById('max_parallel_tasks_group');
    
    if (multiTableSection) {
        multiTableSection.style.display = enabled ? 'block' : 'none';
    }
    
    if (singleTableField) {
        singleTableField.required = !enabled;
        singleTableField.parentElement.style.display = enabled ? 'none' : 'block';
    }
    
    if (parallelTasksField) {
        parallelTasksField.style.display = enabled ? 'block' : 'none';
    }
    
    // Se ativou multi-table e ainda não carregou as tabelas, carrega agora
    if (enabled && availableTables.length === 0) {
        const sourceFilename = document.getElementById('source_filename').value;
        if (sourceFilename) {
            // Extrai connection info do source_filename (formato: MySQL.hostname)
            const parts = sourceFilename.split('.');
            if (parts.length >= 2) {
                loadAvailableTables('mysql_northwind', 'northwind');
            }
        }
    }
}

/**
 * Formata número com separadores de milhares
 */
function formatNumber(num) {
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ",");
}

/**
 * Mostra mensagem de erro
 */
function showError(message) {
    const container = document.getElementById('tables-container');
    if (container) {
        container.innerHTML = `<div class="error-message">${message}</div>`;
    }
}

/**
 * Valida seleção antes de submit
 */
function validateMultiTableSubmit() {
    const isMultiTable = document.getElementById('is_multi_table')?.checked;
    
    if (isMultiTable && selectedTables.size === 0) {
        alert('Por favor, selecione pelo menos uma tabela para processar.');
        return false;
    }
    
    return true;
}

// Adiciona validação ao formulário
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('meuFormulario');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateMultiTableSubmit()) {
                e.preventDefault();
            }
        });
    }
});
