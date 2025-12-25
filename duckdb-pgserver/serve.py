import logging
import os
import sys
from pathlib import Path

import duckdb
from sqlalchemy import create_engine, text

try:
    from pgwire import Server
    from pgwire.gateway.sqlalchemy import SQLAlchemyBackend
except ImportError as exc:  # pragma: no cover
    sys.stderr.write(
        "[fatal] Dependência 'pgwire' não encontrada. Confira requirements.txt e a instalação.\n"
    )
    raise exc


def str_to_bool(value: str, default: bool = False) -> bool:
    if value is None:
        return default
    return value.lower() in {"1", "true", "yes", "y", "on"}


def load_extensions(conn, extensions: list[str]):
    for ext in extensions:
        ext = ext.strip()
        if not ext:
            continue
        conn.execute(text(f"INSTALL {ext}"))
        conn.execute(text(f"LOAD {ext}"))


def run_init_sql(conn, init_sql_path: str):
    sql_file = Path(init_sql_path)
    if not sql_file.exists():
        logging.warning("Arquivo DUCKDB_INIT_SQL não encontrado: %s", sql_file)
        return
    sql_text = sql_file.read_text(encoding="utf-8")
    if sql_text.strip():
        conn.execute(text(sql_text))
        logging.info("Init SQL aplicado: %s", sql_file)


def main():
    logging.basicConfig(
        level=os.getenv("LOG_LEVEL", "INFO"),
        format="[%(asctime)s] %(levelname)s %(message)s",
    )

    db_path = os.getenv("DUCKDB_DATABASE", "/data/duckdb.duckdb")
    read_only = str_to_bool(os.getenv("DUCKDB_READ_ONLY", "true"), default=True)
    pg_host = os.getenv("PGWIRE_HOST", "0.0.0.0")
    pg_port = int(os.getenv("PGWIRE_PORT", "5432"))
    db_user = os.getenv("DB_USER", "duckdb")
    db_password = os.getenv("DB_PASSWORD", "duckdb")
    extensions = os.getenv("DUCKDB_EXTENSIONS", "").split(",")
    init_sql_path = os.getenv("DUCKDB_INIT_SQL")

    # DuckDB engine via SQLAlchemy + duckdb-engine
    engine = create_engine(
        f"duckdb:///{db_path}", connect_args={"read_only": read_only}
    )

    # Inicializa extensões e views se necessário
    with engine.begin() as conn:
        if extensions and any(ext.strip() for ext in extensions):
            load_extensions(conn, extensions)
        if init_sql_path:
            run_init_sql(conn, init_sql_path)

    # Cria backend do pgwire
    backend = SQLAlchemyBackend(engine)

    server = Server(
        backend=backend,
        host=pg_host,
        port=pg_port,
        user=db_user,
        password=db_password,
        database="duckdb",
    )

    logging.info(
        "Servindo DuckDB em protocolo Postgres (host=%s, port=%s, db=%s, user=%s, read_only=%s)",
        pg_host,
        pg_port,
        db_path,
        db_user,
        read_only,
    )

    try:
        server.serve()
    except KeyboardInterrupt:
        logging.info("Encerrando servidor pgwire…")


if __name__ == "__main__":
    main()
