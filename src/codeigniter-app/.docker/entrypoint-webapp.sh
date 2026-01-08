#!/bin/bash
# Habilita o modo de debug
set -x

# 🛑 NOVO: Garante que o diretório de volume tenha permissão de escrita
# É crucial que o usuário root dentro do container consiga manipular o volume
chmod -R 777 /var/www/html

# 1. NOVO: Remove forçadamente o arquivo .htaccess existente para evitar "Permission denied"
if [ -f "/var/www/html/.htaccess" ]; then
    rm -f /var/www/html/.htaccess
    echo "✅ Original .htaccess (com HTTPS) removido com sucesso."
fi

# 2. Copia o .htaccess sem SSL para o volume
cp /tmp/htaccess-no-ssl /var/www/html/.htaccess
echo "✅ .htaccess substituído para desabilitar o redirecionamento HTTPS."

# Criar symlink ou copiar assets para public/assets
echo "📦 Verificando e preparando assets..."
if [ -d "/var/www/html/assets" ] && [ ! -L "/var/www/html/public/assets" ]; then
    if [ ! -d "/var/www/html/public/assets" ]; then
        # Criar symlink dos assets
        ln -s /var/www/html/assets /var/www/html/public/assets
        echo "✅ Symlink criado: /var/www/html/public/assets -> /var/www/html/assets"
    fi
fi

# Remove o conteúdo das pastas de cache e logs (Ignora erro se a pasta for a raiz)
rm -rf /var/www/html/writable/cache/*
rm -rf /var/www/html/writable/logs/*

# 2. Garante as permissões de escrita recursivamente.
# Garante que as pastas de cache e logs sejam de propriedade do usuário www-data
chown -R www-data:www-data /var/www/html/writable
chown -R www-data:www-data /var/www/html/vendor # Já deveria ser feito no Dockerfile, mas é um bom failsafe.

# Garante que a permissão de escrita (rwx) esteja definida para o usuário e grupo.
echo "Ajustando permissões de escrita (para garantir)..."
chmod -R 775 /var/www/html/writable

# 3. Executa o comando principal do contêiner (inicia o Apache)
exec "$@"