#!/usr/bin/env python3
"""
Script para sincronizar usuários com Airflow via CLI
Deve ser executado com: python3 /path/to/airflow_sync_user.py <user_id> <email> <first_name> <last_name> <password> <role>
"""

import subprocess
import sys
import json
import os

def run_airflow_command(cmd):
    """Executa comando no container Airflow e retorna output"""
    try:
        full_cmd = f"docker exec airflow-webserver {cmd}"
        result = subprocess.run(full_cmd, shell=True, capture_output=True, text=True, timeout=30)
        return {
            'returncode': result.returncode,
            'stdout': result.stdout,
            'stderr': result.stderr
        }
    except subprocess.TimeoutExpired:
        return {
            'returncode': -1,
            'stdout': '',
            'stderr': 'Command timeout'
        }
    except Exception as e:
        return {
            'returncode': -1,
            'stdout': '',
            'stderr': str(e)
        }

def sync_user(user_id, email, first_name, last_name, password, role='Viewer', username_override=None):
    """Sincroniza usuário com Airflow"""
    username = username_override or f"user-{user_id}"
    
    # Escapar valores para shell
    username_esc = f"'{username}'"
    email_esc = f"'{email}'"
    first_name_esc = f"'{first_name}'"
    last_name_esc = f"'{last_name}'"
    password_esc = f"'{password}'"
    role_esc = f"'{role}'"
    
    # Tentar criar usuário
    create_cmd = (
        f"airflow users create "
        f"--username {username_esc} "
        f"--password {password_esc} "
        f"--firstname {first_name_esc} "
        f"--lastname {last_name_esc} "
        f"--role {role_esc} "
        f"--email {email_esc}"
    )
    
    result = run_airflow_command(create_cmd)
    
    # Se sucesso
    if result['returncode'] == 0 and 'created' in result['stdout']:
        return {
            'success': True,
            'action': 'created',
            'message': f"User {username} created successfully",
            'username': username
        }
    
    # Se usuário já existe
    if 'already exists' in result['stdout'] or 'already exists' in result['stderr']:
        # Tentar atualizar
        update_cmd = (
            f"airflow users update "
            f"--username {username_esc} "
            f"--password {password_esc} "
            f"--firstname {first_name_esc} "
            f"--lastname {last_name_esc} "
            f"--email {email_esc}"
        )
        
        result = run_airflow_command(update_cmd)
        
        if result['returncode'] == 0:
            return {
                'success': True,
                'action': 'updated',
                'message': f"User {username} updated successfully",
                'username': username
            }
        else:
            return {
                'success': False,
                'action': 'error',
                'message': f"Failed to update user: {result['stderr']}",
                'username': username
            }
    
    # Outro erro
    return {
        'success': False,
        'action': 'error',
        'message': f"Failed to create user: {result['stdout']} {result['stderr']}",
        'username': username
    }

if __name__ == '__main__':
    if len(sys.argv) < 7:
        result = {
            'success': False,
            'action': 'error',
            'message': 'Usage: airflow_sync_user.py <user_id> <email> <first_name> <last_name> <password> <role> [username]'
        }
    else:
        username_override = sys.argv[7] if len(sys.argv) > 7 else None
        result = sync_user(
            sys.argv[1],  # user_id
            sys.argv[2],  # email
            sys.argv[3],  # first_name
            sys.argv[4],  # last_name
            sys.argv[5],  # password
            sys.argv[6] if len(sys.argv) > 6 else 'Viewer',  # role
            username_override
        )
    
    print(json.dumps(result))
    sys.exit(0 if result.get('success') else 1)
