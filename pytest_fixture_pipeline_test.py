import pytest
import os
from airflow.providers.mysql.hooks.mysql import MySqlHook

@pytest.fixture(autouse=True, scope="function")
def reset_dag_table_selections(request):
    scenario = request.node.name
    dag_id = 101
    hook = MySqlHook(mysql_conn_id="test_conn")
    conn = hook.get_conn()
    cursor = conn.cursor()
    cursor.execute(f"DELETE FROM dag_table_selections WHERE id_dag_config={dag_id};")
    cursor.executemany(
        f"INSERT INTO dag_table_selections (id_dag_config, table_name, is_selected) VALUES ({dag_id}, %s, 1)",
        [("table_a",), ("table_b",), ("table_c",)]
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

def test_pipeline_select():
    dag_id = 101
    hook = MySqlHook(mysql_conn_id="test_conn")
    conn = hook.get_conn()
    cursor = conn.cursor()
    cursor.execute(f"SELECT table_name FROM dag_table_selections WHERE id_dag_config={dag_id} AND is_selected=1 ORDER BY table_name;")
    result = [row[0] for row in cursor.fetchall()]
    cursor.close()
    conn.close()
    print(f"[test] resultado: {result}")
    assert set(result) == {"table_a", "table_b", "table_c"}
