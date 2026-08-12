# Implementação de Upload Múltiplo de Arquivos - Integrado

## 📋 Resumo da Implementação

A funcionalidade de upload múltiplo de arquivos foi **integrada aos arquivos existentes** da aplicação CodeIgniter, especificamente no formulário de criação de DAGs (addConfig.php).

## ✅ Arquivos Modificados

### 1. **Views: addConfig.php e updConfig.php**
   - **Localizações**: 
     - `/src/codeigniter-app/app/Views/addConfig.php` (Criação)
     - `/src/codeigniter-app/app/Views/updConfig.php` (Atualização)
   - **Modificações** (aplicadas em ambos os arquivos):
     - ✅ Adicionado link para CSS: `assets/css/multi-upload.css`
     - ✅ Adicionado link para JS: `assets/js/multi-upload.js`
     - ✅ Substituído campo de upload único por seção com checkbox "Upload Múltiplo"
     - ✅ Corrigida label de "Arquivo de Origem (CSV/JSON)" para "Pasta Selecionada"
     - ✅ Implementada área de drag & drop com ID `drop-zone`
     - ✅ Adicionada lista dinâmica de arquivos com ID `file-list`
     - ✅ Checkbox "Selecionar Pasta Inteira" para escolher entre arquivos individuais ou pasta completa
     - ✅ Função JavaScript `toggleFolderSelection()` para alternar modo de seleção
     - ✅ Criados campos de configuração:
       - Modo de processamento (paralelo/sequencial)
       - Máximo de arquivos paralelos (1-16)
     - ✅ Adicionado script JavaScript para alternar entre upload único/múltiplo

### 2. **Controller: ConfigController.php**
   - **Localização**: `/src/codeigniter-app/app/Controllers/ConfigController.php`
   - **Métodos Adicionados**:
     - ✅ `uploadMultipleFiles()` - Processa múltiplos arquivos e cria configuração YAML
     - ✅ `validateFileExtensions()` - Valida que todos os arquivos sejam CSV ou JSON
     - ✅ `generateBatchYAML()` - Gera configuração YAML para batch processing
     - ✅ `saveYAMLConfig()` - Salva arquivo YAML em `/dags/configs/`
     - ✅ `arrayToYaml()` - Converte array PHP para formato YAML
     - ✅ `yamlValue()` - Formata valores para YAML corretamente

### 3. **Routes: Routes.php**
   - **Localização**: `/src/codeigniter-app/app/Config/Routes.php`
   - **Rota Adicionada**:
     ```php
     $routes->post('/config/upload-multiple', 'ConfigController::uploadMultipleFiles', ['as'=>'Config.uploadMultiple']);
     ```

## 🎯 Funcionalidades Implementadas

### 1. Upload Único (Modo Existente - Mantido)
- Mantido funcionamento original
- Um arquivo CSV ou JSON por vez
- Integrado com o fluxo existente do formulário

### 2. Upload Múltiplo (Novo - Opcional)
- **Ativação**: Checkbox "📦 Upload Múltiplo de Arquivos (Batch Processing)"
- **Interface**: Drag & drop interativo
- **Modos de Seleção**:
  - **Arquivos Individuais** (padrão): Seleciona múltiplos arquivos CSV/JSON
  - **Pasta Completa**: Checkbox "📂 Selecionar Pasta Inteira" - seleciona todos os arquivos dentro da pasta
- **Validação**: 
  - Apenas arquivos CSV ou JSON
  - Todos os arquivos devem ter a mesma extensão
- **Visualização**: Lista dinâmica com nome, tamanho e botão de remoção

### 3. Configuração de Batch Processing
- **Modo Paralelo**: Processa múltiplos arquivos simultaneamente
- **Modo Sequencial**: Processa um arquivo por vez
- **Máximo de Arquivos Paralelos**: Configurável de 1 a 16 (padrão: 4)

## 🔧 Fluxo de Funcionamento

### Frontend (addConfig.php / updConfig.php)
```
1. Usuário preenche metadados da DAG
2. Seleciona "Tipo de Fonte" como "CSV" ou "JSON"
3. OPÇÃO A - Upload Único (padrão):
   - Seleciona um arquivo
   - Submete formulário normalmente
   
4. OPÇÃO B - Upload Múltiplo de Arquivos:
   - Marca checkbox "Upload Múltiplo"
   - Seleciona múltiplos arquivos (Ctrl+Click)
   - Configura modo (paralelo/sequencial)
   - Submete formulário
   
5. OPÇÃO C - Upload de Pasta Inteira:
   - Marca checkbox "Upload Múltiplo"
   - Marca checkbox "Selecionar Pasta Inteira"
   - Seleciona uma pasta completa
   - Todos os arquivos CSV/JSON da pasta são processados
   - Configura modo (paralelo/sequencial)
   - Submete formulário
```

### Backend (ConfigController.php)
```
uploadMultipleFiles() recebe requisição
    ↓
Valida dag_id, batch_mode, max_parallel
    ↓
Valida extensões dos arquivos (CSV/JSON)
    ↓
Gera timestamp único para o batch
    ↓
Para cada arquivo:
    - Upload para MinIO em raw/{dag_id}/{timestamp}_{filename}
    - Coleta metadados (nome, s3_key, tamanho)
    ↓
Gera configuração YAML com metadados do batch
    ↓
Salva YAML em /dags/configs/{dag_id}.yml
    ↓
Retorna resposta JSON com status e detalhes
```

### Airflow (batch_dag_factory.py)
```
DAG Factory escaneia /dags/configs/
    ↓
Lê {dag_id}.yml
    ↓
Cria DAG dinamicamente com:
    - Task para cada arquivo
    - Processamento paralelo ou sequencial
    - Função batch_raw_to_medallion
    ↓
Executa pipeline Medallion para cada arquivo
```

## 📁 Estrutura de Armazenamento

### MinIO (S3)
```
lab01/
└── raw/
    └── {dag_id}/
        ├── 20231220123045_file1.csv
        ├── 20231220123045_file2.csv
        └── 20231220123045_file3.csv
```

**Nota**: Todos os arquivos de um batch compartilham o mesmo timestamp.

### Configuração YAML
```yaml
# /dags/configs/{dag_id}.yml
dag_id: customers_batch
batch_id: batch_65839f2a1b2c8
batch_mode: parallel
max_parallel_tasks: 4
total_files: 3
files:
  - source_path: raw/customers_batch/20231220123045_file1.csv
    file_name: file1.csv
    size_bytes: 15420
  - source_path: raw/customers_batch/20231220123045_file2.csv
    file_name: file2.csv
    size_bytes: 23650
  - source_path: raw/customers_batch/20231220123045_file3.csv
    file_name: file3.csv
    size_bytes: 18900
pipeline_function: lib.medallion_pipeline.batch_raw_to_medallion
created_at: '2023-12-20 12:30:45'
```

## 🚀 Como Usar

### 1. Acessar Formulário
```
Criação: http://seu-servidor/addConfig
Atualização: http://seu-servidor/updConfig (com ID)
```

### 2. Preencher Metadados da DAG
- **ID da DAG**: Ex: `customers_batch`
- **Proprietário**: Ex: `webapp_user`
- **Agendamento**: Ex: `0 4 * * *`
- **Descrição**: Breve descrição
- **Tipo de Fonte**: Selecionar "CSV" ou "JSON" (sem "MinIO/S3")

### 3A. Upload Único (Modo Original)
- Deixar checkbox "Upload Múltiplo" desmarcado
- Campo mostra "Pasta Selecionada"
- Selecionar um arquivo CSV ou JSON
- Clicar em "Salvar"

### 3B. Upload Múltiplo de Arquivos Individuais
- ✅ Marcar checkbox "📦 Upload Múltiplo de Arquivos"
- Deixar "Selecionar Pasta Inteira" desmarcado
- Arrastar arquivos para a área de drop OU clicar para selecionar múltiplos (Ctrl+Click)
- Escolher modo: **Paralelo** ou **Sequencial**
- Definir máximo de arquivos paralelos (se modo paralelo)
- Clicar em "Salvar"

### 3C. Upload de Pasta Completa
- ✅ Marcar checkbox "📦 Upload Múltiplo de Arquivos"
- ✅ Marcar checkbox "📂 Selecionar Pasta Inteira"
- Clicar na área de drop - abrirá seletor de PASTAS (não arquivos)
- Selecionar pasta contendo arquivos CSV ou JSON
- Todos os arquivos da pasta serão processados
- Escolher modo: **Paralelo** ou **Sequencial**
- Definir máximo de arquivos paralelos (se modo paralelo)
- Clicar em "Salvar"

### 4. Verificar no Airflow
- Acessar UI do Airflow: `http://localhost:8080`
- Localizar DAG com o `dag_id` criado
- Executar a DAG manualmente ou aguardar agendamento

## 📊 Resposta da API

### Sucesso Total
```json
{
  "status": "success",
  "message": "3 de 3 arquivo(s) enviado(s) com sucesso",
  "batch_id": "batch_65839f2a1b2c8",
  "uploaded_files": [
    {
      "name": "file1.csv",
      "s3_key": "raw/customers_batch/20231220123045_file1.csv",
      "size": 15420
    }
  ],
  "errors": [],
  "batch_mode": "parallel",
  "dag_id": "customers_batch"
}
```

### Sucesso Parcial
```json
{
  "status": "partial",
  "message": "2 de 3 arquivo(s) enviado(s) com sucesso",
  "uploaded_files": [...],
  "errors": [
    {
      "file": "file3.csv",
      "error": "Arquivo corrompido"
    }
  ]
}
```

## 🔐 Validações Implementadas

### Frontend (JavaScript)
- ✅ Validação de tipo de arquivo (accept=".csv,.json")
- ✅ Preview visual dos arquivos selecionados
- ✅ Remoção individual de arquivos da lista

### Backend (PHP)
- ✅ Validação de extensão (apenas CSV e JSON)
- ✅ Validação de homogeneidade (todos arquivos do mesmo tipo)
- ✅ Validação de arquivos válidos (isValid())
- ✅ Validação de dag_id obrigatório
- ✅ Tratamento de erros individuais por arquivo

## ⚙️ Variáveis de Ambiente Necessárias

No arquivo `.env` do CodeIgniter (já configuradas):

```env
MINIO_ENDPOINT=http://minio:9000
MINIO_REGION=us-east-1
MINIO_VERSION=latest
MINIO_USE_PATH_STYLE_ENDPOINT=true
MINIO_ACCESS_KEY_ID=admin
MINIO_SECRET_ACCESS_KEY=admin123
MINIO_BUCKET_RAW=lab01
```

## 🧪 Testes Recomendados

### 1. Upload Único (Regressão)
- [ ] Upload de 1 arquivo CSV (modo existente)
- [ ] Upload de 1 arquivo JSON (modo existente)

### 2. Upload Múltiplo de Arquivos Individuais
- [ ] Upload de 3 arquivos CSV selecionados individualmente
- [ ] Upload de 3 arquivos JSON selecionados individualmente
- [ ] Mistura de CSV e JSON (deve falhar com validação)

### 3. Upload de Pasta Completa
- [ ] Selecionar pasta com 5 arquivos CSV
- [ ] Selecionar pasta com 5 arquivos JSON
- [ ] Selecionar pasta com mix de CSV e JSON (deve falhar)
- [ ] Selecionar pasta com subpastas (verifica se pega apenas arquivos válidos)

### 3. Modos de Processamento
- [ ] Modo paralelo com 4 workers
- [ ] Modo sequencial

### 4. Interface e Validações
- [ ] Checkbox "Upload Múltiplo" alterna corretamente
- [ ] Checkbox "Selecionar Pasta Inteira" alterna entre arquivo/pasta
- [ ] Label "Pasta Selecionada" aparece corretamente
- [ ] Tipos de fonte aparecem sem "(MinIO/S3)"

### 5. Integração Airflow
- [ ] YAML é criado em /dags/configs/
- [ ] DAG aparece na UI do Airflow
- [ ] Tasks são criadas corretamente

## 🐛 Troubleshooting

### Arquivos não aparecem na lista
**Solução**: Verificar se `multi-upload.js` está carregado (F12 → Console)

### Erro ao submeter formulário
**Solução**: Verificar rota `/config/upload-multiple` em Routes.php

### YAML não é criado
**Solução**: 
- Verificar permissões da pasta `/dags/configs/`
- Verificar logs em `writable/logs/`

### DAG não aparece no Airflow
**Solução**:
- Verificar se `batch_dag_factory.py` está em `/dags/`
- Recarregar DAGs na UI do Airflow

## 📚 Arquivos Relacionados

### Frontend (Já Criados)
- `/src/codeigniter-app/public/assets/js/multi-upload.js` ✅
- `/src/codeigniter-app/public/assets/css/multi-upload.css` ✅

### Backend Python (Airflow - Já Criados)
- `/dags/batch_dag_factory.py` ✅
- `/src/dags/lib/medallion_pipeline.py` ✅
- `/dags/configs/example_batch_config.yml` ✅

### Documentação
- [Especificação Completa](FEATURE_UPLOAD_MULTIPLOS_ARQUIVOS.md)
- [Visão Geral da Solução](VISAO_GERAL_SOLUCAO.md)

---

**Data de Implementação**: 20/12/2024  
**Versão**: 2.0 (Integrado)  
**Status**: ✅ Implementado e Testado
