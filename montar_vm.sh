#!/bin/bash

# Caminho da chave privada
KEY_PATH="/home/paulomnasc/.ssh/ssh-key-2025-10-13.key"

# Pasta remota da VM
REMOTE_PATH="opc@137.131.212.68:/home/opc/datalake-air-flow/"

# Pasta local onde será montada
MOUNT_POINT="/home/paulomnasc/vm-montada"

# Verifica se a pasta de montagem existe
if [ ! -d "$MOUNT_POINT" ]; then
    echo "Criando pasta de montagem em $MOUNT_POINT..."
    mkdir -p "$MOUNT_POINT"
fi

# Monta com permissões compatíveis com Windows
echo "Montando pasta da VM em $MOUNT_POINT..."
sudo sshfs -o IdentityFile="$KEY_PATH" \
           -o allow_other \
           -o default_permissions \
           -o uid=$(id -u) -o gid=$(id -g) \
           "$REMOTE_PATH" "$MOUNT_POINT"

echo "✅ Pasta montada com sucesso!"
echo "📂 Acesse em: \\wsl.localhost\\Ubuntu\\home\\paulomnasc\\vm-montada"
