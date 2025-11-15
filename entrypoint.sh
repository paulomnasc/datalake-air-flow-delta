#!/bin/bash

# Inicializa o banco se necessário
airflow db init

# Inicia o scheduler em segundo plano
airflow scheduler &

# Inicia o webserver como processo principal
exec airflow webserver