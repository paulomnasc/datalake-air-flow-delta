# IMPLANTAÇÃO

## Control Panel of Host Provider
https://x10hosting.com/panel/services/135607

## Database:
DB Name:  elvzlhrs_smarttables
DB User:  elvzlhrs_smarttables
Password: kJ#212394

## Domínio:
https://smarttables.x10.mx/

Upload your website into the public_html directory

## FTP
server : ftp.smarttables.x10.mx
user: paulomnasc@smarttables.x10.mx
pwd: ********
porta: 21

## Configurações
No .ENV:
    app.baseURL = 'http://192.168.0.??/'
FTP    
login:root
senha:root

## Criar a pasta "writable/cache"

após o deploy criar na raíz do site uma pasta e subpasta "writable/cache", dando acesso à escrita e leitura ao usuário

### Habilitar o Módulo de Regravação do Apache (mod_rewrite):

No Windows (XAMPP), o mod_rewrite geralmente já está habilitado por padrão. Basta reiniciar o Apache pelo painel do XAMPP.
Verificar o Arquivo .htaccess na Pasta public:


No Linux terminal, execute o comando:
bash
Copiar código
sudo a2enmod rewrite
Em seguida, reinicie o Apache:
bash
Copiar código
sudo systemctl restart apache2


















# CodeIgniter 4 Framework

## What is CodeIgniter?

CodeIgniter is a PHP full-stack web framework that is light, fast, flexible and secure.
More information can be found at the [official site](https://codeigniter.com).

This repository holds the distributable version of the framework.
It has been built from the
[development repository](https://github.com/codeigniter4/CodeIgniter4).

More information about the plans for version 4 can be found in [CodeIgniter 4](https://forum.codeigniter.com/forumdisplay.php?fid=28) on the forums.

You can read the [user guide](https://codeigniter.com/user_guide/)
corresponding to the latest version of the framework.

## Important Change with index.php

`index.php` is no longer in the root of the project! It has been moved inside the *public* folder,
for better security and separation of components.

This means that you should configure your web server to "point" to your project's *public* folder, and
not to the project root. A better practice would be to configure a virtual host to point there. A poor practice would be to point your web server to the project root and expect to enter *public/...*, as the rest of your logic and the
framework are exposed.

**Please** read the user guide for a better explanation of how CI4 works!

## Repository Management

We use GitHub issues, in our main repository, to track **BUGS** and to track approved **DEVELOPMENT** work packages.
We use our [forum](http://forum.codeigniter.com) to provide SUPPORT and to discuss
FEATURE REQUESTS.

This repository is a "distribution" one, built by our release preparation script.
Problems with it can be raised on our forum, or as issues in the main repository.

## Contributing

We welcome contributions from the community.

Please read the [*Contributing to CodeIgniter*](https://github.com/codeigniter4/CodeIgniter4/blob/develop/CONTRIBUTING.md) section in the development repository.

## Server Requirements

PHP version 8.1 or higher is required, with the following extensions installed:

- [intl](http://php.net/manual/en/intl.requirements.php)
- [mbstring](http://php.net/manual/en/mbstring.installation.php)

> [!WARNING]
> - The end of life date for PHP 7.4 was November 28, 2022.
> - The end of life date for PHP 8.0 was November 26, 2023.
> - If you are still using PHP 7.4 or 8.0, you should upgrade immediately.
> - The end of life date for PHP 8.1 will be December 31, 2025.

Additionally, make sure that the following extensions are enabled in your PHP:

- json (enabled by default - don't turn it off)
- [mysqlnd](http://php.net/manual/en/mysqlnd.install.php) if you plan to use MySQL
- [libcurl](http://php.net/manual/en/curl.requirements.php) if you plan to use the HTTP\CURLRequest library
