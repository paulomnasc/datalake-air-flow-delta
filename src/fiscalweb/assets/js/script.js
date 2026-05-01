function uploadArquivo() {

    var formData = new FormData();
    var fileInput = document.getElementById('arquivo');
    
    if (fileInput.files.length === 0) {
        alert('Por favor, selecione um arquivo.');
        return;
    }

    formData.append('arquivo', fileInput.files[0]);

    $.ajax({
        url: '../crud/upload.php',
        type: 'POST',
        data: formData,
        processData: false, // impede que o jQuery processe o FormData
        contentType: false, // impede que o jQuery adicione um cabeçalho Content-Type
        success: function(response) {
            console.log('Arquivo enviado com sucesso!');
            alert('Arquivo enviado com sucesso!');
            // Fazer algo com a resposta se necessário
        },
        error: function(jqXHR, textStatus, errorThrown) {
            console.log('Erro no upload: ' + textStatus);
        }
    });
}


function getFileName(path) {
    // Remove a query string, se houver
    var cleanPath = path.split('?')[0];
    // Encontra a posição do último caractere '/'
    var lastSlashIndex = cleanPath.lastIndexOf('/');
    // Extrai a substring a partir do último '/'
    var fileName = cleanPath.substring(lastSlashIndex + 1);
    return fileName;
}

// Suponha que você tenha o valor do id_perfil do usuário
//var idPerfilUsuario = 3; // Exemplo de valor obtido da tabela usuario
function setSelectedIndex(entity, id_entity) {
    var select = document.getElementById(entity);

    if (id_entity && select) {
        var valor = id_entity;
        console.log('Valor do campo hidden:', valor);

        // Verifica se a opção existe e seleciona
        for (var i = 0; i < select.options.length; i++) {
            if (select.options[i].value == valor) {
                select.selectedIndex = i;
                break;
            }
        }
    }
}


function listaQuadrosPastaSelecionada(id_pasta, id_usuario)
{
    console.log('Carregando quadros...');
    carregarListaIdDescricaoWhereCriteria('quadro', 'id_pasta', id_pasta, id_usuario);
}


//Função que preenche uma select ou outro componente visual de lista
function listarDadosItensSelecao(page, entity, keyToSearch, id = 0, addSelecioneItemMessage = false, callback) {
    
    var myUrl = '../crud/read.php'; 
    if (id > 0)
        myUrl = myUrl + '?fieldcriteria=' + keyToSearch + '&valuecriteria=' + id;
    
    console.log(myUrl);

    $.ajax({
        url: myUrl,
        type: 'GET',
        data: { table: entity },
        dataType: 'json',
        success: function(data) {
            var select = $('#'+ entity);
            var fileName = getFileName(page);
            select.empty(); // Limpar a select antes de preenchê-la novamente
            if (fileName.startsWith('add') || addSelecioneItemMessage === true)
                select.append('<option value="">Selecione uma opção</option>');
            
            $.each(data, function(index, item) {
                select.append('<option value="' + item.id + '">' + item.descricao + '</option>');
            });

            // Após a lógica estar completa
            if (callback) callback();    
        },
        error: function(xhr, status, error) {
            console.error('Erro ao carregar os dados:', status, error);
        }
    });
}

function loadContentLigth(page, nomeArquivoCSV) {
    
    var fileName = getFileName(page);
    
    console.log('Carregando quadro :' + nomeArquivoCSV);

    var xhr = new XMLHttpRequest();
    xhr.open('GET', page, true);
    xhr.onreadystatechange = function () {
        console.log('XHR readyState:', xhr.readyState, 'status:', xhr.status);
        if (xhr.readyState == 4 && xhr.status == 200) {
            var contentElement = document.getElementById('content');            
            if (contentElement) {
                document.getElementById('content').innerHTML = xhr.responseText;
            } else {
                console.error('Elemento com ID "content" não encontrado.');
            }

            loadTable(nomeArquivoCSV);
            
        } else if (xhr.readyState == 4) {
            console.error('Erro ao carregar a página:', page, 'Status:', xhr.status);
        }
    };
    xhr.send();
}



function loadContent(page, id_usuario_texto = "89", id_pasta = 0) {
//alert(id_usuario_texto);

    var id_usuario;
    
    if (typeof id_usuario_texto === 'string') {
        id_usuario = isNaN(id_usuario_texto.trim()) ? id_usuario_texto : parseFloat(id_usuario_texto.trim());
    } else {
        id_usuario = id_usuario_texto; // Ou trate o erro de acordo com sua lógica
    }
    
    
    var fileName = getFileName(page);
    const match = page.match(/\/(list|forms)\/(\w+)\.php/);
    var pageEntity = match ? match[2].toLowerCase().replace(/(list|add|upd)/g, '') : '';

    console.log('Carregando página:', fileName);
    
    var xhr = new XMLHttpRequest();
    xhr.open('GET', page, true);
    xhr.onreadystatechange = function () {
        if (xhr.readyState === 4) {
            if (xhr.status === 200) {
                var contentElement = document.getElementById('content');            
                if (contentElement) {
                    contentElement.innerHTML = xhr.responseText;

                    // Adiciona o evento change após o carregamento do conteúdo
                    var fileInput = document.getElementById('arquivo');
                    if (fileInput) {
                        fileInput.addEventListener('change', function() {
                            var fileName = this.files[0].name;
                            document.getElementById('nome_arquivo').value = fileName;
                        });
                    }

                } else {
                    console.error('Elemento com ID "content" não encontrado.');
                }

                if (fileName === 'listUsuario.php') {
                    carregarUsuarios();
                    $('#filtro-nome').on('input', function() {
                        carregarUsuarios($(this).val());
                    });
                } else if (['addUsuario.php', 'updUsuario.php'].includes(fileName)) {
                    carregarSelectPerfis(page);
                } else if (fileName.startsWith('add') || fileName.startsWith('upd')) {
                    const isAdd = fileName.startsWith('add');
                    
                    //Quando carregando as pastas de um usuário logado
                    if(pageEntity == "pasta")
                    {    
                        selectField = "id_usuario";
                        selectId = id_usuario;
                        selectEntity = pageEntity;
                    }else if(pageEntity == "quadro")
                    {     //Quando recuperando um Quadro ou add um novo Quadro listar as pastas do usuário logado
                        selectField = "id_usuario";
                        selectId = id_usuario;
                        selectEntity = "pasta";
                    }
                    //Quando carregando um select de pastas de um usuário para associar à uma pasta ou trocá-la   
                
                    console.log('if(fileName.startsWith(add) || fileName.startsWith(upd)) : ');
                    console.log('Parametros:');
                    console.log('Entidade : ' + selectEntity + 'Campo Chave: ' + selectField + 'Valor Chave: ' + selectId);
                    //Implementação de callback para que setarItemSelectComponent só exeute após
                    // listarDadosItensSelecao preencher a combo/lista 
                    listarDadosItensSelecao(page, selectEntity, selectField, selectId, isAdd, function() {
                        if (!isAdd) setarItemSelectComponent(pageEntity, selectEntity, selectId, id_pasta);
                    });

                } else if (fileName.startsWith('list')) {
                    if (pageEntity === "quadro") {
                        listarDadosItensSelecao(page, "pasta", "id_usuario", id_usuario, true);
                    } else {
                        listarEntidade(pageEntity, id_usuario);
                    }
                }

                verificarSeusuarioLogado(fileName);
            } else {
                console.error('Erro ao carregar a página:', page, 'Status:', xhr.status);
            }
        }

        
    };
    xhr.send();
}


function setarItemSelectComponent(pageEntity, selectEntity, selectId, id_pasta) {
    if (pageEntity == "quadro")
        setSelectedIndex(selectEntity, id_pasta);

    else
        setSelectedIndex(selectEntity, selectId);
}

function listarEntidade(pageEntity, id_usuario, id_pasta=0) {
    console.log('Carregando ' + pageEntity + '...');
    
    if (id_usuario > 0 && pageEntity.toLowerCase() != 'perfil') {
        carregarListaIdDescricaoWhereCriteria(pageEntity, 'id_usuario', id_usuario, id_usuario);
    } else if (id_pasta > 0 && pageEntity.toLowerCase() != 'quadro') {
        listaQuadrosPastaSelecionada(id_pasta, id_usuario)
    
    } else {

        carregarListaIdDescricao(pageEntity);
        $('#filtro-id').on('input', function () {
            var filtroNome = $(this).val();
            carregarListaIdDescricao(pageEntity, filtroNome);
        });
    }
}

function confirmarExclusao(id, page, id_usuario = 0, id_pasta = 0) {
    var pageEntity;
    var keyToSearch;
    const match = page.match(/list(\w+)\.php/);
    pageEntity = match ? match[1].toLowerCase() : '';
    
    // Definindo qual campo será usado para exibir a descrição
    if (pageEntity === 'usuario') {
        keyToSearch = "nome"; 
    } else { 
        keyToSearch = "descricao"; 
    }
    
    $.ajax({
        url: '../crud/getEntidade.php?table=' + pageEntity + '&keyToSearch=' + keyToSearch,
        type: 'GET',
        data: { id: id },
        dataType: 'json',
        success: function(data) {
            var descricao = (pageEntity === "usuario") ? data.nome : data.descricao;

            if (confirm("Tem certeza que deseja excluir o "  + pageEntity + " " + descricao + "?")) {
                $.ajax({
                    url: '../crud/delete.php',
                    type: 'POST',
                    data: { table: pageEntity, id: id },
                    success: function(response) {
                        alert("Dados excluídos com sucesso!");

                        // Atualizar a lista e o select "pasta" após a exclusão
                        if (pageEntity === "quadro") {
                            carregarListaIdDescricaoWhereCriteria(pageEntity, 'id_pasta', id_pasta);
                            listarDadosItensSelecao('listQuadro.php', 'pasta', 'id_usuario', id_usuario, true);
                        } else if (pageEntity === "usuario") {
                            carregarUsuarios();
                        } else {
                            if (id_usuario > 0) {
                                carregarListaIdDescricaoWhereCriteria(pageEntity, 'id_usuario', id_usuario);
                            } else {
                                carregarListaIdDescricao(pageEntity);
                            }
                        }
                    },
                    error: function(xhr, status, error) {
                        console.error("Erro na requisição AJAX:", error);
                    }
                });
            }
        },
        error: function(xhr, status, error) {
            console.error("Erro ao buscar o " + pageEntity + " :", error);
        }
    });
}


function toggleSidebar() {
    var sidebar = document.getElementById("sidebar");
    var mainContent = document.getElementById("main-content");
    if (sidebar.style.width === "250px") {
        sidebar.style.width = "0";
        mainContent.style.marginLeft = "0";
    } else {
        sidebar.style.width = "250px";
        mainContent.style.marginLeft = "250px";
    }
}

function submeterFormulario(formulario) {
    $.ajax({
        url: formulario, // Substitua pelo caminho do seu script PHP
        type: 'POST',
        data: $('#submitFormulario').serialize(),
        success: function(response) {
            // Atualiza a label com "Olá Visitante"
            if(page.fileName === "signout.php" )
                document.getElementById('usuario').value = "Olá Visitante";

            verificarSeusuarioLogado(formulario);
        },
        error: function(xhr, status, error) {
            console.error("Erro ao executar :" . url_param, error);
            console.log("Resposta do servidor:", xhr.responseText);
            alert("Erro ao processar a solicitação :\nStatus: " + url_param + status + "\nErro: " + error + "\nResposta: " + xhr.responseText);
        }

    });
}

function enviarFormulario(operacao, nomeEntidade) {
    
    
    var form = document.getElementById('meuFormulario');
    var id_usuario = document.getElementById('id_usuario').value;
    var formData = new FormData(form);
    var url_param;
    formData.forEach(function(value, key){
        console.log(key, value);
    });

    if(operacao=="create")
        url_param = '../crud/create.php';
    else
        url_param = '../crud/update.php'

    //alert(url_param);
    
    $.ajax({
        url: url_param,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log("Resposta do servidor:", response);
            if (response.page) {
                loadContent(response.page,id_usuario);
            } else if (response.error) {
                console.error("Erro retornado pelo update.php:", response.error);
                alert("Erro ao atualizar o registro: " + response.error);
            } else {
                console.error("Nenhuma página foi retornada pelo update.php");
            }
            //carregarListaIdDescricao(nomeEntidade);
            listarEntidade(nomeEntidade,id_usuario)
        },
        error: function(xhr, status, error) {
            console.error("Erro ao executar :" . url_param, error);
            console.log("Resposta do servidor:", xhr.responseText);
            alert("Erro ao processar a solicitação :\nStatus: " + url_param + status + "\nErro: " + error + "\nResposta: " + xhr.responseText);
        }
        
        
    });
    
}

function enviarFormulario(operacao, nomeEntidade, id) {
    
    alert('enviarFormulario(operacao, nomeEntidade, id)');
    var form = document.getElementById('meuFormulario');
    //var id_usuario = document.getElementById('id_usuario').value;
    var formData = new FormData(form);
    var url_param;
    var fileInput = document.getElementById('arquivo');

    if (fileInput && fileInput.files.length > 0) {
        console.log('Arquivo selecionado: ', fileInput.files[0].name);
        formData.append('arquivo', fileInput.files[0]);
        console.log('fileInput.files[0].length : ' + fileInput.files[0].length); 
        console.log('formData.length : ' + formData.length);
    } else {
        console.log('Nenhum arquivo selecionado.');
    }

    
    formData.forEach(function(value, key){
        console.log(key, value);
    });
    


    if(operacao=="create")
        url_param = '../crud/create.php';
    else
        url_param = '../crud/update.php'

    //alert(url_param);
    
    $.ajax({
        url: url_param,
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        dataType: 'json',
        success: function(response) {
            console.log("Resposta do servidor:", response);
            if (response.page) {
                loadContent(response.page,id);
            } else if (response.error) {
                console.error("Erro retornado pelo update.php:", response.error);
                alert("Erro ao atualizar o registro: " + response.error);
            } else {
                console.error("Nenhuma página foi retornada pelo update.php");
            }
            //carregarListaIdDescricao(nomeEntidade);
            //listarEntidade(nomeEntidade,id)
            console.log("A execução chegou após loadContent.");
            console.log("O conteúdo da var nomeEntidade = " + nomeEntidade.toLowerCase() + ' e o id=' + id);
            if (nomeEntidade.toLowerCase() === "quadro") {
                carregarListaIdDescricaoWhereCriteria(nomeEntidade, 'id_pasta', id);
                console.log("Entrou em : if (nomeEntidade == quadro");
            } else if (nomeEntidade.toLowerCase() === "usuario") {
                carregarUsuarios(); 
                console.log("Entrou em : if (nomeEntidade == usuario");
            } else if (id_usuario > 0) {
                carregarListaIdDescricaoWhereCriteria(nomeEntidade, 'id_usuario', id_usuario);
                console.log("Entrou em : if (id_usuario > 0");
            } else {
                carregarListaIdDescricao(nomeEntidade);
                console.log("Entrou em : Else");
            }
            
        },
        error: function(xhr, status, error) {
            console.error("Erro ao executar :" . url_param, error);
            console.log("Resposta do servidor:", xhr.responseText);
            alert("Erro ao processar a solicitação :\nStatus: " + url_param + status + "\nErro: " + error + "\nResposta: " + xhr.responseText);
        }
        
        
    });
    
}



// Função para montar a linha da tabela (tbody)
function montarLinhaTabela(entity, nomeEntidade, id_usuario, id_pasta = 0) {
    let linha = '<tr>';
    linha += '<td>' + entity.id + '</td>';
    linha += '<td>' + entity.descricao + '</td>';
    if(id_pasta > 0)
        linha += '<td>' + entity.nome_arquivo + '</td>';
        
    linha += '<td>';
    
    // Botões de ação
    linha += gerarBotoesAcoes(entity, nomeEntidade, id_usuario, id_pasta);
    
    linha += '</td>';
    linha += '</tr>';
    return linha;
}

// Função para gerar os botões de ação (editar e excluir)
function gerarBotoesAcoes(entity, nomeEntidade, id_usuario, id_pasta = 0) {
    let botoes = '';

    if (nomeEntidade === 'quadro') {
        // Adicionando botões de editar e excluir
        botoes += '<button class="edit-button" onclick="loadContent(\'../forms/upd' + nomeEntidade + '.php?id=' + entity.id + '\', ' + id_usuario + ', ' + entity.id_pasta + ')">✏️</button>';
        botoes += '<button class="delete-button" onclick="confirmarExclusao(' + entity.id + ', \'../forms/list' + nomeEntidade + '.php\', ' + id_usuario + ',' + id_pasta + ')">🗑️</button>';
            botoes += '<button class="play-button" onclick="loadContentLigth(\'../forms/playQuadro.php\', \'' + entity.nome_arquivo + '\')">▶️</button>';
    } else {
        // Gerando botões para outras entidades
        botoes += '<button class="edit-button" onclick="loadContent(\'../forms/upd' + nomeEntidade + '.php?id=' + entity.id + '\', ' + entity.id + ')">✏️</button>';
        botoes += '<button class="delete-button" onclick="confirmarExclusao(' + entity.id + ', \'../forms/list' + nomeEntidade + '.php\', ' + id_usuario + ')">🗑️</button>';
    }

    return botoes;
}


// Função para filtrar entidades pelo nome ou pela descrição
function filtrarEntidade(entity, nomeEntidade, filtroNome) {
    if (nomeEntidade === 'usuario') {
        return entity.nome.toLowerCase().includes(filtroNome.toLowerCase());
    } else {
        return entity.descricao.toLowerCase().includes(filtroNome.toLowerCase());
    }
}

// Função genérica para carregar lista com ou sem critério adicional
function carregarLista(nomeEntidade, url, id_usuario = 0, filtroNome = '', id_pasta = 0) {
    console.log('Carregando lista para: ' + nomeEntidade + ' com URL: ' + url);

    $.ajax({
        url: url,
        type: 'GET',
        data: { table: nomeEntidade },
        dataType: 'json',
        success: function (data) {
            let tbody = '';
            data.forEach(function (entity) {
                if (filtrarEntidade(entity, nomeEntidade, filtroNome)) {
                    tbody += montarLinhaTabela(entity, nomeEntidade, id_usuario, id_pasta);
                }
            });
            $('.data-table tbody').html(tbody);
        },
        error: function (xhr, status, error) {
            console.error("Erro na requisição AJAX:", error);
        }
    });
}

// Função principal para carregar lista sem critério adicional
function carregarListaIdDescricao(nomeEntidade, id_usuario = 0, filtroNome = '') {
    carregarLista(nomeEntidade, '../crud/read.php', id_usuario, filtroNome);
}

// Função principal para carregar lista com critério adicional (campo e valor)
function carregarListaIdDescricaoWhereCriteria(nomeEntidade, nomeCampoCriterio, valorCampoCriterio, id_usuario = 0, id_pasta = 0, filtroNome = '') {
    const url = '../crud/read.php?fieldcriteria=' + nomeCampoCriterio + '&valuecriteria=' + valorCampoCriterio;
    carregarLista(nomeEntidade, url, id_usuario, filtroNome);
}


///////////////////////////////////////////      LOAD TABLES      //////////////////////////////////////////////////////////////



var tabelaOriginal = [];
var tabelaEmbaralhada = [];
var seconds = 0;
var minutes = 0;
var hours = 0;
var interval;
var totalAcertos = 0;
var totalRows = 0;
var totalColumns = 0;
var totalCards = 0;

function getParameterByName(name, url = window.location.href) {
    name = name.replace(/[\[\]]/g, '\\$&');
    var regex = new RegExp('[?&]' + name + '(=([^&#]*)|&|#|$)'),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, ' '));
}

function handleFile(fileName) {
    //window.alert(fileName);
    const timestamp = new Date().getTime();
    
    fetch(`${fileName}?t=${timestamp}`)
        .then(response => response.text())
        .then(csvText => {
            Papa.parse(csvText, {
                complete: function(results) {
                    tabelaOriginal = results.data;
                    console.log(tabelaOriginal); 
                    // Aqui você tem o array bidimensional
                    tabelaEmbaralhada = JSON.parse(JSON.stringify(tabelaOriginal));
                    totalRows = tabelaEmbaralhada.length - 1;
                    totalColumns = tabelaEmbaralhada[0].length - 1;
                    totalCards = totalRows * totalColumns;
                    console.log("CSV carregado com êxito!");
                    criarTabela();
                }
            });
        })
        .catch(error => console.error('Erro ao carregar o arquivo:', error));
}

// Exemplo de chamada da função
function loadTable(nomeArquivoCSV){

    //window.alert('Executou function loadTable para :' + nomeArquivoCSV);
    /* if (typeof Papa !== 'undefined') {
        alert("PapaParse carregado com sucesso!");
    } else {
        alert("PapaParse não está definido.");
    } */
    handleFile("../data/" + nomeArquivoCSV);
}



function criarTabela() {

   

    const table = document.getElementById("tabela");
    table.innerHTML = "";
    for (let i = 0; i < tabelaEmbaralhada.length; i++) {
        const row = table.insertRow();
        for (let j = 0; j < tabelaEmbaralhada[i].length; j++) {
            const cell = row.insertCell();
            cell.innerHTML = tabelaEmbaralhada[i][j];
            // Adicionando logs para depuração
            console.log(`Verificando célula [${i}, ${j}]: "${cell.innerText.trim()}"`);
            if ((i === 0 || j === 0))  {
                cell.classList.add("fixed");
            } else {
                cell.draggable = true;
                cell.ondragstart = dragStart;
                cell.ondragover = dragOver;
                cell.ondrop = drop;
                
            }
        }
    }
    atualizarStatus();
}

function embaralhar(embaralharLinhas = false, coluna = null) {
    totalAcertos = 0;
    //window.alert(embaralharLinhas);
    //window.alert(coluna);
    if (embaralharLinhas) {
        if (coluna !== null) {
            // Embaralha apenas a coluna especificada
            for (let i = 1; i < tabelaEmbaralhada.length; i++) {
                const i1 = Math.floor(Math.random() * (tabelaEmbaralhada.length - 1)) + 1;
                [tabelaEmbaralhada[i][coluna], tabelaEmbaralhada[i1][coluna]] = [tabelaEmbaralhada[i1][coluna], tabelaEmbaralhada[i][coluna]];
            }
        } else {
            // Embaralha todas as linhas
            for (let i = 1; i < tabelaEmbaralhada.length; i++) {
                const i1 = Math.floor(Math.random() * (tabelaEmbaralhada.length - 1)) + 1;
                [tabelaEmbaralhada[i], tabelaEmbaralhada[i1]] = [tabelaEmbaralhada[i1], tabelaEmbaralhada[i]];
            }
        }
    } else {
        // Embaralha tanto as linhas quanto as colunas
        for (let i = 1; i < tabelaEmbaralhada.length; i++) {
            for (let j = 1; j < tabelaEmbaralhada[i].length; j++) {
                const i1 = Math.floor(Math.random() * (tabelaEmbaralhada.length - 1)) + 1;
                const j1 = Math.floor(Math.random() * (tabelaEmbaralhada[i].length - 1)) + 1;
                [tabelaEmbaralhada[i][j], tabelaEmbaralhada[i1][j1]] = [tabelaEmbaralhada[i1][j1], tabelaEmbaralhada[i][j]];
            }
        }
    }
    
    criarTabela();
    startClock();
}


function dragStart(event) {
    event.dataTransfer.setData("text", event.target.innerHTML);
    event.dataTransfer.setData("id", event.target.cellIndex + "," + event.target.parentNode.rowIndex);
}

function dragOver(event) {
    event.preventDefault();
}

function drop(event) {
    event.preventDefault();
    const data = event.dataTransfer.getData("text");
    const [fromX, fromY] = event.dataTransfer.getData("id").split(",").map(Number);
    const toX = event.target.cellIndex;
    const toY = event.target.parentNode.rowIndex;

    if (toX > 0 && toY > 0) {
        [tabelaEmbaralhada[fromY][fromX], tabelaEmbaralhada[toY][toX]] = [tabelaEmbaralhada[toY][toX], tabelaEmbaralhada[fromY][fromX]];
        criarTabela();
        verificarPosicao(Boolean(getParameterByName('verificarColuna')));
    }
}

function verificarPosicao(verificarColuna = false) {
    totalAcertos = 0;
    for (let i = 1; i < tabelaEmbaralhada.length; i++) {
        for (let j = 1; j < tabelaEmbaralhada[i].length; j++) {
            const cell = document.getElementById("tabela").rows[i].cells[j];
            if (!verificarColuna) {
                if (tabelaEmbaralhada[i][j] === tabelaOriginal[i][j]) {
                    cell.classList.add("correct");
                    cell.draggable = false;
                    totalAcertos++;
                    atualizarStatus();
                } else {
                    cell.classList.remove("correct");
                    cell.draggable = true;
                }
            }
        }
    }

    if (verificarColuna) {
        for (let j = 1; j < tabelaEmbaralhada[0].length; j++) {
            for (let i = 1; i < tabelaEmbaralhada.length; i++) {
                if (tabelaEmbaralhada[i][j] !== tabelaOriginal[i][j]) {
                    for (let k = 1; k < tabelaOriginal.length; k++) {
                        if (tabelaEmbaralhada[i][j] === tabelaOriginal[k][j]) {
                            const origem = document.getElementById("tabela").rows[i].cells[j];
                            const destino = document.getElementById("tabela").rows[k].cells[j];
                            [origem.innerHTML, destino.innerHTML] = [destino.innerHTML, origem.innerHTML];
                            origem.classList.remove("correct");
                            origem.draggable = true;
                            destino.classList.add("correct");
                            destino.draggable = false;
                            totalAcertos++;
                            atualizarStatus();
                            break;
                        }
                    }
                }
            }
        }
    }
}


function atualizarStatus() {
    const statusBar = document.getElementById("status-bar");
    const percentual = totalAcertos > 0 ? (totalAcertos / totalCards * 100).toFixed(2) : 0;
    statusBar.innerHTML = `
        <div class="container">
            <div class="box">Total de Acertos: ${totalAcertos}</div>
            <div class="box">Total de Cartões: ${totalCards}</div>
            <div class="box">Percentual de Acerto: ${percentual}%</div>
        </div>
    `;
}

function startClock() {
    interval = setInterval(updateClock, 1000);
}

function pauseClock() {
    clearInterval(interval);
}

function resetClock() {
    clearInterval(interval);
    seconds = 0;
    minutes = 0;
    hours = 0;
    document.getElementById("cronometro").innerHTML = "00:00:00";
}

function updateClock() {

    seconds++;
    if (seconds == 60) {
        seconds = 0;
        minutes++;
    }
    if (minutes == 60) {
        minutes = 0;
        hours++;
    }
    document.getElementById("cronometro").innerHTML =
        ("0" + hours).slice(-2) + ":" +
        ("0" + minutes).slice(-2) + ":" +
        ("0" + seconds).slice(-2);
}


