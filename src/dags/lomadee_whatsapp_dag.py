from airflow import DAG
from airflow.operators.python import PythonOperator
from airflow.models import Variable
from datetime import datetime, timedelta
import subprocess
import os

def run_whatsapp_script(**kwargs):
    # Path inside the worker container where the scripts are mapped
    script_path = '/usr/local/bin/scripts/send_whatsapp_products.py'
    
    # Read variables from Airflow with defaults matching the user request
    default_token = 'EAAYWHHdGTbMBRZBKUjGJ6If9wv0UXfedkwZBdMcacHCbVoSZCuOalTkDf6d5DuGVHDtPVLY6jWZCq4BQa0teZB66ldYWcuWhdcPSXYQtXPd0tczLp48Ul2wp1kaZCqhLNepc43k3NkSUgoEbrYAXqH5ZAc9XOJ6PGrdgA18uDiXOOdD4rTlddg02qE4utP0ZB3ZB4yOAUEnsYFjSnepeheUm9Eg33byZCSPZBZCZA0EebSSO508pUlhcIV0hg2PINo2cWUCDR1yYxeHVJuPPTZB5Tfy0tlZCLMAu7kHZBsy4sC9bsgZDZD'
    default_phone_id = '1261026707084295'
    default_recipient = '556191117028'
    
    # Get config overrides from DagRun
    dag_run = kwargs.get('dag_run')
    conf = dag_run.conf if (dag_run and dag_run.conf) else {}
    
    # Custom API / Group Mode variables (Precedence: DagRun conf -> Airflow Variable -> Default None)
    api_url = conf.get('whatsapp_api_url', Variable.get('whatsapp_api_url', default_var=None))
    api_token = conf.get('whatsapp_api_token', Variable.get('whatsapp_api_token', default_var=None))
    auth_header_name = conf.get('whatsapp_auth_header_name', Variable.get('whatsapp_auth_header_name', default_var='Authorization'))
    auth_header_value = conf.get('whatsapp_auth_header_value', Variable.get('whatsapp_auth_header_value', default_var='Bearer {token}'))
    payload_format = conf.get('whatsapp_payload_format', Variable.get('whatsapp_payload_format', default_var='{"number": "{recipient}", "text": "{message}"}'))
    
    # Meta API / Direct Mode variables (Precedence: DagRun conf -> Airflow Variable -> Default values)
    token = conf.get('whatsapp_access_token', Variable.get('whatsapp_access_token', default_var=default_token))
    phone_id = conf.get('whatsapp_phone_number_id', Variable.get('whatsapp_phone_number_id', default_var=default_phone_id))
    recipient = conf.get('whatsapp_recipient_number', Variable.get('whatsapp_recipient_number', default_var=default_recipient))
    msg_type = conf.get('whatsapp_message_type', Variable.get('whatsapp_message_type', default_var='template'))
    template_name = conf.get('whatsapp_template_name', Variable.get('whatsapp_template_name', default_var='hello_world'))
    template_lang = conf.get('whatsapp_template_language', Variable.get('whatsapp_template_language', default_var='en_US'))
    max_messages = str(conf.get('whatsapp_max_messages', Variable.get('whatsapp_max_messages', default_var='5')))
    
    # Construct subprocess environment
    env = os.environ.copy()
    env['WHATSAPP_RECIPIENT_NUMBER'] = recipient
    env['WHATSAPP_MAX_MESSAGES'] = max_messages
    
    # Inject Custom API values if api_url is provided
    if api_url:
        env['WHATSAPP_API_URL'] = api_url
        if api_token:
            env['WHATSAPP_API_TOKEN'] = api_token
        env['WHATSAPP_AUTH_HEADER_NAME'] = auth_header_name
        env['WHATSAPP_AUTH_HEADER_VALUE'] = auth_header_value
        env['WHATSAPP_PAYLOAD_FORMAT'] = payload_format
    else:
        # Fallback to Meta API values
        env['WHATSAPP_ACCESS_TOKEN'] = token
        env['WHATSAPP_PHONE_NUMBER_ID'] = phone_id
        env['WHATSAPP_MESSAGE_TYPE'] = msg_type
        env['WHATSAPP_TEMPLATE_NAME'] = template_name
        env['WHATSAPP_TEMPLATE_LANGUAGE'] = template_lang
    
    cmd = ['python', script_path]
    
    print(f"Executing WhatsApp script: {' '.join(cmd)}")
    print(f"Mode: {'Custom API (Group)' if api_url else 'Meta API (Direct)'}")
    print(f"Target Recipient/Group: {recipient}")
    print(f"Max Messages: {max_messages}")
    
    result = subprocess.run(cmd, capture_output=True, text=True, env=env)
    
    print("STDOUT:")
    print(result.stdout)
    
    if result.stderr:
        print("STDERR:")
        print(result.stderr)
        
    if result.returncode != 0:
        raise Exception(f"The lomadee WhatsApp script failed with exit code {result.returncode}")

default_args = {
    'owner': 'paulomnasc-558',
    'start_date': datetime(2025, 1, 1),
    'depends_on_past': False,
    'retries': 1,
    'retry_delay': timedelta(minutes=5),
}

dag = DAG(
    'lomadee_whatsapp_dag',
    default_args=default_args,
    schedule=None,  # Manual trigger
    catchup=False,
    description="Sends shortened Lomadee products to a WhatsApp number/group using Meta API or custom HTTP API",
    tags=['lomadee', 'whatsapp', 'notification']
)

whatsapp_task = PythonOperator(
    task_id='send_whatsapp_products',
    python_callable=run_whatsapp_script,
    dag=dag
)
