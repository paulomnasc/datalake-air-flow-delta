# Solução de Backup Automático - MySQL → Google Drive

## 📋 Visão Geral

Sistema de backup automatizado que exporta dumps diários do banco de dados MySQL `lista_revisao2` e armazena no Google Drive usando `rclone`. Os backups são compactados em formato `.sql.gz` e mantidos localmente por 7 dias.

## 🏗️ Arquitetura

```
┌─────────────────┐      ┌──────────────────┐      ┌────────────────┐
│  MySQL Docker   │──────│  Shell Script    │──────│  Google Drive  │
│  (porta 23306)  │ dump │  mysqldump +     │upload│  gdrive:       │
│                 │      │  rclone          │      │  /backups/     │
└─────────────────┘      └──────────────────┘      └────────────────┘
                                  │
                                  ▼
                         ┌──────────────────┐
                         │  /tmp/backups    │
                         │  (7 dias local)  │
                         └──────────────────┘
```

## 📂 Estrutura de Arquivos

```
/root/datalake-air-flow-delta/backup/
├── backup_lista_revisao2.sh     # Script principal de backup
├── create_backup_user.sql       # SQL para criar usuário dedicado
└── README.md                    # Instruções de setup inicial
```

## 🔧 Componentes

### 1. Script de Backup (`backup_lista_revisao2.sh`)

**Localização**: `/root/datalake-air-flow-delta/backup/backup_lista_revisao2.sh`

**Funcionalidades**:
- Conecta ao MySQL via TCP (porta 23306) - necessário pois o MySQL está em container Docker
- Gera dump com transação única (`--single-transaction`) para não bloquear o banco
- Inclui rotinas, triggers e eventos (`--routines --triggers --events`)
- Compacta o dump com `gzip` para economizar espaço
- Envia para Google Drive via `rclone`
- Remove backups locais antigos (> 7 dias)
- Nomeia arquivos com timestamp: `lista_revisao2_YYYYMMDD_HHMMSS.sql.gz`

**Configurações principais**:
```bash
DB_NAME="lista_revisao2"
DB_HOST="localhost"
DB_PORT="23306"                    # Porta do MySQL Docker
DB_USER="${MYSQL_USER:-backup_lista_revisao2}"
RCLONE_REMOTE="gdrive"             # Nome do remote rclone
RCLONE_PATH="backups/lista_revisao2"  # Pasta no Google Drive
```

### 2. Usuário de Backup MySQL

**Script SQL**: `/root/datalake-air-flow-delta/backup/create_backup_user.sql`

Cria usuário `backup_lista_revisao2` com permissões **somente leitura** no schema `lista_revisao2`:
- `SELECT`: Ler dados das tabelas
- `SHOW VIEW`: Ver definições de views
- `TRIGGER`: Necessário para dump de triggers
- `EVENT`: Necessário para dump de eventos
- `LOCK TABLES`: Garantir consistência do dump

### 3. Autenticação

O script usa credenciais de `~/.my.cnf` no formato:
```ini
[client]
user=backup_lista_revisao2
password=<senha_do_usuario>
```

**Segurança**: 
- Permissões restritas: `chmod 600 ~/.my.cnf`
- Senha não fica hardcoded no script
- Usuário com privilégios mínimos (somente leitura)

### 4. Rclone (Google Drive)

**Remote configurado**: `gdrive`  
**Destino**: `gdrive:backups/lista_revisao2/`

O `rclone` sincroniza os backups compactados para o Google Drive após cada execução.

## 🚀 Uso Manual

### Executar backup imediatamente:
```bash
cd /root/datalake-air-flow-delta
./backup/backup_lista_revisao2.sh
```

### Verificar backups locais:
```bash
ls -lh /tmp/mysql_backups/
```

### Listar backups no Google Drive:
```bash
rclone ls gdrive:backups/lista_revisao2/
```

### Baixar backup do Google Drive:
```bash
rclone copy gdrive:backups/lista_revisao2/lista_revisao2_20260102_171851.sql.gz ~/Downloads/
```

## ⏰ Agendamento Automático (Cron)

### Configurar cron para execução diária às 2h da manhã:

```bash
crontab -e
```

Adicione a linha:
```cron
0 2 * * * /root/datalake-air-flow-delta/backup/backup_lista_revisao2.sh >> /var/log/backup_lista_revisao2.log 2>&1
```

**Explicação**:
- `0 2 * * *`: Todo dia às 02:00
- `>>`: Redireciona saída para arquivo de log (append)
- `2>&1`: Captura erros também

### Outros exemplos de agendamento:

```cron
# A cada 12 horas (02:00 e 14:00)
0 2,14 * * * /root/datalake-air-flow-delta/backup/backup_lista_revisao2.sh >> /var/log/backup_lista_revisao2.log 2>&1

# Semanalmente aos domingos às 3h
0 3 * * 0 /root/datalake-air-flow-delta/backup/backup_lista_revisao2.sh >> /var/log/backup_lista_revisao2.log 2>&1

# Diariamente às 23:30
30 23 * * * /root/datalake-air-flow-delta/backup/backup_lista_revisao2.sh >> /var/log/backup_lista_revisao2.log 2>&1
```

### Verificar cron ativo:
```bash
crontab -l
```

### Monitorar logs de execução:
```bash
tail -f /var/log/backup_lista_revisao2.log
```

## 🔍 Troubleshooting

### Erro: "Can't connect to MySQL server through socket"
**Causa**: Script tentando usar socket Unix em vez de TCP  
**Solução**: Verificar se `--protocol=TCP` está no comando `mysqldump`

### Erro: "Access denied for user"
**Causa**: Credenciais incorretas ou usuário sem permissões  
**Solução**: 
1. Verificar `~/.my.cnf` tem usuário e senha corretos
2. Confirmar permissões do usuário no MySQL:
   ```sql
   SHOW GRANTS FOR 'backup_lista_revisao2'@'%';
   ```

### Erro: "rclone: command not found"
**Causa**: Rclone não instalado  
**Solução**: 
```bash
curl https://rclone.org/install.sh | sudo bash
rclone config  # Configurar remote 'gdrive'
```

### Backup não está no Google Drive
**Causa**: Remote `gdrive` não configurado ou path incorreto  
**Solução**:
```bash
rclone listremotes  # Deve listar 'gdrive:'
rclone lsd gdrive:  # Listar diretórios raiz
```

### Container MySQL não está rodando
**Verificar**:
```bash
docker ps | grep mysql
```
**Iniciar se necessário**:
```bash
cd /root/datalake-air-flow-delta
./startup.sh
```

## 📊 Monitoramento

### Tamanho dos backups:
```bash
du -sh /tmp/mysql_backups/
rclone size gdrive:backups/lista_revisao2/
```

### Último backup realizado:
```bash
ls -lt /tmp/mysql_backups/ | head -n 2
rclone ls gdrive:backups/lista_revisao2/ --max-age 24h
```

### Status do último cron job:
```bash
grep "backup_lista_revisao2" /var/log/syslog | tail -5
```

## 🔐 Segurança

- ✅ Usuário de backup com **privilégios mínimos** (somente leitura)
- ✅ Senhas armazenadas em `~/.my.cnf` com permissões `600`
- ✅ Backups criptografados em trânsito pelo `rclone`
- ✅ Autenticação OAuth2 com Google Drive
- ✅ Logs de execução para auditoria

## 🗄️ Restauração de Backup

### 1. Baixar backup do Google Drive:
```bash
rclone copy gdrive:backups/lista_revisao2/lista_revisao2_YYYYMMDD_HHMMSS.sql.gz /tmp/
```

### 2. Descompactar:
```bash
gunzip /tmp/lista_revisao2_YYYYMMDD_HHMMSS.sql.gz
```

### 3. Restaurar no MySQL:
```bash
# Via Docker exec
docker exec -i mysql mysql -uroot -p lista_revisao2 < /tmp/lista_revisao2_YYYYMMDD_HHMMSS.sql

# Ou via TCP (se mysql client estiver na VM)
mysql --protocol=TCP -h localhost -P 23306 -uroot -p lista_revisao2 < /tmp/lista_revisao2_YYYYMMDD_HHMMSS.sql
```

### 4. Verificar restauração:
```bash
docker exec -i mysql mysql -uroot -p -e "USE lista_revisao2; SHOW TABLES;"
```

## 📝 Manutenção

### Alterar período de retenção local (default: 7 dias)
Editar linha no script:
```bash
find "$DUMP_DIR" -type f -name "${DB_NAME}_*.gz" -mtime +7 -delete
#                                                          ^^
#                                                    altere aqui (dias)
```

### Mudar horário do backup
```bash
crontab -e  # Editar linha do cron
```

### Adicionar notificações por email em caso de falha
Adicionar ao cron:
```cron
0 2 * * * /root/datalake-air-flow-delta/backup/backup_lista_revisao2.sh >> /var/log/backup_lista_revisao2.log 2>&1 || echo "Backup falhou!" | mail -s "Erro no Backup MySQL" admin@exemplo.com
```

## 📌 Notas Importantes

1. **MySQL em Docker**: O banco de dados roda em container Docker na porta `23306`. O script usa `--protocol=TCP` para conectar.

2. **Transações**: O dump usa `--single-transaction` que é **essencial** para InnoDB - garante consistência sem bloquear o banco.

3. **Espaço em Disco**: Backups locais são mantidos por 7 dias. Monitore o espaço em `/tmp/mysql_backups/`.

4. **Google Drive Quota**: Verifique periodicamente a cota do Google Drive para evitar falhas de upload.

5. **Testes Regulares**: Teste a restauração de um backup mensalmente para garantir a integridade.

6. **Logs Cron**: Por padrão, cron envia emails com saída de comandos. Configure `/etc/aliases` ou redirecione para arquivo.

## 🔗 Referências

- [Documentação MySQL Backup](https://dev.mysql.com/doc/refman/8.0/en/backup-and-recovery.html)
- [Rclone Documentation](https://rclone.org/docs/)
- [Cron Expression Generator](https://crontab.guru/)
