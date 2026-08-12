# 🧪 Test Plan - Deploy Button

## Pre-Test Checklist

- [ ] Application running on http://localhost/validation-rules-editor
- [ ] GitHub connection configured
- [ ] Airflow container running
- [ ] Browser developer tools available (F12)
- [ ] Internet connection stable

---

## Test Case 1: UI Button Visibility

**Objective**: Verify deploy button appears in correct location

### Steps:
1. Open `/validation-rules-editor`
2. Scroll to editor section
3. Look at button row below code editor

### Expected Result:
```
✓ Button row shows: [▶️ Testar] [💾 Salvar] [🚀 Implantar] [🗑️ Limpar]
✓ 🚀 Implantar button is orange/amber colored
✓ Button has white text
```

### Pass/Fail: ___________

---

## Test Case 2: Button Styling (Hover)

**Objective**: Verify button visual feedback on hover

### Steps:
1. Hover mouse over [🚀 Implantar] button
2. Observe color change

### Expected Result:
```
✓ Button changes to darker orange/amber on hover
✓ Cursor changes to pointer (hand icon)
✓ Color returns to normal when mouse leaves
```

### Pass/Fail: ___________

---

## Test Case 3: Validation - Empty Editor

**Objective**: Verify error when editor is empty

### Steps:
1. Clear editor (click 🗑️ Limpar)
2. Click [🚀 Implantar]

### Expected Result:
```
✓ Error message appears: "❌ Editor vazio - Salve um arquivo primeiro"
✓ Message appears in feedback area
✓ Message disappears after 3 seconds
✓ No API call is made
```

### Pass/Fail: ___________

---

## Test Case 4: Validation - No File Open

**Objective**: Verify error when no file is selected

### Steps:
1. Write some code in editor
2. Don't open any file from sidebar
3. Click [🚀 Implantar]

### Expected Result:
```
✓ Error message appears: "❌ Nenhum arquivo aberto - Abra ou crie um arquivo no Git"
✓ No API call is made
```

### Pass/Fail: ___________

---

## Test Case 5: Confirmation Dialog

**Objective**: Verify confirmation dialog works

### Steps:
1. Open file from GitHub sidebar (or create new one)
2. Write test code:
   ```python
   def validate(df):
       return df
   ```
3. Click 💾 Salvar
4. Click [🚀 Implantar]

### Expected Result:
```
✓ Browser confirmation dialog appears
✓ Message shows filename: "Sincronizar "test_deploy.py" para Airflow?"
✓ Dialog has [Cancelar] and [OK] buttons
```

### Pass/Fail: ___________

---

## Test Case 6: Cancel Deployment

**Objective**: Verify cancel functionality

### Steps:
1. Follow Test Case 5 steps 1-4
2. Click [Cancelar] in confirmation dialog

### Expected Result:
```
✓ Dialog closes
✓ No API call is made
✓ Button remains unchanged
✓ No success/error message shown
```

### Pass/Fail: ___________

---

## Test Case 7: Button State During Deployment

**Objective**: Verify button becomes disabled during deploy

### Steps:
1. Follow Test Case 5 steps 1-3
2. Open browser DevTools (F12)
3. Go to Network tab
4. Click [🚀 Implantar] → [OK] in dialog
5. Watch button state

### Expected Result:
```
✓ Button immediately becomes disabled
✓ Button text changes to "⏳ Implantando..."
✓ Button color becomes grayed out/less opaque
✓ Cursor changes to not-allowed
```

### Pass/Fail: ___________

---

## Test Case 8: API Request

**Objective**: Verify API endpoint receives correct data

### Steps:
1. Open browser DevTools (F12)
2. Go to Network tab
3. Create/open file: `test_api.py`
4. Add code:
   ```python
   def validate(df):
       return df
   ```
5. Click 💾 Salvar
6. Click [🚀 Implantar] → [OK]
7. Watch Network tab

### Expected Result:
```
✓ POST request to /api/validation-deploy appears
✓ Request Method: POST
✓ Request Headers include: Content-Type: application/json
✓ Request Body: {"filename":"test_api.py"}
✓ Response Status: 200 OK
```

### Pass/Fail: ___________

---

## Test Case 9: API Response - Success

**Objective**: Verify successful deployment response

### Steps:
1. Follow Test Case 8
2. Check Network tab → Response tab

### Expected Result:
```
✓ Response JSON includes:
  {
    "success": true,
    "message": "✅ test_api.py sincronizado para Airflow!",
    "output": "[deployment logs...]",
    "next_step": "Aguarde 30 segundos..."
  }
```

### Pass/Fail: ___________

---

## Test Case 10: Success Message Display

**Objective**: Verify success message shown to user

### Steps:
1. Follow Test Case 8-9
2. Watch editor area for message

### Expected Result:
```
✓ Green success message appears
✓ Message text: "✅ test_api.py sincronizado para Airflow!"
✓ Message includes next step advice
✓ Message disappears after 3 seconds
✓ Button re-enables with text "🚀 Implantar"
```

### Pass/Fail: ___________

---

## Test Case 11: End-to-End Workflow

**Objective**: Complete workflow from creation to Airflow

### Steps:
1. Open `/validation-rules-editor`
2. Connect to GitHub (if not already)
3. Create new file or use existing
4. Write validator code:
   ```python
   from src.datalake.medallion import raw_to_medallion
   
   class TestValidator:
       def __call__(self, source_filename, target_table_name, **context):
           result = raw_to_medallion(source_filename, target_table_name, **context)
           return result
   
   def validate(df):
       return df
   ```
5. Click 💾 Salvar
6. Click 🚀 Implantar
7. Confirm dialog
8. Wait for success message
9. Wait 30 seconds
10. Check Airflow Web UI

### Expected Result:
```
✓ File saved to Git successfully
✓ Deploy button activated
✓ Success message appears
✓ Deployment script executed (check backend logs)
✓ File appears in /opt/airflow/dags/
✓ DAG visible in Airflow Web UI after 30s
```

### Pass/Fail: ___________

---

## Test Case 12: Error Handling - Invalid Filename

**Objective**: Verify filename sanitization works

### Steps:
1. Create file with special characters (if possible)
   - Try: `test@#$.py` or `../etc/passwd.py`
2. Try to deploy

### Expected Result:
```
✓ API rejects invalid characters
✓ Error message shown to user
✓ No malicious characters reach shell
✓ Backend logs show sanitization
```

### Pass/Fail: ___________

---

## Test Case 13: Error Handling - Script Not Found

**Objective**: Verify error when sync script missing

### Steps:
1. Temporarily rename/remove sync_validators_to_airflow.sh
2. Try to deploy
3. Restore file

### Expected Result:
```
✓ Error message: "❌ Script de sincronização não disponível"
✓ No crash, graceful error handling
✓ User-friendly message (no paths exposed)
```

### Pass/Fail: ___________

---

## Test Case 14: Concurrent Requests

**Objective**: Verify multiple rapid clicks are handled

### Steps:
1. Have file ready to deploy
2. Click [🚀 Implantar] multiple times rapidly
3. Watch button state

### Expected Result:
```
✓ First request processes normally
✓ Button stays disabled during processing
✓ Subsequent clicks ignored (button disabled)
✓ Only one deployment happens
✓ No race conditions or errors
```

### Pass/Fail: ___________

---

## Test Case 15: Responsive Design

**Objective**: Verify button works on mobile/tablet

### Steps:
1. Open DevTools (F12)
2. Toggle device toolbar
3. Test on: iPhone 12, iPad, Android tablet
4. Click button on each device

### Expected Result:
```
✓ Button visible and accessible on all sizes
✓ Touch targets >= 44x44px (iOS standard)
✓ Buttons don't wrap into multiple lines on mobile
✓ Confirmation dialog readable on small screens
✓ Button clickable and responsive
```

### Pass/Fail: ___________

---

## Test Case 16: Accessibility

**Objective**: Verify accessibility features

### Steps:
1. Use keyboard Tab key to navigate to button
2. Press Enter to activate
3. Use screen reader if available

### Expected Result:
```
✓ Button reachable via Tab key
✓ Button has focus indicator
✓ Enter key triggers deployment
✓ Button has descriptive title attribute
✓ Error messages announced by screen reader
```

### Pass/Fail: ___________

---

## Test Case 17: Browser Compatibility

**Objective**: Test on different browsers

### Browsers to test:
- [ ] Chrome/Chromium
- [ ] Firefox
- [ ] Safari
- [ ] Edge

### Expected Result for each:
```
✓ Button visible and styled correctly
✓ Click events work
✓ API requests successful
✓ Console has no JavaScript errors
✓ Response handling works
```

### Pass/Fail: ___________

---

## Test Case 18: Performance

**Objective**: Verify deployment doesn't cause lag

### Steps:
1. Open DevTools → Performance tab
2. Click [🚀 Implantar]
3. Record and analyze

### Expected Result:
```
✓ Button UI responds immediately (<50ms)
✓ No frame drops
✓ Network request completes in reasonable time
✓ UI remains responsive during deployment
✓ No memory leaks (check over multiple deployments)
```

### Pass/Fail: ___________

---

## Test Case 19: Logging

**Objective**: Verify backend logs are created

### Steps:
1. Deploy a validator
2. Check server logs

### Expected Result:
```
✓ Log entry: "Executando deploy: [command]"
✓ Log entry: "Deploy concluído com sucesso para [filename]"
✓ Log entry contains command details
✓ Error cases also logged
```

### Pass/Fail: ___________

---

## Test Case 20: Session/Auth

**Objective**: Verify auth works correctly

### Steps:
1. Deploy as authenticated user
2. Log out
3. Try to deploy (if redirected) or access endpoint

### Expected Result:
```
✓ Authenticated users can deploy
✓ Unauthorized requests are blocked
✓ Session timeouts handled gracefully
✓ Auth tokens validated for POST request
```

### Pass/Fail: ___________

---

## Summary

| Test Case | Status | Notes |
|-----------|--------|-------|
| 1. UI Button Visibility | [ ] | |
| 2. Button Styling | [ ] | |
| 3. Empty Editor | [ ] | |
| 4. No File Open | [ ] | |
| 5. Confirmation Dialog | [ ] | |
| 6. Cancel Deployment | [ ] | |
| 7. Button State | [ ] | |
| 8. API Request | [ ] | |
| 9. API Response | [ ] | |
| 10. Success Message | [ ] | |
| 11. End-to-End | [ ] | |
| 12. Filename Security | [ ] | |
| 13. Error Handling | [ ] | |
| 14. Concurrent Requests | [ ] | |
| 15. Responsive Design | [ ] | |
| 16. Accessibility | [ ] | |
| 17. Browser Compatibility | [ ] | |
| 18. Performance | [ ] | |
| 19. Logging | [ ] | |
| 20. Auth/Session | [ ] | |

**Overall Result**: ___________

**Date**: __________________

**Tester**: ________________

**Blockers**: ______________________________________________________________

**Notes**: __________________________________________________________________

---

## Rollback Procedure (if issues found)

```bash
git checkout \
  src/codeigniter-app/app/Controllers/ValidationRulesController.php \
  src/codeigniter-app/app/Config/Routes.php \
  src/codeigniter-app/app/Views/code_editor/validation-rules-editor.php
```

---

**When all tests PASS**: Ready for production! 🚀
