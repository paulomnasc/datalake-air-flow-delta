# 📊 Guia de Implementação GA4 - MyDataFlow

## ✅ Implementação Completa

**Measurement ID:** `G-YFCB5Z0SQ3`  
**Data de Implementação:** 25 de Janeiro de 2026  
**Workspace:** `/root/datalake-air-flow-delta`

---

## 📝 Alterações Realizadas

### 1. **Google Analytics 4 - Script Principal**
**Arquivo:** `/src/codeigniter-app/app/Views/header.php`  
**Localização:** Início do `<head>` (linhas 171-183)

```html
<!-- Google Analytics 4 - DEVE SER O PRIMEIRO SCRIPT -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-YFCB5Z0SQ3"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-YFCB5Z0SQ3', {
    'cookie_flags': 'SameSite=None;Secure'
  });
</script>
```

**✅ Benefícios:**
- Carrega ANTES de qualquer outro script (inclusive AdSense)
- Evita conflitos com Google OAuth
- Configurado com cookies seguros (SameSite=None;Secure)

---

### 2. **Evento de Login Automático**
**Arquivo:** `/src/codeigniter-app/app/Views/header.php`  
**Localização:** Logo após `<body>` (linhas 318-336)

```php
<?php if (isset($_SESSION['ga4_login_event'])): ?>
<!-- Disparo de evento GA4: Login -->
<script>
  if (typeof gtag === 'function') {
    gtag('event', 'login', {
      'method': '<?= htmlspecialchars($_SESSION['ga4_login_event']['method'], ENT_QUOTES, 'UTF-8'); ?>',
      'user_id': '<?= htmlspecialchars($_SESSION['ga4_login_event']['user_id'], ENT_QUOTES, 'UTF-8'); ?>'
    });
    console.log('✅ GA4: Login event sent - Method: <?= htmlspecialchars($_SESSION['ga4_login_event']['method'], ENT_QUOTES, 'UTF-8'); ?>');
  } else {
    console.error('❌ GA4: gtag function not available');
  }
</script>
<?php 
  unset($_SESSION['ga4_login_event']); // Limpa para não reenviar
endif; 
?>
```

---

### 3. **Registro de Login no Backend**
**Arquivo:** `/src/codeigniter-app/app/Controllers/AuthController.php`  
**Localização:** Método `googleCallback()` (após linha 160)

```php
// Registra evento de login para GA4
$_SESSION['ga4_login_event'] = [
    'method' => 'Google',
    'user_id' => $usuario->id,
    'email' => $usuario->email
];
```

---

## 🧪 Como Testar

### **Teste 1: Verificar se GA4 está carregando**

1. Abra seu site
2. Pressione **F12** (DevTools)
3. Vá para aba **Console**
4. Recarregue a página
5. **Busque por:**
   ```
   googletagmanager.com/gtag/js?id=G-YFCB5Z0SQ3
   ```

✅ **Esperado:** Script carregado sem erros  

---

### **Teste 2: DebugView do GA4**

1. Acesse: [Google Analytics](https://analytics.google.com)
2. Vá em: **Admin** → **DebugView**
3. Abra seu site em outra aba com:
   ```
   http://seu-site.com?debug_mode=true
   ```
4. Navegue pelo site

✅ **Esperado:** Eventos em tempo real no DebugView  

---

### **Teste 3: Evento de Login com Google**

1. Faça logout do sistema
2. Abra **DevTools** (F12) → **Console**
3. Clique em **"Entrar com Google"**
4. Complete o fluxo OAuth
5. **Busque no Console:**
   ```
   ✅ GA4: Login event sent - Method: Google
   ```

✅ **Esperado:** Mensagem aparece + Evento no GA4 DebugView  

---

### **Teste 4: Validação Completa**

**Eventos esperados na sequência:**

1. **page_view** → Página de login carregou
2. **Redirecionamento** → OAuth Google (não rastreado)
3. **page_view** → Callback processou
4. **login** → Evento customizado disparado
5. **page_view** → Home carregou após login

---

## 🔍 Diagnóstico de Problemas

### ❌ **"gtag is not a function"**

**Solução:** Script GA4 deve estar no **TOPO** do `<head>`, antes do AdSense

---

### ❌ **Evento de login não aparece**

**Verificações:**
1. Console do navegador tem a mensagem "✅ GA4: Login event sent"?
2. Session `$_SESSION['ga4_login_event']` está sendo criada no AuthController?
3. Header.php está sendo incluído na página pós-login?

---

### ❌ **Eventos não aparecem no GA4**

**Causa:** Delay de processamento (normal)  
**Solução:** Use **DebugView** para validação em tempo real

---

## 📈 Arquivos Modificados

```bash
modified:   src/codeigniter-app/app/Views/header.php
modified:   src/codeigniter-app/app/Controllers/AuthController.php
```

---

## 🎯 Próximos Passos (Opcional)

### **Rastrear mais eventos de conversão:**

```javascript
// Quando usuário cria um pipeline
gtag('event', 'create_pipeline', {
  'pipeline_name': 'Nome do Pipeline',
  'pipeline_type': 'ELT'
});

// Quando usuário executa query SQL
gtag('event', 'execute_query', {
  'editor': 'SQL Editor'
});

// Deploy bem-sucedido
gtag('event', 'deploy_success', {
  'environment': 'production'
});
```

Adicione esses eventos nos respectivos controllers.

---

## 📞 Status da Implementação

**✅ Concluído:**
- Script GA4 instalado e funcionando
- Evento de login Google configurado
- Proteção contra conflitos com OAuth

**📍 Localização dos Arquivos:**
- `header.php`: Linhas 171-183 (GA4) + Linhas 318-336 (Evento)
- `AuthController.php`: Linhas 163-169 (Registro de evento)

---

## 🔗 Links Úteis

- [GA4 DebugView](https://analytics.google.com/analytics/web/#/debugview)
- [Documentação GA4 Events](https://developers.google.com/analytics/devguides/collection/ga4/events)
- [GA4 Reports](https://analytics.google.com/analytics/web/)

---

**🎉 Implementação concluída no workspace correto: datalake-air-flow-delta**
