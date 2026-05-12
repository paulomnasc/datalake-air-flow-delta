-- Cria usuário dedicado a backup (senha deve ser definida depois).
CREATE USER 'backup_lista_revisao2'@'%';

-- Defina a senha segura antes do uso:
-- ALTER USER 'backup_lista_revisao2'@'%' IDENTIFIED BY 'SUA_SENHA_FORTE_AQUI';

GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON `lista_revisao2`.* TO 'backup_lista_revisao2'@'%';
GRANT SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES ON `fiscal`.* TO 'backup_lista_revisao2'@'%';
FLUSH PRIVILEGES;
