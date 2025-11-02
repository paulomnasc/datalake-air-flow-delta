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

# Remove o conteúdo das pastas de cache e logs (Ignora erro se a pasta for a raiz)
rm -rf /var/www/html/writable/cache/*
rm -rf /var/www/html/writable/logs/*

# 2. Garante as permissões de escrita recursivamente.
chmod -R 777 /var/www/html/vendor # 🛑 NOVO: Permissão para a pasta vendor
chmod -R 777 /var/www/html/writable

# 3. Executa o comando principal do contêiner (inicia o Apache)
exec "$@"