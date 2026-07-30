#
# Licensed to the Apache Software Foundation (ASF) under one
# or more contributor license agreements.  See the NOTICE file
# distributed with this work for additional information
# regarding copyright ownership.  The ASF licenses this file
# to you under the Apache License, Version 2.0 (the
# "License"); you may not use this file except in compliance
# with the License.  You may obtain a copy of the License at
#
#   http://www.apache.org/licenses/LICENSE-2.0
#
# Unless required by applicable law or agreed to in writing,
# software distributed under the License is distributed on an
# "AS IS" BASIS, WITHOUT WARRANTIES OR CONDITIONS OF ANY
# KIND, either express or implied.  See the License for the
# specific language governing permissions and limitations
# under the License.
"""Default configuration for the Airflow webserver."""

from __future__ import annotations

import os
import logging
from sqlalchemy import create_engine, text
from flask_appbuilder.const import AUTH_DB
from airflow.providers.fab.auth_manager.security_manager.override import FabAirflowSecurityManagerOverride
from airflow.providers.fab.auth_manager.fab_auth_manager import FabAuthManager
from airflow.models.dag import DagModel
from airflow.utils.session import provide_session

logger = logging.getLogger(__name__)

basedir = os.path.abspath(os.path.dirname(__file__))

# Flask-WTF flag for CSRF
WTF_CSRF_ENABLED = True
WTF_CSRF_TIME_LIMIT = None

# The authentication type
AUTH_TYPE = AUTH_DB

class GroupOwnerSecurityManager(FabAirflowSecurityManagerOverride):
    
    def _is_admin(self, user):
        """Verifica se o usuário possui a role Admin."""
        if not user or user.is_anonymous:
            return False
        return any(role.name == "Admin" for role in user.roles)

    def _get_user_group_emails_from_mysql(self, email):
        """
        Consulta o MySQL do sistema web para obter os e-mails dos grupos
        aos quais o usuário pertence.
        """
        emails = set()
        if not email:
            return emails
            
        try:
            # String de conexão utilizando as credenciais existentes do .env
            engine = create_engine("mysql://root:YM11rMrT32xH0E6N@mysql:3306/lista_revisao2")
            query = text("""
                SELECT g.email 
                FROM grupo g
                JOIN grupo_usuario gu ON g.id = gu.id_grupo
                JOIN usuario u ON u.id = gu.id_usuario
                WHERE LOWER(u.email) = :email
            """)
            with engine.connect() as conn:
                result = conn.execute(query, {"email": email.lower()})
                for row in result:
                    emails.add(row[0].lower())
        except Exception as e:
            logger.error(f"Erro ao buscar grupos do MySQL: {str(e)}")
            
        return emails

    def _get_user_allowed_owners(self, user):
        """Retorna todos os donos válidos para o usuário logado."""
        allowed_owners = set()
        if not user or user.is_anonymous:
            return allowed_owners

        # Username e email do próprio usuário
        if user.username:
            allowed_owners.add(user.username.lower())
        if hasattr(user, 'email') and user.email:
            user_email = user.email.lower()
            allowed_owners.add(user_email)
            # Busca os e-mails dos grupos do MySQL
            allowed_owners.update(self._get_user_group_emails_from_mysql(user_email))

        # Roles diretas atribuídas ao usuário no Airflow
        if hasattr(user, 'roles') and user.roles:
            for role in user.roles:
                allowed_owners.add(role.name.lower())
        
        return allowed_owners

    @provide_session
    def get_authorized_dag_ids(self, user=None, session=None):
        """Filtra a listagem de DAGs na UI."""
        authorized_dag_ids = super().get_authorized_dag_ids(user=user, session=session)
        is_admin = self._is_admin(user)

        # Se for Admin, vê tudo
        if is_admin:
            return authorized_dag_ids

        allowed_owners = self._get_user_allowed_owners(user)

        # Buscar donos das DAGs autorizadas no banco de dados do Airflow
        dags = session.query(DagModel.dag_id, DagModel.owners).filter(
            DagModel.dag_id.in_(authorized_dag_ids)
        ).all()

        filtered_dag_ids = set()
        for dag_id, owners_str in dags:
            dag_owners = [o.strip().lower() for o in (owners_str or "").split(",") if o.strip()]
            
            # Regra: DAGs sem owner ou com owner 'airflow' são exclusivas para Admin
            if not dag_owners or (len(dag_owners) == 1 and dag_owners[0] == "airflow"):
                continue
                
            # Verifica se o usuário tem permissão sobre algum dos donos
            if any(owner in allowed_owners for owner in dag_owners):
                filtered_dag_ids.add(dag_id)
                
        return filtered_dag_ids

    def is_authorized_dag(self, method, access_entity=None, details=None, user=None):
        """Valida acesso a ações específicas na DAG (API, detalhes, execução)."""
        if not user:
            from flask import g
            user = g.user

        default_auth = super().is_authorized_dag(method, access_entity, details, user)
        if not default_auth:
            return False

        is_admin = self._is_admin(user)
        if is_admin:
            return True

        dag_id = details.id if details else None
        if not dag_id:
            return default_auth

        @provide_session
        def check_owner(session=None):
            dag = session.query(DagModel).filter(DagModel.dag_id == dag_id).first()
            if not dag:
                return False
            
            dag_owners = [o.strip().lower() for o in (dag.owners or "").split(",") if o.strip()]
            
            # Sem dono ou padrão 'airflow' -> somente Admin
            if not dag_owners or (len(dag_owners) == 1 and dag_owners[0] == "airflow"):
                return False
                
            allowed_owners = self._get_user_allowed_owners(user)
            return any(owner in allowed_owners for owner in dag_owners)

        return check_owner()


# Ativação do Custom Security Manager
SECURITY_MANAGER_CLASS = GroupOwnerSecurityManager

class CustomFabAuthManager(FabAuthManager):
    def _is_authorized_dag(
        self,
        method,
        details=None,
        user=None,
    ) -> bool:
        if not user:
            user = self.get_user()

        # 1. Se for Admin, tem acesso global
        if self.security_manager._is_admin(user):
            return True

        # 2. Se não houver detalhes da DAG, delegamos para a verificação padrão (baseado em roles)
        if not details or not details.id:
            return super()._is_authorized_dag(method, details, user)

        # 3. Validar acesso à DAG específica pelo proprietário (owner)
        dag_id = details.id
        session = self.security_manager.get_session
        dag = session.query(DagModel).filter(DagModel.dag_id == dag_id).first()
        if not dag:
            return False

        dag_owners = [o.strip().lower() for o in (dag.owners or "").split(",") if o.strip()]
        
        # DAGs sem proprietário ou 'airflow' só são visíveis para Admin
        if not dag_owners or (len(dag_owners) == 1 and dag_owners[0] == "airflow"):
            return False

        allowed_owners = self.security_manager._get_user_allowed_owners(user)
        return any(owner in allowed_owners for owner in dag_owners)

    @provide_session
    def get_permitted_dag_ids(
        self,
        *,
        methods=None,
        user=None,
        session=None,
    ) -> set[str]:
        if not user:
            user = self.get_user()
            
        # 1. Se for Admin, retorna todas as DAGs
        if self.security_manager._is_admin(user):
            return {dag.dag_id for dag in session.query(DagModel.dag_id).all()}

        # 2. Obter proprietários autorizados para o usuário
        allowed_owners = self.security_manager._get_user_allowed_owners(user)
        if not allowed_owners:
            return set()

        # 3. Buscar todas as DAGs e seus owners no banco
        dags = session.query(DagModel.dag_id, DagModel.owners).all()

        filtered_dag_ids = set()
        for dag_id, owners_str in dags:
            dag_owners = [o.strip().lower() for o in (owners_str or "").split(",") if o.strip()]
            
            # Regra: DAGs sem owner ou com owner 'airflow' são exclusivas para Admin
            if not dag_owners or (len(dag_owners) == 1 and dag_owners[0] == "airflow"):
                continue
                
            # Verifica se o usuário tem permissão sobre algum dos donos
            if any(owner in allowed_owners for owner in dag_owners):
                filtered_dag_ids.add(dag_id)
                
        return filtered_dag_ids
