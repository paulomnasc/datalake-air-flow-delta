Perfeito, Cristiane! Como sua máquina local é Windows, aqui está um guia completo para configurar o **VS Code com SSH** usando sua chave `.pem` ou `.key` para se conectar à sua instância Linux na nuvem:

---

## 🧭 Passo a passo: Conectar VS Code via SSH no Windows

### ✅ 1. Verifique se você tem o OpenSSH instalado
No PowerShell, digite:

```powershell
ssh -V
```

Se aparecer algo como `OpenSSH_for_Windows_8.1p1`, está tudo certo.

---

### ✅ 2. Crie ou edite o arquivo de configuração SSH

O arquivo deve estar em:

```
C:\Users\SEU_USUARIO\.ssh\config
```

> Substitua `SEU_USUARIO` pelo seu nome de usuário do Windows. Se a pasta `.ssh` não existir, crie manualmente.

### 📝 Exemplo de conteúdo do arquivo `config`

```ssh
Host airflow-server
    HostName 137.131.212.68
    User opc
    IdentityFile C:\Users\SEU_USUARIO\.ssh\ssh-key-2025-10-13.key
    Port 22
```

> Certifique-se de que o caminho da chave está correto e que a extensão `.key` corresponde à sua chave privada.

---

### ✅ 3. Teste a conexão no PowerShell

```powershell
ssh airflow-server
```

Se funcionar, você verá o terminal da máquina Linux.

---

### ✅ 4. Conecte via VS Code

1. Instale a extensão **Remote - SSH** no VS Code.
2. Pressione `Ctrl+Shift+P` e digite:
   ```
   Remote-SSH: Connect to Host...
   ```
3. Selecione `airflow-server`.
4. O VS Code abrirá um terminal remoto e permitirá editar arquivos diretamente na máquina.

---

## 🧯 Se der erro de permissão

Se aparecer `Permission denied (publickey)`, verifique:

- Se a chave pública correspondente à `.key` foi adicionada ao `~/.ssh/authorized_keys` da máquina.
- Se o usuário (`opc` ou `cblna`) existe e tem permissão.
- Se as permissões da pasta `.ssh` e do arquivo `authorized_keys` estão corretas:

```bash
chmod 700 ~/.ssh
chmod 600 ~/.ssh/authorized_keys
```

---

Se quiser, posso te ajudar a validar a chave pública ou montar os comandos para configurar o acesso remoto. Me avisa se está usando `opc` ou `cblna`, e se a chave foi gerada com `ssh-keygen` ou baixada da nuvem. Vamos deixar isso funcionando! 💪
