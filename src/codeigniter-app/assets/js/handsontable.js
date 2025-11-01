// Função para buscar o CSV do servidor, converter para JSON e inicializar o Handsontable
function fetchCSVAndInitializeHandsontable(uploadedFilePath) {
    console.log('Caminho do arquivo CSV:', uploadedFilePath); // Verifica se o caminho está correto
    $.ajax({
        url: uploadedFilePath, // URL do CSV no servidor
        type: 'GET',
        dataType: 'text',
        success: function(csvData) {
            // Verifica se os dados CSV foram recebidos
            if (csvData) {
                console.log('Dados CSV recebidos:', csvData); // Mostra os dados CSV no console
                const conteudo = CSVToArray(csvData);
                // Inicialize o Handsontable com os dados convertidos do CSV
                initializeHandsontable(conteudo);
            } else {
                console.error('Nenhum dado CSV recebido.');
            }
        },
        error: function(err) {
            console.error('Erro ao carregar o CSV:', err);
            alert('Erro ao carregar o CSV: ' + err.statusText); // Alerta com a mensagem de erro
        }
    });
}


// Função para converter CSV em Array de Arrays (JSON) adequado para Handsontable
function CSVToArray(csvData) {
        console.log('Converting CSV to Array...');
        const rows = csvData.split('\n').map(row => {
            console.log('Processing row:', row);
            return row.split(',');
        });
        console.log('Conversion complete:', rows);
        return rows;
}


let handsontableInstance;
// Função para inicializar o Handsontable na Div spreadSheet com os dados JSON
function initializeHandsontable(data) {
    const container = document.getElementById('spreadSheet');
    
    if (!container) {
        console.error('O contêiner "spreadSheet" não foi encontrado.');
        return;
    }

    // Função auxiliar para verificar se uma string é uma URL de imagem
    function isImageUrl(url) {
        return (/\.(jpeg|jpg|gif|png|svg)$/i).test(url);
    }

    // Limpar caracteres de escape nos dados
    const cleanedData = limparCaracteresDeEscape(data);
    //Converte os dados para um formato de array simples.
    const tableData = cleanedData.map(row => Object.values(row));
    console.log('cleanedData.map : ', tableData);
    const colHeaders = Object.keys(cleanedData[0]);


    // Inicialize o Handsontable com os dados convertidos
    const hot = new Handsontable(container, {
        data: tableData, // Use os dados convertidos
        persistentState: true,
        colHeaders: colHeaders,
        //A linha abaixo torna a planilha com colunas fixas, impedindo add novas colunas
        rowHeaders: true,
        contextMenu: {
            items: {
                "row_above": { name: 'Inserir linha acima' },
                "row_below": { name: 'Inserir linha abaixo' },
                "col_left": {
                    name: 'Inserir coluna à esquerda',
                    callback: function () {
                        const selected = hot.getSelected();
                        if (selected && selected.length > 0) {
                            const colIndex = selected[0][1];
                            hot.alter('insert_col_start', colIndex, 1); // Inserir uma coluna à esquerda
                        }
                    },
                    disabled: function () {
                        const selected = hot.getSelected();
                        return !selected || selected[0][1] === undefined;
                    }
                },
                "col_right": {
                    name: 'Inserir coluna à direita',
                    callback: function () {
                        const selected = hot.getSelected();
                        if (selected && selected.length > 0) {
                            const colIndex = selected[0][1];
                            hot.alter('insert_col_end', colIndex + 1, 1); // Inserir uma coluna à direita
                        }
                    },
                    disabled: function () {
                        const selected = hot.getSelected();
                        return !selected || selected[0][1] === undefined;
                    }
                },
                "remove_row": { name: 'Excluir linha' },
                "remove_col": { name: 'Excluir coluna' },
                "set_type": {
                    name: 'Trocar tipo de coluna',
                    callback: function () {
                        const selected = hot.getSelected();
                        if (selected && selected.length > 0) {
                            const colIndex = selected[0][1]; // Pega o índice da coluna selecionada
                            setColumnType(colIndex, hot); // Chama a função para definir o tipo
                        } else {
                            alert("Nenhuma coluna selecionada.");
                        }
                    }
                },
                "set_cell_type": {
                    name: 'Trocar tipo da célula',
                    callback: function () {
                        const selected = hot.getSelected();
                        if (selected && selected.length > 0) {
                            const rowIndex = selected[0][0]; // Pega o índice da linha selecionada
                            const colIndex = selected[0][1]; // Pega o índice da coluna selecionada
                            const type = prompt("Digite o tipo da célula (text, image):");
                            setCellType(rowIndex, colIndex, type, hot); // Chama a função para definir o tipo
                        } else {
                            alert("Nenhuma célula selecionada.");
                        }
                    }
                },
                "---------": Handsontable.plugins.ContextMenu.SEPARATOR,
                "undo": { name: 'Desfazer (crtl+z)' },
                "redo": { name: 'Refazer (crtl+y)' }
            }
        },

        allowInsertColumn: true,
        allowInsertRow: true,
        allowRemoveColumn: true,
        allowRemoveRow: true,
        manualRowMove: true,
        manualColumnMove: true,
        colWidths: 200,
        height: 'auto',
        licenseKey: 'non-commercial-and-evaluation' // Use a licença de avaliação

        
    });

    // Após inicializar, definir os tipos de coluna baseados no conteúdo das células
    colHeaders.forEach((colKey, colIndex) => {
        for (let rowIndex = 0; rowIndex < cleanedData.length; rowIndex++) {
            if (isImageUrl(cleanedData[rowIndex][colKey])) {
                setColumnType(colIndex, hot, 'image');
                break;
            }
        }
    });

    handsontableInstance = hot;
}

function clearHandsontable(container) {
    // Inicialize o Handsontable com dados vazios
    
    const hot = new Handsontable(container, {
        data: [],
        colHeaders: true,
        rowHeaders: true,
        colWidths: 200,
        height: 'auto',
        licenseKey: 'non-commercial-and-evaluation' // Use a licença de avaliação
    });

    // Log para verificar se os dados foram zerados
    console.log('Dados da tabela após zerar:', hot.getData());
    // Limpa o conteúdo do contêiner
    container.innerHTML = '';
    hot.render();
}


function setColumnType(col, hot, type="") {
    
    if(!type)
        type = prompt("Digite o tipo da coluna (text, image):");
    
    if (type) {
        
        let columnSettings = hot.getSettings().columns;
        
        // Inicializa columnSettings se estiver undefined
        if (!columnSettings) {
            columnSettings = [];
            for (let i = 0; i < hot.countCols(); i++) {
                columnSettings.push({ type: 'text' });
            }
        }

        // Verifica se o índice da coluna é válido
        if (col >= 0 && col < columnSettings.length) {
            if (type === "image") {
                // Define um renderer para exibir imagens
                columnSettings[col] = {
                    type: 'text', // Usa 'text' para permitir URLs de imagem
                    renderer: function (instance, td, row, col, prop, value, cellProperties) {
                        const img = document.createElement('img');
                        img.src = value;
                        img.style.width = "80px";
                        img.style.height = "80px";
                        td.innerHTML = '';
                        td.appendChild(img);
                    }
                };
            } else {
                // Define a coluna como texto simples
                columnSettings[col] = { type: 'text' };
            }
            
            hot.updateSettings({ columns: columnSettings }); // Atualiza as configurações do Handsontable
            hot.render(); //Renderiza a imagem ou texto
            
            if(!type)
                alert(`Tipo da coluna ${col} atualizado para ${type}`);
        } else {
            if(!type)
                alert("Índice de coluna inválido.");
        }
    }
}

function setCellType(row, col, type, hot) {

    
    if (type === "image") {
        // Define um renderer para exibir imagens
        hot.setCellMeta(row, col, 'renderer', function (instance, td, row, col, prop, value, cellProperties) {
            const img = document.createElement('img');
            img.src = value;
            img.style.width = "80px";
            img.style.height = "80px";
            td.innerHTML = '';
            td.appendChild(img);
        });
    } else {
        // Remove o renderer personalizado para voltar ao tipo padrão
        hot.removeCellMeta(row, col, 'renderer');
    }
    
    hot.render(); // Re-renderiza a tabela para aplicar as mudanças
    alert(`Tipo da célula (${row}, ${col}) atualizado para ${type}`);
}

// Função para obter o tipo atual da coluna selecionada
function getColumnType(colIndex, hotInstance) {
    const columns = hotInstance.getSettings().columns;
    
    // Verificação adicional para garantir que 'columns' e 'colIndex' estejam definidos
    if (!Array.isArray(columns)) {
        console.error("Erro: 'columns' não é um array ou está indefinido.", columns);
        return "Tipo Desconhecido";
    }
    
    const column = columns[colIndex];
    
    if (!column) {
        console.error(`Erro: Coluna não encontrada no índice ${colIndex}.`, column);
        return "Tipo Desconhecido";
    }

    if (!column.hasOwnProperty('type')) {
        console.warn(`A coluna no índice ${colIndex} não possui uma propriedade 'type'.`, column);
        return "Tipo Desconhecido";
    }
    
    // Log para confirmar o tipo da coluna antes de retornar
    console.log(`Tipo de coluna encontrado no índice ${colIndex}:`, column.type);
    return column.type || "Tipo Desconhecido";
}



function downloadCSV() {
        
        const hotInstance = document.getElementById('spreadSheet').handsontableInstance;

        
        const data = hotInstance.getData();
        const csvContent = data.map(row => row.join(',')).join('\n');
        const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
        const link = document.createElement('a');

        if (navigator.msSaveBlob) { // Para IE 10+
            navigator.msSaveBlob(blob, 'data.csv');
        } else {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', 'data.csv');
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
}

function printColumnTypes(hotInstance) {

    const data = hotInstance.getData();
    const tableBody = document.getElementById('columnTypesTable').querySelector('tbody');
    tableBody.innerHTML = '';

    if (data.length > 0) {
        const columns = Object.keys(data[0]);

        columns.forEach((column, colIndex) => {
            const row = document.createElement('tr');
            const cellIndex = document.createElement('td');
            const cellType = document.createElement('td');

            cellIndex.textContent = colIndex;
            cellType.textContent = hotInstance.getCellMeta(0, colIndex).type || 'text'; // Obtém o tipo de coluna atual

            row.appendChild(cellIndex);
            row.appendChild(cellType);
            tableBody.appendChild(row);
        });
    }
}

/*
function getColumnHeader(data) {
    // Check for different configurations (fixed rows, etc.)
    if (data && data.length > 0 && data[0]) {
        return data[0]; // Assuming the first row contains the headers
    } else {
        return null;
    }
}
*/

function desescaparCaracteresUnicode(csv) {
    // Remove aspas extras, se necessário
    if (csv.startsWith('"') && csv.endsWith('"')) {
        csv = csv.slice(1, -1);
    }

    // Substituir sequências de quebra de linha por quebras reais
    csv = csv.replace(/\\r\\n|\\n/g, '\n');

    // Função para desescapar caracteres unicode como \u00e7 para "ç"
    csv = csv.replace(/\\u([\d\w]{4})/gi, function (match, grp) {
        return String.fromCharCode(parseInt(grp, 16));
    });

    return csv;
}

function limparCaracteresDeEscape(dados) {
    return dados.map(row => row.map(cell => cell.replace(/\r/g, '')));
}


function csvToJsonString(csv) {
    if (!csv || typeof csv !== 'string') {
        console.error("CSV inválido ou vazio");
        return []; // Retorna array vazio se o CSV não for válido
    }

    // Dividir o CSV em linhas, ignorando linhas vazias
    var lines = csv.split(/\r?\n/).filter(line => line.trim() !== '');
    console.log('Conteúdo dentro de csvToJsonString ');
    console.log(csv);
    if (lines.length < 2) {
        console.error("CSV não possui dados suficientes");
        return [];
    }

    var headers = lines[0].split(',');
    var jsonArr = [];

    for (var i = 1; i < lines.length; i++) {
        var currentline = lines[i].split(',');
        var obj = {};

        for (var j = 0; j < headers.length; j++) {
            obj[headers[j]] = currentline[j] ? currentline[j].trim().replace(/"/g, '\\"') : '';
        }
        jsonArr.push(obj);
    }

    return jsonArr;
}



function parseCSV(csv) {
    console.log('var csv input');
    console.log(csv);

    // Dividir o CSV em linhas, considerando quebras de linha
    var lines = csv.split(/\r?\n/);
    var result = [];

    // Extrair os cabeçalhos da primeira linha
    var headers = lines[0].split(',');

    console.log('Lines:', lines); // Log detalhado das linhas

    // Iterar sobre cada linha de dados (começando da segunda linha)
    for (var i = 1; i < lines.length; i++) {
        var obj = {};

        // Manter os campos separados respeitando vírgulas e aspas
        var currentline = lines[i].match(/(".*?"|[^",\s]+)(?=\s*,|\s*$)/g);

        // Log de cada linha processada
        console.log('Processing line:', currentline);

        // Preencher objeto com cabeçalho e valor correspondente
        for (var j = 0; j < headers.length; j++) {
            obj[headers[j]] = currentline ? currentline[j] : null;
        }
        result.push(obj);
    }

    console.log('Parsed Result:', result);
    return result;
}



/* function parseCSV(csv) {
    console.log('var csv input');
    console.log(csv);

    // Corrige o split usando expressão regular sem as aspas
    var lines = csv.split(/\r?\n/);
    var result = [];
    var headers = lines[0].split(',');

    console.log('Lines:', lines); // Log detalhado das linhas

    // Iterar sobre cada linha e separar os campos
    for (var i = 1; i < lines.length; i++) {
        var obj = {};
        var currentline = lines[i].split(',');

        // Log de cada linha processada
        console.log('Processing line:', currentline);

        for (var j = 0; j < headers.length; j++) {
            obj[headers[j]] = currentline[j];
        }
        result.push(obj);
    }

    console.log('Parsed Result:', result);
    return result;
} */


function parseCSVOld(csv) {

    console.log('var csv input');
    console.log(csv);

    var lines = csv.split(/\r?\n/);
    var result = [];
    var headers = lines[0].split(',');

    console.log('Lines:', lines); // Log detalhado das linhas

    // Iterar sobre cada linha e separar os campos
    for (var i = 1; i < lines.length; i++) {
        var obj = {};
        var currentline = lines[i].split(',');

        // Log de cada linha processada
        console.log('Processing line:', currentline);

        for (var j = 0; j < headers.length; j++) {
            obj[headers[j]] = currentline[j];
        }
        result.push(obj);
    }

    console.log('Parsed Result:', result);
    return result;
}


/* Esta função pega o conteúdo da handsontable na div e salva o conteúdo na sessão */
function salvarTabelaNaSessao() {
    
    const dados = handsontableInstance.getData();   

    console.log('Dados retornados pelo front-end:', dados);

    const xhr = new XMLHttpRequest();
    xhr.open('POST', '/salvarTabela', true);  // Ajuste a rota conforme necessário
    xhr.setRequestHeader('Content-Type', 'application/json;charset=UTF-8');
    
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                console.log('Dados salvos com sucesso');
            } else {
                console.error('Erro ao salvar dados:', xhr.status, xhr.statusText);
            }
        }
    };
    
    // Envia os dados como JSON
    xhr.send(JSON.stringify({ data: dados }));
}


function base64Decode(base64) {
    // Decodificar a string base64 para binário
    const binaryString = atob(base64);
    // Converter a string binária para um array de bytes (Uint8Array)
    const len = binaryString.length;
    const bytes = new Uint8Array(len);
    for (let i = 0; i < len; i++) {
        bytes[i] = binaryString.charCodeAt(i);
    }
    // Decodificar o array de bytes para uma string UTF-8
    const decoder = new TextDecoder('utf-8');
    return decoder.decode(bytes);
}



