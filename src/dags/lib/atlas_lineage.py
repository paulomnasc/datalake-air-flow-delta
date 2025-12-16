from typing import Dict, List
from .atlas_client import AtlasClient

def register_table(client: AtlasClient, layer: str, table: str, db: str, columns: List[Dict], owner: str = "airflow"):
    qualified_name = f"{db}.{table}@cluster"
    return client.create_hive_table(qualified_name=qualified_name, name=table, db=db, columns=columns, description=f"Medallion {layer} table", owner=owner)

def register_process(client: AtlasClient, step_name: str, layer_from: str, layer_to: str, inputs_qn: List[str], outputs_qn: List[str]):
    inputs = [{"typeName": "hive_table", "uniqueAttributes": {"qualifiedName": qn}} for qn in inputs_qn]
    outputs = [{"typeName": "hive_table", "uniqueAttributes": {"qualifiedName": qn}} for qn in outputs_qn]
    qualified_name = f"process:{step_name}:{layer_from}->{layer_to}@cluster"
    return client.create_process(name=step_name, qualified_name=qualified_name, inputs=inputs, outputs=outputs, description=f"Medallion {layer_from} to {layer_to}")
