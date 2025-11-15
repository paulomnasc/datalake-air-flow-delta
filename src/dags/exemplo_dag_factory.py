import os
from pathlib import Path

# The following import is here so Airflow parses this file
# from airflow import DAG
from dagfactory import load_yaml_dags

DEFAULT_CONFIG_ROOT_DIR = "/opt/airflow/dags/"

CONFIG_ROOT_DIR = Path(os.getenv("CONFIG_ROOT_DIR", DEFAULT_CONFIG_ROOT_DIR))

config_file = str(CONFIG_ROOT_DIR / "exemplo_dag_factory.yml")

load_yaml_dags(
    globals_dict=globals(),
    config_filepath=config_file,
)