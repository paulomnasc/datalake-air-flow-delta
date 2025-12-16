import os
import json
import time
import logging
import requests
from typing import Dict, List, Optional

ATLAS_HOST = os.getenv("ATLAS_HOST", "http://apache-atlas:21000")
ATLAS_USER = os.getenv("ATLAS_USER", "admin")
ATLAS_PASS = os.getenv("ATLAS_PASS", "admin")

BASE_URL = f"{ATLAS_HOST}/api/atlas/v2"

log = logging.getLogger(__name__)

class AtlasClient:
    def __init__(self, host: Optional[str] = None, user: Optional[str] = None, password: Optional[str] = None):
        self.host = host or ATLAS_HOST
        self.user = user or ATLAS_USER
        self.password = password or ATLAS_PASS
        self.base_url = f"{self.host}/api/atlas/v2"
        self.fallback_url = f"{self.host}/api/atlas"
        self.session = requests.Session()
        self.session.auth = (self.user, self.password)
        self.session.headers.update({"Content-Type": "application/json"})
        self.max_retries = int(os.getenv("ATLAS_MAX_RETRIES", "5"))
        self.backoff_seconds = float(os.getenv("ATLAS_BACKOFF_SECONDS", "2.0"))
        self.http_timeout = float(os.getenv("ATLAS_HTTP_TIMEOUT", "60.0"))

    def _request_with_retry(self, method: str, path: str, payload: Optional[Dict] = None, params: Optional[Dict] = None) -> requests.Response:
        last_exc = None
        last_resp: Optional[requests.Response] = None
        for attempt in range(self.max_retries):
            try:
                url_v2 = f"{self.base_url}{path}"
                url_v1 = f"{self.fallback_url}{path}"
                if method == "POST":
                    r = self.session.post(url_v2, data=json.dumps(payload), timeout=self.http_timeout)
                    if r.status_code == 404:
                        r = self.session.post(url_v1, data=json.dumps(payload), timeout=self.http_timeout)
                else:
                    r = self.session.get(url_v2, params=params, timeout=self.http_timeout)
                    if r.status_code == 404:
                        r = self.session.get(url_v1, params=params, timeout=self.http_timeout)

                last_resp = r
                # Retry on 5xx or timeout
                if r.status_code >= 500:
                    time.sleep(self.backoff_seconds * (2 ** attempt))
                    continue
                return r
            except (requests.exceptions.Timeout, requests.exceptions.ConnectionError) as e:
                last_exc = e
                wait_time = self.backoff_seconds * (2 ** attempt)
                print(f"[ATLAS] Timeout/connection error (attempt {attempt+1}/{self.max_retries}), waiting {wait_time}s: {e}")
                time.sleep(wait_time)
            except requests.RequestException as e:
                last_exc = e
                time.sleep(self.backoff_seconds * (2 ** attempt))
        # After retries, if we have a response, return it to surface HTTPError with body
        if last_resp is not None:
            return last_resp
        if last_exc:
            raise last_exc
        raise RuntimeError("Atlas request failed without exception")

    def _post(self, path: str, payload: Dict) -> Dict:
        r = self._request_with_retry("POST", path, payload=payload)
        try:
            r.raise_for_status()
        except requests.HTTPError:
            # Include response body for easier debugging
            log.error("[ATLAS] POST %s failed: %s | body: %s", path, r.status_code, (r.text or "<empty>"))
            raise requests.HTTPError(f"Atlas POST {path} failed: {r.status_code} {r.text}", response=r)
        return r.json() if r.text else {}

    def _get(self, path: str, params: Optional[Dict] = None) -> Dict:
        r = self._request_with_retry("GET", path, params=params)
        try:
            r.raise_for_status()
        except requests.HTTPError:
            raise requests.HTTPError(f"Atlas GET {path} failed: {r.status_code} {r.text}", response=r)
        return r.json() if r.text else {}

    def create_mysql_table(self, qualified_name: str, name: str, database: str, server: str, owner: str = "mysql") -> Dict:
        """Create a MySQL table entity in Atlas to represent the data source.
        Uses hive_table type with special database 'mysql' to identify it as external source."""
        
        # Criar database mysql se não existir
        self.ensure_hive_db("mysql")
        
        mysql_entity = {
            "typeName": "hive_table",
            "attributes": {
                "qualifiedName": qualified_name,
                "name": name,
                "description": f"MySQL table {name} from database {database} on server {server}",
                "tableType": "EXTERNAL_TABLE",
                "owner": owner
            },
            "relationshipAttributes": {
                "db": {
                    "typeName": "hive_db",
                    "uniqueAttributes": {"qualifiedName": "mysql@cluster"}
                }
            }
        }
        
        payload = {"entities": [mysql_entity]}
        return self._post("/entity/bulk", payload)

    def create_hive_table(self, qualified_name: str, name: str, db: str, columns: Optional[List[Dict]] = None, description: str = "", owner: str = "airflow") -> Dict:
        table_entity = {
            "typeName": "hive_table",
            "attributes": {
                "qualifiedName": qualified_name,
                "name": name,
                "description": description,
                "owner": owner,
                "tableType": "EXTERNAL_TABLE"
            },
            "relationshipAttributes": {
                "db": {
                    "typeName": "hive_db",
                    "uniqueAttributes": {"qualifiedName": f"{db}@cluster"}
                }
            }
        }

        # Build column entities when provided to populate Schema tab in Atlas
        column_entities: List[Dict] = []
        column_refs: List[Dict] = []
        if columns:
            for col in columns:
                col_qn = col.get("qualifiedName")
                col_name = col.get("name")
                col_type = col.get("type", "string")
                if not col_qn or not col_name:
                    continue
                column_entities.append({
                    "typeName": "hive_column",
                    "attributes": {
                        "qualifiedName": col_qn,
                        "name": col_name,
                        "type": col_type
                    },
                    "relationshipAttributes": {
                        "table": {
                            "typeName": "hive_table",
                            "uniqueAttributes": {"qualifiedName": qualified_name}
                        }
                    }
                })
                column_refs.append({"typeName": "hive_column", "uniqueAttributes": {"qualifiedName": col_qn}})

        # Não definir 'columns' no lado da tabela para evitar referências
        # a entidades ainda não criadas; as colunas apontam para a tabela.
        payload = {"entities": [table_entity] + column_entities}
        return self._post("/entity/bulk", payload)

    def link_table_columns(self, table_qualified_name: str, column_qualified_names: List[str]) -> Dict:
        """Ensure table has 'columns' relationship set to existing column entities.
        Columns must already exist (we create them in bulk above). This sets the table→columns relationship
        so Atlas UI Schema tab can list them.
        """
        if not column_qualified_names:
            return {}
        table_update = {
            "entities": [
                {
                    "typeName": "hive_table",
                    "uniqueAttributes": {"qualifiedName": table_qualified_name},
                    "relationshipAttributes": {
                        "columns": [
                            {"typeName": "hive_column", "uniqueAttributes": {"qualifiedName": qn}}
                            for qn in column_qualified_names
                        ]
                    }
                }
            ]
        }
        return self._post("/entity/bulk", table_update)

    def add_columns_to_table(self, table_qualified_name: str, columns: List[Dict]) -> Dict:
        """Add columns to an existing table. Table must already exist.
        This creates column entities and links them to the table.
        """
        if not columns:
            return {}
        
        column_entities = []
        for col in columns:
            col_qn = col.get("qualifiedName")
            col_name = col.get("name")
            col_type = col.get("type", "string")
            if not col_qn or not col_name:
                continue
            column_entities.append({
                "typeName": "hive_column",
                "attributes": {
                    "qualifiedName": col_qn,
                    "name": col_name,
                    "type": col_type
                },
                "relationshipAttributes": {
                    "table": {
                        "typeName": "hive_table",
                        "uniqueAttributes": {"qualifiedName": table_qualified_name}
                    }
                }
            })
        
        if not column_entities:
            return {}
        
        payload = {"entities": column_entities}
        return self._post("/entity/bulk", payload)

    def create_process(
        self,
        name: str,
        qualified_name: str,
        inputs: List[Dict],
        outputs: List[Dict],
        description: str = "",
        start_time_ms: Optional[int] = None,
        end_time_ms: Optional[int] = None,
    ) -> Dict:
        # hive_process exige startTime; usamos timestamp atual em ms se não for informado
        now_ms = int(time.time() * 1000)
        start_ms = start_time_ms or now_ms
        end_ms = end_time_ms or now_ms

        process = {
            "entities": [
                {
                    "typeName": "hive_process",
                    "attributes": {
                        "name": name,
                        "qualifiedName": qualified_name,
                        "description": description,
                        "queryId": qualified_name,
                        "startTime": start_ms,
                        "endTime": end_ms,
                        "userName": self.user,
                        "queryText": description or name,
                        "queryPlan": description or name,
                        "operationType": "ETL",
                    },
                    "relationshipAttributes": {
                        "inputs": inputs,
                        "outputs": outputs
                    }
                }
            ]
        }
        return self._post("/entity/bulk", process)

    def ensure_hive_db(self, db_name: str) -> Dict:
        db_qn = f"{db_name}@cluster"
        payload = {
            "entities": [
                {
                    "typeName": "hive_db",
                    "attributes": {
                        "qualifiedName": db_qn,
                        "name": db_name,
                        "clusterName": "cluster",
                        "description": f"Database for {db_name}"
                    }
                }
            ]
        }
        return self._post("/entity/bulk", payload)

    def admin_version(self) -> Dict:
        # admin/version exists only on v1 route
        try:
            return self._get("/admin/version")
        except requests.HTTPError:
            # Fallback to v1 explicitly
            r = self.session.get(f"{self.fallback_url}/admin/version", timeout=self.http_timeout)
            r.raise_for_status()
            return r.json() if r.text else {}

    def wait_until_ready(self, timeout_seconds: int = 60) -> None:
        """
        Wait until Atlas admin/version responds successfully or until timeout.
        Raises TimeoutError if not ready within the given timeout.
        """
        start = time.time()
        attempt = 0
        while (time.time() - start) < timeout_seconds:
            try:
                self.admin_version()
                return
            except Exception:
                time.sleep(self.backoff_seconds * (2 ** attempt))
                attempt += 1
        raise TimeoutError("Atlas is not ready within timeout")

    def get_entity_by_qualified_name(self, type_name: str, qualified_name: str) -> Dict:
        """Fetch an entity by its qualifiedName to verify existence/indexing."""
        params = {"attr:qualifiedName": qualified_name}
        return self._get(f"/entity/uniqueAttribute/type/{type_name}", params=params)

    @staticmethod
    def format_http_error(err: Exception) -> str:
        """Return a concise, hint-rich error string for Atlas HTTP errors."""
        import requests

        if isinstance(err, requests.HTTPError) and err.response is not None:
            status = err.response.status_code
            text = err.response.text or ""
            hints = []

            lowered = text.lower()
            if "starttime" in lowered:
                hints.append("add startTime (ms) in hive_process")
            if "username" in lowered:
                hints.append("add userName in hive_process")
            if "querytext" in lowered:
                hints.append("add queryText in hive_process")
            if "queryplan" in lowered:
                hints.append("add queryPlan in hive_process")
            if "hive_process" in lowered and "mandatory" in lowered:
                hints.append("check mandatory hive_process attrs")
            if "solr" in lowered or "no live solr" in lowered:
                hints.append("Solr indisponivel/fora do ar")

            body_excerpt = text[:300].replace("\n", " ")
            hint_str = f" | hints: {', '.join(hints)}" if hints else ""
            return f"HTTP {status}: {body_excerpt}{hint_str}"

        return str(err)

