# 🔧 Implementação: Git Sidebar Component

## ✅ Status: Componente Criado

**Arquivo**: `/src/codeigniter-app/app/Views/components/git-sidebar.php`

## 📋 Especificações Implementadas

### 1. localStorage Compartilhado
- ✅ Usa `gitConfig` único para ambas as páginas
- ✅ Sessão Git compartilhada (1 login serve ambos editores)

### 2. Filtro por Parâmetro
```php
// code-editor.php
<?php 
$fileFilter = '.parquet'; // ou '.sql'
include VIEWPATH . '/components/git-sidebar.php'; 
?>

// validation-rules-editor.php
<?php 
$fileFilter = '.py';
include VIEWPATH . '/components/git-sidebar.php'; 
?>
```

### 3. Deploy na Página
- ✅ Sidebar **não contém** botão de deploy
- ✅ Deploy fica em cada página específica

### 4. Estilo da code-editor.php
- ✅ Usa mesmos estilos CSS
- ✅ Mantém layout e cores idênticos

## 🎯 Como Funciona

### Comunicação via CustomEvent

```javascript
// Sidebar emite evento quando arquivo é selecionado:
window.dispatchEvent(new CustomEvent('git-file-selected', {
    detail: {
        filepath: 'validators/meu_arquivo.py',
        filename: 'meu_arquivo.py',
        content: '...conteúdo...',
        fileNode: {...}
    }
}));

// Página escuta e processa:
window.addEventListener('git-file-selected', (e) => {
    // Validar extensão (automático na sidebar)
    // Carregar no editor
    editor.setValue(e.detail.content);
    // Atualizar UI
    updateCurrentFile(e.detail.filename);
});
```

### Filtro Automático
```javascript
// Sidebar verifica ANTES de emitir evento:
if (!fileNode.fullPath.endsWith(window.gitSidebarFileFilter)) {
    alert(`❌ Apenas arquivos ${window.gitSidebarFileFilter} são permitidos`);
    return; // Não emite evento
}
```

## 🚀 Próximos Passos

### Para completar a integração:

1. **code-editor.php**:
   - Substituir `<div id="tab-git">...</div>` por `<?php include VIEWPATH . '/components/git-sidebar.php'; ?>`
   - Adicionar listener para `git-file-selected`
   - Setar `$fileFilter = '.parquet'` (ou '.sql')

2. **validation-rules-editor.php**:
   - Substituir `<div id="tab-git">...</div>` por `<?php include VIEWPATH . '/components/git-sidebar.php'; ?>`
   - Adicionar listener para `git-file-selected`
   - Setar `$fileFilter = '.py'`
   - **Manter** botão Deploy na página (não na sidebar)

## ⚠️ Importante

- Ambas páginas usarão o **mesmo** `git-file-manager.js`
- Ambas compartilharão `localStorage.gitConfig`
- Filtro de extensão é **validado na sidebar** antes de emitir evento
- Deploy permanece nas páginas individuais

## 📝 Código de Exemplo

### code-editor.php (parte relevante)
```php
<!-- Sidebar com Git -->
<aside class="code-sidebar">
    <?php 
    $fileFilter = '.parquet'; // ou '.sql'
    include VIEWPATH . '/components/git-sidebar.php'; 
    ?>
</aside>

<script>
// Escutar seleção de arquivo Git
window.addEventListener('git-file-selected', (e) => {
    const { content, filename } = e.detail;
    editor.setValue(content);
    currentGitFile = filename;
    document.getElementById('currentFileInfo').innerHTML = `📄 ${filename}`;
});
</script>
```

### validation-rules-editor.php (parte relevante)
```php
<!-- Sidebar com Git -->
<aside class="validation-sidebar">
    <?php 
    $fileFilter = '.py';
    include VIEWPATH . '/components/git-sidebar.php'; 
    ?>
</aside>

<script>
// Escutar seleção de arquivo Git
window.addEventListener('git-file-selected', (e) => {
    const { content, filename } = e.detail;
    editor.setValue(content);
    currentValidationFile = filename;
    document.getElementById('currentFileInfo').innerHTML = `📄 ${filename}`;
});
</script>
```

## ✨ Benefícios

- ✅ **DRY**: Sidebar em 1 único arquivo
- ✅ **Consistência**: Comportamento idêntico em ambas páginas
- ✅ **Manutenção**: Bug fix uma vez, funciona em todos
- ✅ **Flexível**: Filtro por extensão configurável
- ✅ **Desacoplado**: Comunicação via eventos
- ✅ **Sessão única**: 1 login GitHub serve ambos editores

---

**Pronto para aplicar nas páginas?** Aguardando sua confirmação para integrar o componente.
