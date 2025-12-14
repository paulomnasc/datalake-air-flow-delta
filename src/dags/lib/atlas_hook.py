from typing import Dict, List, Optional
from .atlas_client import AtlasClient

class AtlasHook:
    def __init__(self, host: Optional[str] = None, user: Optional[str] = None, password: Optional[str] = None):
        self.client = AtlasClient(host=host, user=user, password=password)

    def ensure_db(self, db_name: str) -> Dict:
        return self.client.ensure_hive_db(db_name)

    def create_table(self, qualified_name: str, name: str, db: str, columns: Optional[List[Dict]] = None, description: str = "") -> Dict:
        return self.client.create_hive_table(qualified_name, name, db, columns, description)

    def create_process(self, name: str, qualified_name: str, inputs_qn: List[str], outputs_qn: List[str], description: str = "") -> Dict:
        inputs = [{"typeName": "hive_table", "uniqueAttributes": {"qualifiedName": qn}} for qn in inputs_qn]
        outputs = [{"typeName": "hive_table", "uniqueAttributes": {"qualifiedName": qn}} for qn in outputs_qn]
        return self.client.create_process(name=name, qualified_name=qualified_name, inputs=inputs, outputs=outputs, description=description)

    def admin_version(self) -> Dict:
        return self.client.admin_version()

    def wait_until_ready(self, timeout_seconds: int = 60) -> None:
        return self.client.wait_until_ready(timeout_seconds=timeout_seconds)
