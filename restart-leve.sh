#!/bin/bash
# Reinicialização leve dos containers

docker compose down
sleep 2
docker compose up -d

echo "Stack reiniciada."
