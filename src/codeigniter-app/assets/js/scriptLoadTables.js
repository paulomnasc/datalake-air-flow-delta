

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

function handleText(csvText) {
    
    
    const timestamp = new Date().getTime();
    
            Papa.parse(csvText, {
                complete: function(results) {
                    tabelaOriginal = results.data;
                    console.log(tabelaOriginal); 
                    // Aqui você tem o array bidimensional
                    tabelaEmbaralhada = JSON.parse(JSON.stringify(tabelaOriginal));
                    totalRows = tabelaEmbaralhada.length - 1;
                    totalColumns = tabelaEmbaralhada[0].length - 1;
                    totalCards = totalRows * totalColumns;
                    criarTabela();
                }
            });
    
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
                    criarTabela();
                }
            });
        })
        .catch(error => console.error('Erro ao carregar o arquivo:', error));
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
            // Verifica e transforma links de imagem 
            
            //Se está na linha i = 0 (cabeçalho) não transformar em imagem
            if (i > 0 && j > 0)  
                verificarETransformarLinkImagem(cell);
            
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


function stringContemURL(str) {
    try {
      new URL(str);
      return true;
    } catch (_) {
      return false;
    }
  }

function verificarETransformarLinkImagem(cell) {
    const url = cell.innerText.trim();
    console.log(`Verificando URL: ${url}`);
    if(!stringContemURL(url))
        return;

    const img = document.createElement('img');
    img.src = url;
    img.alt = "Imagem";
    //img.style.maxWidth = "200px";
    //img.style.maxHeight = "200px";

    img.onload = function() {
        console.log(`Imagem carregada com sucesso: ${url}`);
        cell.innerHTML = '';
        cell.appendChild(img);
    };

    img.onerror = function() {
        console.error(`Erro ao carregar a imagem: ${url}`);
    };
}

/* 
function verificarETransformarLinkImagem(cell) {
    const url = cell.innerText.trim();
    const regexImagem = /\.(jpeg|jpg|gif|png|svg)$/i;

    if (regexImagem.test(url)) {
        const img = document.createElement('img');
        img.src = url;
        img.alt = "Imagem";
        img.style.maxWidth = "100px"; // Ajuste o tamanho conforme necessário
        img.style.maxHeight = "100px";

        // Tentar carregar a imagem primeiro
        img.onload = function() {
            console.log(`Imagem carregada com sucesso: ${url}`);
            // Substitui o conteúdo da célula pelo elemento img
            cell.innerHTML = '';
            cell.appendChild(img);
        };

        img.onerror = function() {
            console.error(`Erro ao carregar a imagem: ${url}`);
        };

        // Substitui o conteúdo da célula pelo elemento img
        cell.innerHTML = '';
        cell.appendChild(img);
    }
} */


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
    if(percentual == 100){
        window.alert('Teste finalizado !!');
        resetClock();
    } 
        
    statusBar.innerHTML = `
        <div class="status-bar-content">
            <div class="">Total de Acertos: ${totalAcertos}</div>
            <div class="">Total de Cartões: ${totalCards}</div>
            <div class="">Percentual de Acerto: ${percentual}%</div>
            <div class="">
                <button class="nav-button" 
                onclick="embaralhar(Boolean(getParameterByName('embaralhaapenaslinhas')), 
                getParameterByName('apenascolunaespecificada'))">
                Misturar
                    <i class="fas fa-bolt"></i>
                </button>
            
            </div>
        </div>
        
        
        <div class="">
                <h4><span id="cronometro">00:00:00</span></h4>
        </div>
        <br>
        <div class="w3-container w3-teal">
            <span id="mensagem-conclusao" style="display: ${percentual == 100 ? 'block' : 'none'};">Parabéns, você concluiu com 100% de acerto!</span>
        </div>
    `;
}


function startClock() {
    interval = setInterval(updateClock, 1000);
}

function getTime(){
    return interval;
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

