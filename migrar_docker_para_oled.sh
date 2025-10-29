#!/bin/bash

echo "⏸️ Parando o serviço Docker..."
sudo systemctl stop docker

echo "📁 Criando novo diretório de armazenamento em /var/oled/docker..."
sudo mkdir -p /var/oled/docker

echo "📦 Movendo conteúdo atual de /var/lib/docker para /var/oled/docker..."
sudo mv /var/lib/docker/* /var/oled/docker/

echo "🔗 Criando link simbólico para redirecionar /var/lib/docker..."
sudo mv /var/lib/docker /var/lib/docker.bak
sudo ln -s /var/oled/docker /var/lib/docker

echo "▶️ Reiniciando o serviço Docker..."
sudo systemctl start docker

echo "✅ Docker agora usa /var/oled como armazenamento!"
echo "🧪 Verifique com: docker info | grep 'Docker Root Dir'"
