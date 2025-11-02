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

# 3. Executa o comando principal do contêiner (inicia o Apache)
exec "$@"