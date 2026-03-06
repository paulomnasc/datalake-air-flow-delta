import pytest
import os
from airflow.providers.mysql.hooks.mysql import MySqlHook
from dags.factory_master import fetch_selected_tables_pipeline
from dags.lib.medallion_pipeline_v2 import RawToMedallionPipeline

@pytest.fixture(scope="function")
def dag_id_fixture(request):
    scenario = request.node.name
    if "criacao" in scenario:
        dag_id = 101
    elif "edicao" in scenario:
        dag_id = 102
    elif "adicao" in scenario:
        dag_id = 103
    elif "remocao" in scenario:
        dag_id = 104
    else:
        dag_id = 999
    hook = MySqlHook(mysql_conn_id="test_conn")
    conn = hook.get_conn()
    cursor = conn.cursor()
    cursor.execute(f"DELETE FROM dag_table_selections WHERE id_dag_config={dag_id};")
    if "criacao" in scenario or "adicao" in scenario:
        cursor.executemany(
            f"INSERT INTO dag_table_selections (id_dag_config, table_name, is_selected) VALUES ({dag_id}, %s, 1)",
            [("table_a",), ("table_b",), ("table_c",)]
        )
    elif "edicao" in scenario:
        cursor.executemany(
            f"INSERT INTO dag_table_selections (id_dag_config, table_name, is_selected) VALUES ({dag_id}, %s, %s)",
            [("table_a", 1), ("table_b", 0), ("table_c", 1)]
        )
    elif "remocao" in scenario:
        cursor.executemany(
            f"INSERT INTO dag_table_selections (id_dag_config, table_name, is_selected) VALUES ({dag_id}, %s, %s)",
            [("table_a", 1), ("table_b", 0), ("table_c", 0)]
        )
    conn.commit()
    cursor.close()
    conn.close()
    log_dir = "/opt/airflow/test_logs"
    os.makedirs(log_dir, exist_ok=True)
    log_path = os.path.join(log_dir, f"dag_table_selections_{scenario}.log")
    with open(log_path, "a") as f:
        f.write(f"Fixture executado para {scenario}\n")
    print(f"[fixture] executado para {scenario}")
    return dag_id

@pytest.mark.parametrize("selected_tables", [["table_a", "table_b"]])
def test_criacao_pipeline(selected_tables, dag_id_fixture):
    dag_id = dag_id_fixture
    result = fetch_selected_tables_pipeline("test_conn", dag_id)
    assert isinstance(result, list)
    pipeline = RawToMedallionPipeline()
    assert pipeline is not None

@pytest.mark.parametrize("selected_tables", [["table_a", "table_c"]])
def test_edicao_pipeline(selected_tables, dag_id_fixture):
    dag_id = dag_id_fixture
    result = fetch_selected_tables_pipeline("test_conn", dag_id)
    assert "table_c" in result or "table_a" in result

@pytest.mark.parametrize("selected_tables", [["table_a", "table_b", "table_c"]])
def test_adicao_tabela(selected_tables, dag_id_fixture):
    dag_id = dag_id_fixture
    result = fetch_selected_tables_pipeline("test_conn", dag_id)
    assert "table_c" in result

@pytest.mark.parametrize("selected_tables", [["table_a"]])
def test_remocao_tabela(selected_tables, dag_id_fixture):
    dag_id = dag_id_fixture
    result = fetch_selected_tables_pipeline("test_conn", dag_id)
    print(f"[TESTE REMOCAO] Resultado: {result}")
    assert "table_b" not in result


