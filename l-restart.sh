#!/bin/bash
# -------------------------------------------------------------
# Script de Reinicialização Rápida da Stack Airflow/Spark/CodeIgniter
# -------------------------------------------------------------
docker-compose down codeigniter-app 
docker-compose up --build -d codeigniter-app 
