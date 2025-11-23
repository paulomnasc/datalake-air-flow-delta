#!/bin/bash

# Inicializa o banco se necessário
airflow db init

# Se ENABLE_DEBUGPY está definido, inicia debugpy antes do scheduler
if [ "$ENABLE_DEBUGPY" = "true" ]; then
    echo "🐛 Iniciando debugpy na porta 5678 (aguardando conexão do VS Code)..."
    python -m debugpy --listen 0.0.0.0:5678 --wait-for-client -m airflow scheduler &
else
    # Inicia o scheduler em segundo plano
    airflow scheduler &
fi

# Inicia o webserver como processo principal
exec airflow webserver