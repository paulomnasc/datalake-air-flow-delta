# 🔗 GitHub Synchronization Upgrade - Validation Rules Editor

## Overview

The **Validation Rules Editor** has been upgraded to use the same production-grade Git implementation as the **Code Editor SQL** page. This upgrade brings:

- ✅ **Persistent Session Management** - GitHub connection stays active across page reloads and navigation
- ✅ **isomorphic-git Integration** - Direct Git operations with browser-based LightningFS
- ✅ **Hierarchical File Tree** - Visual directory structure for repository files
- ✅ **localStorage Persistence** - Automatic session restoration via `validationGitConfig` key
- ✅ **Real-time File Operations** - Load, save, create, and delete validation rules in GitHub
- ✅ **Sidebar UI** - Retractable sidebar matching Code Editor SQL design

## Key Changes

### Before (Basic Implementation)
```
✗ Simple form-based Git UI (inline form)
✗ Direct REST API calls to GitHub (not isomorphic-git)
✗ No cross-page session persistence
✗ No file tree rendering
✗ Session lost on page reload or navigation
```

### After (Production Implementation)
```
✓ Full sidebar with Git tab interface
✓ isomorphic-git + LightningFS for Git operations
✓ File tree with hierarchical folder structure
✓ localStorage persistence (key: validationGitConfig)
✓ Multiple restore entry points (DOMContentLoaded, window.load)
✓ Same robust architecture as Code Editor SQL
```

## Architecture

### Session Persistence Flow

```
Page Load
    ↓
DOMContentLoaded Event
    ↓
restoreGitFromStorage('DOMContentLoaded')
    ↓
Check localStorage.validationGitConfig
    ↓
If Found: Restore gitConfig + Load Files
    ↓
window.load Event
    ↓
restoreGitFromStorage('window-load')
    ↓
Session Active Across Pages
```

### Storage Key
```javascript
localStorage.setItem('validationGitConfig', JSON.stringify({
    owner: 'username',
    repo: 'validators',
    token: 'github_token',
    username: 'github_username',
    branch: 'main'
}));
```

## Usage Guide

### 1. Connect to GitHub

1. Click the **🔗 GitHub** button in the validation header
2. Fill in the connection form:
   - **GitHub Username**: Your GitHub username
   - **Personal Access Token**: Create at [GitHub Settings](https://github.com/settings/tokens/new)
     - Scope required: `repo` (full control of private repositories)
   - **Repository**: Format: `username/validators`

3. Click **✓ Conectar**
4. The sidebar will show `Conectado a username/validators`

### 2. Browse Repository Files

- **Expandable Folders**: Click folder arrows (▶/▼) to expand/collapse
- **Load File**: Click any `.py` file to load into the editor
- **File Icon Legend**:
  - 📁 Closed folder
  - 📂 Open folder
  - 📄 Python file

### 3. Work with Files

#### Load Existing File
```
1. Browse file tree in sidebar
2. Click file name
3. File loads into editor
4. "📝 Arquivo Atual" updates
```

#### Save Current File
```
1. Edit code in editor
2. Click "💾 Salvar" button in sidebar
3. File uploads to MinIO (persistent)
4. Success message appears (fades after 3s)
```

#### Create New File
```
1. Enter filename in "➕ Criar Novo Arquivo" field
2. Edit code in main editor
3. Click "✨ Criar do Editor"
4. File created in repository
5. File list refreshes automatically
```

#### Delete File
```
1. Load file by clicking in file tree
2. Click "🗑️ Deletar" button
3. Confirm deletion
4. File removed from repository
5. File list refreshes
```

### 4. Commit and Push to GitHub

```
1. Make changes to validation rules
2. Click "💾 Salvar" to persist to MinIO
3. In "📤 Sincronizar GitHub" section, enter commit message
4. Click "🚀 Commit & Push"
5. Changes sync to GitHub repository
6. Status message appears on success
```

## Persistence Behavior

### What Gets Saved?

**localStorage Entry**: `validationGitConfig`
```javascript
{
    "owner": "github_username",
    "repo": "validators", 
    "token": "ghp_...",
    "username": "github_username",
    "branch": "main"
}
```

### Restoration Triggers

The session restores automatically on these events:

1. **Page Load** → `DOMContentLoaded` event
2. **Window Load** → `window.load` event
3. **Manual Navigation** → Page refresh or direct URL visit

### Session Lifespan

- ✅ **Persists during**: Page reloads, tab switching, menu navigation
- ✅ **Persists until**: User disconnects (clicks "🔓 Desconectar") or clears localStorage
- ❌ **Not persists after**: Browser cache clear, private mode closure

## File Structure

### Recommended Repository Structure

```
validators/
├── bronze/
│   ├── null_check.py
│   ├── duplicate_remove.py
│   └── type_validation.py
├── silver/
│   ├── schema_validation.py
│   ├── data_quality.py
│   └── deduplication.py
└── gold/
    ├── business_rules.py
    └── aggregations.py
```

### Python Validator Template

```python
def validate(df):
    """
    Validate data in medallion layer
    
    Args:
        df: PySpark DataFrame
        
    Returns:
        Validated PySpark DataFrame
    """
    # Your validation logic
    return df
```

## Technical Implementation

### isomorphic-git Integration

**Libraries Loaded from CDN**:
- `isomorphic-git@1.25.7/index.umd.min.js` - Core Git operations
- `@isomorphic-git/lightning-fs@4.6.0/dist/lightning-fs.min.js` - Browser filesystem

**Custom HTTP Client**: Built-in for GitHub API requests with authentication

### API Endpoints Used

| Endpoint | Method | Purpose |
|----------|--------|---------|
| `/api/git-clone` | POST | Clone GitHub repository |
| `/api/git-files` | GET | List repository files |
| `/api/git-file-content` | GET | Load file content |
| `/api/git-file-save` | POST | Save file to MinIO |
| `/api/git-file-delete` | DELETE | Delete file from repository |
| `/api/git-push` | POST | Commit and push changes |

### Sidebar Elements

```html
<!-- Git Section in Sidebar -->
<div id="gitNotConnected">
  <!-- Connection form -->
</div>

<div id="gitConnected" style="display: none;">
  <!-- Connected state UI -->
  <!-- File tree: #gitFileTree -->
  <!-- Status messages: #git-success/error-message -->
  <!-- Operations: save, create, delete, push -->
</div>
```

## Troubleshooting

### Issue: "isomorphic-git tidak tersedia"

**Solution**:
1. Wait 10 seconds (libraries loading)
2. Check browser console (F12) for network errors
3. Verify CDN is accessible:
   ```javascript
   // In console:
   console.log(window.git); // Should show object
   console.log(window.LightningFS); // Should show class
   ```

### Issue: Session Lost After Page Reload

**Check**:
1. Open browser DevTools (F12)
2. Go to Application → LocalStorage
3. Look for `validationGitConfig` key
4. If missing, reconnect to GitHub

**Debug**:
```javascript
// In console:
localStorage.getItem('validationGitConfig');
```

### Issue: File Tree Not Showing Files

**Solution**:
1. Verify repository is public or token has `repo` scope
2. Check repository has Python files
3. Click "🔗 GitHub" button again to reload
4. Check console for errors (F12)

### Issue: Push to GitHub Fails

**Check**:
1. Token has `repo` scope
2. Repository is not read-only
3. Commit message is not empty
4. Network connection is active

## Migration from Old Implementation

### Removed
- ✗ Inline Git form (replaced with sidebar)
- ✗ Simple REST API calls (replaced with isomorphic-git)
- ✗ Form-based repository connection

### Migrated
- ✅ All Git functionality preserved
- ✅ Same API endpoints (backend compatible)
- ✅ Enhanced persistence mechanism

### Backward Compatibility
- ✅ Existing Git API endpoints still work
- ✅ File storage in MinIO unchanged
- ✅ All validation rules continue working
- ⚠️ Old localStorage entries ignored (use new key: `validationGitConfig`)

## Comparison with Code Editor SQL

| Feature | Code Editor SQL | Validation Rules |
|---------|---|---|
| Sidebar | ✓ Retractable | ✓ Retractable |
| Git Integration | isomorphic-git | isomorphic-git |
| File Tree | ✓ Hierarchical | ✓ Hierarchical |
| localStorage | gitConfig | validationGitConfig |
| Persistence | DOMContentLoaded + window.load | DOMContentLoaded + window.load |
| Tab System | 📁 Files / 🔗 Git | Integrated in sidebar |
| Editor | Monaco SQL | Monaco Python |

## References

### Related Documentation
- [GUIA_VALIDACOES_CUSTOMIZADAS.md](./GUIA_VALIDACOES_CUSTOMIZADAS.md) - Validation rules user guide
- [CODE_EDITOR_MONACO.md](./CODE_EDITOR_MONACO.md) - Code Editor SQL documentation
- [GIT_FILE_MANAGER_FEATURES.md](./GIT_FILE_MANAGER_FEATURES.md) - Git file manager details

### API References
- [isomorphic-git Documentation](https://isomorphic-git.org/)
- [GitHub REST API](https://docs.github.com/en/rest)
- [LightningFS Documentation](https://github.com/isomorphic-git/lightning-fs)

## Support

For issues or questions:
1. Check troubleshooting section above
2. Review browser console (F12)
3. Verify GitHub token and repository access
4. Check network connectivity

## Version History

### v2.0 (Current)
- ✨ Migrated to isomorphic-git
- ✨ Added sidebar persistent UI
- ✨ Full localStorage session persistence
- ✨ Hierarchical file tree rendering
- ✨ Multiple restore entry points

### v1.0 (Deprecated)
- Basic form-based Git integration
- Simple REST API calls
- No cross-page persistence
