# 🔧 Quick Troubleshooting Guide - Deploy Button

## 🚨 Problem: Button not appearing

### Checklist
```
[ ] Page fully loaded? → Press F5 to refresh
[ ] Using correct URL? → /validation-rules-editor
[ ] JavaScript enabled? → Check browser settings
[ ] Browser cached? → Ctrl+Shift+Del (clear cache)
[ ] No console errors? → Press F12 → Console tab
```

### Solution
```bash
# Clear browser cache
Ctrl + Shift + Delete (or Cmd + Shift + Delete on Mac)

# Hard refresh page
Ctrl + F5 (or Cmd + Shift + R on Mac)

# Check if route exists
POST /api/validation-deploy in Routes.php
```

---

## 🚨 Problem: Button greyed out / disabled

### Checklist
```
[ ] Editor has code? → Write something
[ ] File is open? → Select file from Git sidebar
[ ] Not already deploying? → Wait for previous to finish
```

### Solution
```
1. Write code in editor
2. Click 💾 Salvar first
3. Then click 🚀 Implantar
```

---

## 🚨 Problem: Error "Editor vazio"

### Checklist
```
[ ] Code in editor? → Type or paste code
[ ] Code visible? → Check Monaco loaded
[ ] File opened? → Select from sidebar
```

### Solution
```
1. Click in editor area
2. Type or paste Python code
3. Verify code appears
4. Try deploy again
```

---

## 🚨 Problem: Error "Nenhum arquivo aberto"

### Checklist
```
[ ] File selected in sidebar? → Git sidebar shows file name
[ ] Sidebar visible? → Click "GitHub Files" if hidden
[ ] Connected to GitHub? → Connect first
```

### Solution
```
1. Open editor: /validation-rules-editor
2. Click GitHub Files button (if hidden)
3. Select a file or create new one
4. Try deploy again
```

---

## 🚨 Problem: Confirmation dialog doesn't appear

### Checklist
```
[ ] Pop-ups blocked? → Check browser permissions
[ ] JavascriptEnabled? → Check console (F12)
[ ] No errors? → Check F12 → Console tab
```

### Solution
```
# Allow pop-ups for this domain
Browser Settings → Privacy & Security → Pop-ups & Redirects
→ Add your domain to exceptions

# Or check console for errors
F12 → Console → Look for red messages
```

---

## 🚨 Problem: Button says "⏳ Implantando..." but nothing happens

### Checklist
```
[ ] Network working? → Test other sites
[ ] API endpoint exists? → Check Routes.php
[ ] Controller method exists? → Check ValidationRulesController.php
[ ] Server running? → Check Docker logs
```

### Solution
```bash
# Check server logs
docker logs [container-name]

# Check if endpoint is registered
grep "validation-deploy" app/Config/Routes.php

# Test API manually
curl -X POST http://localhost/api/validation-deploy \
  -H "Content-Type: application/json" \
  -d '{"filename":"test.py"}'
```

---

## 🚨 Problem: API returns error

### Checklist
```
[ ] Error message clear? → Read error text
[ ] Script exists? → Check sync_validators_to_airflow.sh
[ ] Filename valid? → Only alphanumeric, dot, hyphen, underscore
[ ] Permissions? → Check file permissions
```

### Solution by Error Type

#### Error: "Script não disponível"
```bash
# Check if script exists
ls -la sync_validators_to_airflow.sh

# Make executable
chmod +x sync_validators_to_airflow.sh

# Check permissions
sudo chown $USER:$USER sync_validators_to_airflow.sh
```

#### Error: "Falha ao sincronizar"
```bash
# Check Airflow container
docker ps | grep airflow

# Check Airflow logs
docker logs [airflow-container] --tail 50

# Test Docker access
docker ps
```

#### Error: "Python syntax error"
```bash
# Check Python syntax locally
python -c "import seu_validador"

# Or run full file
python seu_validador.py
```

---

## 🚨 Problem: DAG doesn't appear in Airflow

### Checklist
```
[ ] Waited 30 seconds? → Airflow scanner interval
[ ] Airflow running? → docker ps shows airflow
[ ] DAG ID correct? → Check dag_configurations table
[ ] No errors? → Check Airflow scheduler logs
```

### Solution
```bash
# Wait longer
sleep 60
# Then refresh Airflow UI

# Or check Airflow logs
docker logs [airflow-scheduler] --tail 100

# Or manually trigger scanner
docker exec [airflow] \
  airflow dags list-import-errors

# Check if file is in correct location
docker exec [airflow] \
  ls -la /opt/airflow/dags/*.py
```

---

## 🚨 Problem: "Permission denied" when executing

### Checklist
```
[ ] Script executable? → chmod +x script.sh
[ ] User permissions? → Check user group
[ ] Docker permissions? → Check Docker daemon
```

### Solution
```bash
# Make script executable
chmod +x sync_validators_to_airflow.sh

# Check current user
whoami

# Add user to docker group
sudo usermod -aG docker $USER

# Restart docker service
sudo systemctl restart docker
```

---

## 🚨 Problem: JavaScript error in console

### Error: "fetch is not defined"
```
Solution: Browser too old
→ Use modern browser (Chrome 40+, Firefox 39+, Safari 10.1+)
```

### Error: "Cannot read property 'getValue' of undefined"
```
Solution: Monaco editor not loaded
→ Refresh page
→ Check browser console for errors
→ Check internet connection
```

### Error: "CORS policy block"
```
Solution: Cross-origin request blocked
→ Check if /api/validation-deploy is reachable
→ Check CORS settings in app
→ Check server headers
```

---

## 🚨 Problem: Button works but shows wrong filename

### Checklist
```
[ ] File name in Git? → Check GitHub sidebar shows name
[ ] Name contains spaces? → Git filenames shouldn't
[ ] Name too long? → Limit to 255 chars
```

### Solution
```
1. Check filename in Git sidebar
2. If wrong, rename file
3. Try deploy again
```

---

## 📋 Diagnostic Checklist

Run through this if something is wrong:

```
FRONTEND:
[ ] Page loads? → /validation-rules-editor
[ ] Button visible? → Look for 🚀 Implantar
[ ] Console clean? → F12 → Console → No red errors
[ ] Monaco editor works? → Can type in editor
[ ] Git sidebar? → "GitHub Files" button toggles

BACKEND:
[ ] PHP no errors? → Check app logs
[ ] Route registered? → grep in Routes.php
[ ] Method exists? → Check ValidationRulesController.php
[ ] Script available? → ls sync_validators_to_airflow.sh
[ ] Script executable? → chmod +x

INTEGRATION:
[ ] Docker running? → docker ps
[ ] Airflow running? → docker ps | grep airflow
[ ] Git accessible? → Can read/write files
[ ] API reachable? → curl /api/validation-deploy (POST)
[ ] Logs visible? → docker logs shows output
```

---

## 🔍 Debug Mode

### Enable verbose logging

**PHP** - Add to ValidationRulesController.php:
```php
log_message('info', 'DEBUG: Request received: ' . json_encode($data));
log_message('info', 'DEBUG: Executing: ' . $command);
log_message('info', 'DEBUG: Output: ' . $outputText);
```

**JavaScript** - Add to browser console:
```javascript
// Add before deployValidator()
console.log('DEBUG: currentGitFile:', currentGitFile);
console.log('DEBUG: filename:', filename);
console.log('DEBUG: gitConfig:', gitConfig);
```

### Check logs
```bash
# Application logs
tail -f app/writable/logs/*.log

# Docker logs
docker logs -f [container-name]

# System logs
journalctl -u docker -f
```

---

## 🆘 Still Not Working?

### Escalation Steps

1. **Basic Check**
   - Page refresh (Ctrl+F5)
   - Browser cache clear
   - Check console errors (F12)

2. **Server Check**
   - Verify Docker running: `docker ps`
   - Check logs: `docker logs [container]`
   - Verify routes: `grep validation-deploy app/Config/Routes.php`

3. **File Check**
   - Verify sync script: `ls sync_validators_to_airflow.sh`
   - Check permissions: `ls -la sync_validators_to_airflow.sh`
   - Test manually: `./sync_validators_to_airflow.sh test.py`

4. **API Check**
   - Test endpoint: `curl -X POST http://localhost/api/validation-deploy`
   - Check response: Should be JSON error if no file

5. **Airflow Check**
   - Container running: `docker ps | grep airflow`
   - Check scheduler: `docker logs [airflow-scheduler]`
   - List DAGs: `docker exec [airflow] airflow dags list`

### Get Help

If still stuck:
1. Collect information:
   - Error message (screenshot)
   - Browser console error (F12)
   - Server log excerpt
   - Docker container name

2. Check documentation:
   - [DEPLOYMENT_BUTTON_README.md](DEPLOYMENT_BUTTON_README.md)
   - [CUSTOM_VALIDATIONS_README.md](CUSTOM_VALIDATIONS_README.md)

3. Contact support with:
   - What you tried
   - What happened
   - What you expected
   - Complete error message

---

## ✅ Verification Steps

Confirm everything is working:

```
1. Button visible?
   [ ] Yes → Continue
   [ ] No → Refresh page, see "Button not appearing"

2. Can click button?
   [ ] Yes → Continue
   [ ] No → Check "Button disabled"

3. Confirmation dialog appears?
   [ ] Yes → Continue
   [ ] No → See "Dialog doesn't appear"

4. Can confirm deploy?
   [ ] Yes → Continue
   [ ] No → Check browser pop-up blocker

5. Success message shown?
   [ ] Yes → WORKING! ✅
   [ ] No → Check API response

6. DAG in Airflow after 30s?
   [ ] Yes → COMPLETE SUCCESS! 🎉
   [ ] No → Check "DAG doesn't appear"
```

---

## 🎯 Quick Fixes

| Problem | Quick Fix | Time |
|---------|-----------|------|
| Button missing | F5 refresh | 5s |
| Permission error | `chmod +x script.sh` | 5s |
| DAG not found | Wait 60s, refresh | 60s |
| API error | Check docker logs | 30s |
| JavaScript error | F12, clear cache | 30s |
| Filename issue | Rename file | 1m |
| Syntax error | Test locally | 5m |
| Full rebuild | See deployment guide | 10m |

---

**Need help?** Check:
- `DEPLOYMENT_BUTTON_README.md` - Full guide
- `QUICK_START_DEPLOYMENT.md` - Quick reference
- Browser console - F12
- Server logs - `docker logs`
- This guide! - You're reading it now! 📖

---

**Quick Test**: 
```
1. Visit /validation-rules-editor
2. Can you see [🚀 Implantar] button?
   YES → ✅ UI is working
   NO → Check "Button not appearing" above
```

---

**Last Resort**: Rollback and restart
```bash
# Revert changes
git checkout src/codeigniter-app/app/Controllers/ValidationRulesController.php
git checkout src/codeigniter-app/app/Config/Routes.php
git checkout src/codeigniter-app/app/Views/code_editor/validation-rules-editor.php

# Restart
docker-compose restart webapp
```

---

*Problems? You're not alone. Follow this guide and you'll solve it! 💪*
