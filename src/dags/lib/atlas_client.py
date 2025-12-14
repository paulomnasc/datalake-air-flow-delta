import os
import json
import time
import requests
from typing import Dict, List, Optional

ATLAS_HOST = os.getenv("ATLAS_HOST", "http://apache-atlas:21000")
ATLAS_USER = os.getenv("ATLAS_USER", "admin")
ATLAS_PASS = os.getenv("ATLAS_PASS", "admin")

BASE_URL = f"{ATLAS_HOST}/api/atlas/v2"

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
        self.backoff_seconds = float(os.getenv("ATLAS_BACKOFF_SECONDS", "1.0"))
        self.http_timeout = float(os.getenv("ATLAS_HTTP_TIMEOUT", "10.0"))

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
                # Retry on 5xx
                if r.status_code >= 500:
                    time.sleep(self.backoff_seconds * (2 ** attempt))
                    continue
                return r
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
            raise requests.HTTPError(f"Atlas POST {path} failed: {r.status_code} {r.text}", response=r)
        return r.json() if r.text else {}

    def _get(self, path: str, params: Optional[Dict] = None) -> Dict:
        r = self._request_with_retry("GET", path, params=params)
        try:
            r.raise_for_status()
        except requests.HTTPError:
            raise requests.HTTPError(f"Atlas GET {path} failed: {r.status_code} {r.text}", response=r)
        return r.json() if r.text else {}

    def create_hive_table(self, qualified_name: str, name: str, db: str, columns: Optional[List[Dict]] = None, description: str = "") -> Dict:
        entity = {
            "entities": [
                {
                    "typeName": "hive_table",
                    "attributes": {
                        "qualifiedName": qualified_name,
                        "name": name,
                        "description": description
                    },
                    "relationshipAttributes": {
                        "db": {
                            "typeName": "hive_db",
                            "uniqueAttributes": {"qualifiedName": f"{db}@cluster"}
                        }
                    }
                }
            ]
        }
        # Columns are relationships; to add them correctly we'd need to create column entities and reference them by GUID.
        # For simplicity and robustness, skip column creation here.
        return self._post("/entity/bulk", entity)

    def create_process(self, name: str, qualified_name: str, inputs: List[Dict], outputs: List[Dict], description: str = "") -> Dict:
        process = {
            "entities": [
                {
                    "typeName": "hive_process",
                    "attributes": {
                        "name": name,
                        "qualifiedName": qualified_name,
                        "description": description
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

