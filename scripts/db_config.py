#!/usr/bin/env python3
"""
scripts/db_config.py

Módulo Canônico de Conexão com o Banco de Dados MySQL (FootballWeb).
Carrega credenciais EXCLUSIVAMENTE a partir do arquivo src/footballweb/.env
ou variáveis de ambiente do sistema operacional.
PROIBIDO QUALQUER HARDCODING DE SENHAS NO CÓDIGO (Regra de Ouro nº 18).
"""

import os
import sys
import pymysql

_ENV_VARS_CACHE = None


def get_live_env_vars() -> dict:
    """
    Carrega variáveis do arquivo src/footballweb/.env de forma segura e cacheada.
    """
    global _ENV_VARS_CACHE
    if _ENV_VARS_CACHE is not None:
        return _ENV_VARS_CACHE

    env_paths = [
        os.path.abspath(os.path.join(os.path.dirname(__file__), "../src/footballweb/.env")),
        "/root/datalake-air-flow-delta/src/footballweb/.env",
        os.path.abspath(os.path.join(os.path.dirname(__file__), "../.env")),
        "/root/datalake-air-flow-delta/.env"
    ]

    env_vars = {}
    for p in env_paths:
        if os.path.exists(p):
            with open(p, "r", encoding="utf-8") as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith("#") and "=" in line:
                        k, v = line.split("=", 1)
                        env_vars[k.strip()] = v.strip().strip("'").strip('"')
            break

    _ENV_VARS_CACHE = env_vars
    return _ENV_VARS_CACHE


def get_db_credentials():
    """
    Extrai as credenciais do MySQL de src/footballweb/.env ou os.environ.
    Falha imediatamente se a senha não estiver configurada (Fail-Fast).
    """
    env = get_live_env_vars()

    user = os.environ.get("MYSQL_USER") or env.get("database.default.username") or "root"
    password = (
        os.environ.get("MYSQL_PASSWORD")
        or os.environ.get("MYSQL_ROOT_PASSWORD")
        or env.get("database.default.password")
        or env.get("MYSQL_PASSWORD")
    )
    database = os.environ.get("MYSQL_DATABASE") or env.get("database.default.database") or "footballweb"

    if not password:
        raise ValueError(
            "❌ [FALHA DE SEGURANÇA - Regra nº 18] Senha do MySQL não encontrada em src/footballweb/.env "
            "nem nas variáveis de ambiente (MYSQL_PASSWORD/MYSQL_ROOT_PASSWORD). "
            "A execução foi interrompida para proteger a integridade do sistema."
        )

    return user, password, database


def get_db_connection(cursorclass=pymysql.cursors.DictCursor, autocommit=True, connect_timeout=3):
    """
    Obtém conexão com o MySQL através de múltiplos hosts/portas locais e docker,
    utilizando as credenciais carregadas dinamicamente do .env.
    """
    user, password, database = get_db_credentials()

    hosts_ports = [
        ("127.0.0.1", 23306),
        ("localhost", 3306),
        ("mysql", 3306),
        ("127.0.0.1", 3306)
    ]

    last_error = None
    for host, port in hosts_ports:
        try:
            conn = pymysql.connect(
                host=host,
                port=port,
                user=user,
                password=password,
                database=database,
                charset="utf8mb4",
                cursorclass=cursorclass,
                autocommit=autocommit,
                connect_timeout=connect_timeout
            )
            return conn
        except Exception as e:
            last_error = e
            continue

    raise ConnectionError(
        f"❌ [ERRO CRÍTICO] Falha ao conectar em qualquer porta do MySQL com as credenciais do .env. "
        f"Último erro: {last_error}"
    )


if __name__ == "__main__":
    print("🧪 Testando módulo canônico db_config.py...")
    try:
        user, pwd, db = get_db_credentials()
        print(f"✅ Credenciais carregadas do .env com sucesso:")
        print(f"   • Usuário: {user}")
        print(f"   • Banco: {db}")
        print(f"   • Senha: {'*' * len(pwd)} (Protegida, {len(pwd)} caracteres)")

        conn = get_db_connection()
        with conn.cursor() as cur:
            cur.execute("SELECT 1 AS ok")
            res = cur.fetchone()
            print(f"✅ Conexão com o banco bem-sucedida! Teste query: {res}")
        conn.close()
    except Exception as err:
        print(f"❌ Erro no teste de db_config: {err}")
        sys.exit(1)
