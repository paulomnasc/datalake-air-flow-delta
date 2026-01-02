# Backup MySQL → Google Drive

## 1. Criar usuário de backup no MySQL

```bash
docker exec -i mysql mysql -uroot -p'YM11rMrT32xH0E6N' < backup/create_backup_user.sql
```

Depois defina a senha:
```bash
docker exec -i mysql mysql -uroot -p'YM11rMrT32xH0E6N' -e \
  "ALTER USER 'backup_lista_revisao2'@'%' IDENTIFIED BY 'SUA_SENHA_FORTE_AQUI';"
```

## 2. Configurar credenciais para mysqldump

Crie `~/.my.cnf` (não precisa ser root):
```ini
[client]
user=backup_lista_revisao2
password=SUA_SENHA_FORTE_AQUI
```

Proteja o arquivo:
```bash
chmod 600 ~/.my.cnf
```

## 3. Instalar e configurar rclone

```bash
curl https://rclone.org/install.sh | sudo bash
rclone config  # Crie um remote chamado 'gdrive' para Google Drive
```

## 4. Testar o backup manualmente

```bash
./backup/backup_lista_revisao2.sh
```

## 5. Agendar no cron (diário às 2h da manhã)

```bash
crontab -e
```

Adicione:
```cron
0 2 * * * /root/datalake-air-flow-delta/backup/backup_lista_revisao2.sh >> /var/log/backup_lista_revisao2.log 2>&1
```

## Observações

- O script conecta via TCP na porta **23306** (MySQL Docker)
- Backups locais temporários são mantidos por 7 dias
- Backups no Google Drive ficam em `gdrive:backups/lista_revisao2/`
