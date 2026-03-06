import pytest
import os

@pytest.fixture(autouse=True, scope="function")
def log_fixture():
    log_dir = "/opt/airflow/test_logs"
    os.makedirs(log_dir, exist_ok=True)
    log_path = os.path.join(log_dir, "pytest_fixture_test.log")
    with open(log_path, "a") as f:
        f.write("fixture executado\n")
    print("[fixture] executado")

def test_sanity():
    assert True
