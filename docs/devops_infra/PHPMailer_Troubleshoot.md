# Troubleshooting: Erro PHPMailer no Container Docker

## Sintoma
- Erro: `Class "PHPMailer\PHPMailer\PHPMailer" not found`
- Ou: problemas ao enviar e-mails usando PHPMailer em ambiente Docker

## Causas Possíveis
- PHPMailer não instalado via Composer
- composer.json/composer.lock desatualizados ou corrompidos
- vendor não contém PHPMailer
- Autoload do Composer não incluído corretamente
- Permissões de pasta vendor

## Solução Passo a Passo

### 1. Acesse o Container
```bash
docker exec -it <nome_do_container> bash
```

### 2. Verifique se Composer está instalado
```bash
composer --version
```
Se não estiver, instale:
```bash
apt-get update && apt-get install composer -y
```

### 3. Navegue até o diretório do projeto
```bash
cd /caminho/do/projeto
```

### 4. Instale o PHPMailer
```bash
composer require phpmailer/phpmailer
```

### 5. Verifique se PHPMailer foi instalado
- O arquivo `vendor/phpmailer/phpmailer/src/PHPMailer.php` deve existir.

### 6. Inclua o autoload do Composer no seu código
No início do seu controller:
```php
require_once FCPATH . 'vendor/autoload.php';
```

### 7. Remova require_once antigos
- Não use require_once de arquivos individuais do PHPMailer.
- Use apenas o autoload do Composer.

### 8. Corrija o namespace
- O namespace deve ser a primeira instrução após `<?php`.
- Não pode haver linhas em branco antes.

### 9. Permissões
- Certifique-se que o usuário do container tem permissão de escrita na pasta vendor.

### 10. Versionamento
- Versione composer.json e composer.lock.
- Não versionar a pasta vendor.

## Teste
- Reinicie o container se necessário.
- Teste o envio de e-mail.

## Referências
- https://github.com/PHPMailer/PHPMailer
- https://getcomposer.org/

## Exemplo de comando completo:
```bash
docker exec -it codeigniter-app bash
cd /var/www/html
composer require phpmailer/phpmailer
```

## Observação
Se o container não tem Composer, instale manualmente ou use um Dockerfile que já inclua Composer.
