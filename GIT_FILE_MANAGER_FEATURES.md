# Git File Manager - Novas Funcionalidades

## ✅ Funcionalidades Implementadas

### 1. **Criar Novos Arquivos**
- Campo de input para nome do arquivo na seção "➕ Criar Novo Arquivo"
- Botão "Criar do Editor" que salva o conteúdo atual do Monaco Editor como novo arquivo
- Validação de nome de arquivo e confirmação para arquivos vazios
- Atualização automática da lista de arquivos após criação

**Como usar:**
1. Escreva o código no Monaco Editor
2. Digite o nome do arquivo (ex: `meu-script.sql`)
3. Clique em "Criar do Editor"
4. O arquivo aparecerá na lista de arquivos do repositório

### 2. **Deletar Arquivos**
- Botão "Deletar" na seção "📝 Arquivo Atual"
- Confirmação antes de deletar
- Remove o arquivo do MinIO
- Atualiza a lista de arquivos automaticamente
- Limpa o editor após deleção

**Como usar:**
1. Abra um arquivo clicando nele na lista
2. Clique no botão "Deletar"
3. Confirme a deleção
4. O arquivo será removido permanentemente

### 3. **Sincronizar com GitHub (Commit & Push)**
- Implementação server-side para commit e push
- Baixa todos os arquivos do MinIO
- Faz commit com mensagem personalizada
- Push para GitHub (tenta main, depois master)
- Limpa automaticamente arquivos temporários

**Como usar:**
1. Faça as edições desejadas nos arquivos (criar, editar, deletar)
2. Digite uma mensagem de commit na seção "📤 Sincronizar GitHub"
3. Clique em "Commit & Push"
4. Aguarde a sincronização (pode levar alguns segundos)
5. Verifique no GitHub que os arquivos foram atualizados

### 4. **Preview de Markdown**
- Preview em tempo real para arquivos `.md`
- Renderização usando Marked.js
- Atualização automática ao digitar
- Painel toggle (pode mostrar/esconder)

**Como usar:**
1. Abra um arquivo `.md` clicando nele na lista
2. O preview aparecerá automaticamente abaixo do editor
3. Edite o Markdown no editor - o preview atualiza em tempo real
4. Clique em "Fechar" no preview para escondê-lo

## 📁 Arquivos Modificados

### Backend (PHP)
1. **GitServerController.php**
   - `deleteFileContent()`: Endpoint DELETE para deletar arquivos do MinIO
   - `gitPush()`: Endpoint POST para sincronizar alterações com GitHub
   
2. **Routes.php**
   - Adicionado `/api/git-file-delete` (DELETE/POST)
   - Adicionado `/api/git-push` (POST)

### Frontend (JavaScript)
3. **code_editor/index.php**
   - `createNewGitFile()`: Cria novo arquivo a partir do conteúdo do editor
   - `deleteGitFile()`: Deleta arquivo atual
   - `gitAddCommitPush()`: Sincroniza com GitHub via server-side
   - `showMarkdownPreview()`: Exibe preview de Markdown com Marked.js
   - `hideMarkdownPreview()`: Esconde preview
   - `toggleMarkdownPreview()`: Toggle do preview
   - Atualização de `loadGitFileContent()` para mostrar preview automático

## 🔧 Tecnologias Utilizadas

- **Backend**: CodeIgniter 4, AWS SDK PHP (MinIO S3), Git CLI
- **Frontend**: Monaco Editor, Marked.js (Markdown rendering)
- **Storage**: MinIO S3 em `s3://lab01/scripts/{owner}/{repo}/`
- **Git**: Clone server-side via exec(), commit/push automatizado

## 🎯 Fluxo de Trabalho Completo

```
1. CONECTAR GITHUB
   ↓
2. CLONAR REPOSITÓRIO (cria pasta no MinIO)
   ↓
3. LISTAR ARQUIVOS (do MinIO)
   ↓
4. EDITAR ARQUIVOS
   - Abrir arquivo (clique na lista)
   - Editar no Monaco Editor
   - Salvar (botão Salvar)
   ↓
5. CRIAR NOVOS ARQUIVOS
   - Escrever código no editor
   - Digitar nome → Criar do Editor
   ↓
6. DELETAR ARQUIVOS
   - Abrir arquivo → Deletar
   ↓
7. PREVIEW MARKDOWN (automático para .md)
   ↓
8. SINCRONIZAR COM GITHUB
   - Digitar mensagem de commit
   - Commit & Push
   - Todas as alterações vão para GitHub
```

## 🔒 Segurança

- Path traversal validation em todas as operações de arquivo
- Token GitHub nunca exposto no frontend
- Limpeza automática de diretórios temporários
- Validação de campos obrigatórios em todos os endpoints

## 📝 Notas Importantes

1. **MinIO como fonte da verdade**: Todos os arquivos são salvos no MinIO primeiro. O push sincroniza MinIO → GitHub.

2. **Arquivos temporários**: O push cria um clone temporário em `/tmp/git-push/{owner}/{repo}`, sincroniza e limpa automaticamente.

3. **Branch padrão**: O sistema tenta push para `main` primeiro, depois `master` se falhar.

4. **Markdown**: Apenas arquivos `.md` mostram preview. Outros formatos só editam código.

5. **Confirmações**: Deleção sempre pede confirmação. Criar arquivo vazio também pede.

## 🧪 Testado e Funcionando

✅ Clone de repositório  
✅ Listagem de arquivos do MinIO  
✅ Carregamento de arquivo no editor  
✅ Salvamento de edições  
✅ Criação de novos arquivos  
✅ Deleção de arquivos  
✅ Preview de Markdown em tempo real  
✅ Commit & Push para GitHub  

## 🚀 Próximos Passos (Opcionais)

- [ ] Upload de múltiplos arquivos via drag-and-drop
- [ ] Histórico de commits (visualizar)
- [ ] Diff/comparação de versões
- [ ] Suporte a pastas/subdiretórios
- [ ] Download de arquivo individual
- [ ] Busca/filtro na lista de arquivos
