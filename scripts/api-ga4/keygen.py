import os
from google.oauth2 import service_account
import google.auth.transport.requests

# Carrega a chave da Service Account

SCOPES = ['https://www.googleapis.com/auth/analytics.readonly']
json_path = os.path.join(os.path.dirname(__file__), 'my-project-3276-359418-e60090efffb9.json')
creds = service_account.Credentials.from_service_account_file(json_path, scopes=SCOPES)


# Gera o token
auth_req = google.auth.transport.requests.Request()
creds.refresh(auth_req)

print(f"Seu novo token para o JSON: {creds.token}")