#!/usr/bin/env python3
"""
Script de Teste: Verificação de Data Lineage no Apache Atlas

Este script verifica se o lineage está sendo registrado corretamente após as correções.
"""

import requests
from requests.auth import HTTPBasicAuth
import json
import sys
from typing import Dict, List

# Configurações do Atlas
ATLAS_URL = "http://localhost:21000"
ATLAS_USER = "admin"
ATLAS_PASS = "admin"

class AtlasLineageValidator:
    def __init__(self, url: str, user: str, password: str):
        self.url = url
        self.auth = HTTPBasicAuth(user, password)
        self.session = requests.Session()
        self.session.auth = self.auth
    
    def check_connection(self) -> bool:
        """Verifica se o Atlas está acessível"""
        try:
            response = self.session.get(f"{self.url}/api/atlas/admin/version", timeout=10)
            if response.status_code == 200:
                version = response.json()
                print(f"✅ Atlas conectado com sucesso!")
                print(f"   Versão: {version.get('Version', 'Unknown')}")
                return True
            else:
                print(f"❌ Atlas respondeu com código: {response.status_code}")
                return False
        except Exception as e:
            print(f"❌ Erro ao conectar no Atlas: {e}")
            return False
    
    def search_entities(self, type_name: str, limit: int = 100) -> List[Dict]:
        """Busca entidades por tipo"""
        try:
            params = {
                "typeName": type_name,
                "limit": limit
            }
            response = self.session.get(
                f"{self.url}/api/atlas/v2/search/basic",
                params=params,
                timeout=30
            )
            
            if response.status_code == 200:
                data = response.json()
                entities = data.get('entities', [])
                return entities
            else:
                print(f"⚠️  Erro ao buscar {type_name}: {response.status_code}")
                return []
        except Exception as e:
            print(f"❌ Erro ao buscar entidades: {e}")
            return []
    
    def get_lineage(self, guid: str, depth: int = 3) -> Dict:
        """Obtém lineage de uma entidade"""
        try:
            params = {
                "depth": depth,
                "direction": "BOTH"
            }
            response = self.session.get(
                f"{self.url}/api/atlas/v2/lineage/{guid}",
                params=params,
                timeout=30
            )
            
            if response.status_code == 200:
                return response.json()
            else:
                print(f"⚠️  Erro ao obter lineage: {response.status_code}")
                return {}
        except Exception as e:
            print(f"❌ Erro ao obter lineage: {e}")
            return {}
    
    def validate_lineage_chain(self):
        """Valida se a cadeia de lineage MySQL → Raw → Bronze → Silver → Gold existe"""
        print("\n" + "="*70)
        print("🔍 VALIDAÇÃO DE DATA LINEAGE")
        print("="*70 + "\n")
        
        # 1. Verificar entidades hive_table
        print("1️⃣  Verificando entidades hive_table...")
        tables = self.search_entities("hive_table")
        print(f"   Encontradas {len(tables)} tabelas")
        
        if tables:
            print("\n   📋 Tabelas registradas:")
            for table in tables:
                attrs = table.get('attributes', {})
                qn = attrs.get('qualifiedName', 'N/A')
                name = attrs.get('name', 'N/A')
                print(f"      • {name} ({qn})")
        else:
            print("   ⚠️  Nenhuma tabela encontrada!")
            return False
        
        # 2. Verificar processos hive_process
        print("\n2️⃣  Verificando processos hive_process...")
        processes = self.search_entities("hive_process")
        print(f"   Encontrados {len(processes)} processos")
        
        if processes:
            print("\n   🔄 Processos registrados:")
            for process in processes:
                attrs = process.get('attributes', {})
                name = attrs.get('name', 'N/A')
                qn = attrs.get('qualifiedName', 'N/A')
                print(f"      • {name}")
                print(f"        QN: {qn}")
        else:
            print("   ⚠️  Nenhum processo encontrado!")
            print("   💡 Dica: Verifique se ATLAS_REGISTER_PROCESSES=true")
            return False
        
        # 3. Analisar lineage de uma tabela Gold
        print("\n3️⃣  Analisando lineage de tabela Gold...")
        gold_tables = [t for t in tables if 'gold' in t.get('attributes', {}).get('name', '').lower()]
        
        if not gold_tables:
            print("   ⚠️  Nenhuma tabela Gold encontrada!")
            return False
        
        gold_table = gold_tables[0]
        gold_name = gold_table.get('attributes', {}).get('name', 'Unknown')
        gold_guid = gold_table.get('guid')
        
        print(f"   Analisando: {gold_name}")
        print(f"   GUID: {gold_guid}")
        
        lineage = self.get_lineage(gold_guid)
        
        if not lineage:
            print("   ❌ Não foi possível obter lineage!")
            return False
        
        # Analisar relações de lineage
        relations = lineage.get('relations', [])
        print(f"\n   📊 Relações encontradas: {len(relations)}")
        
        if relations:
            print("\n   🔗 Cadeia de Lineage:")
            for rel in relations:
                from_guid = rel.get('fromEntityId')
                to_guid = rel.get('toEntityId')
                
                # Buscar nomes das entidades
                from_entity = next((e for e in lineage.get('guidEntityMap', {}).values() 
                                  if e.get('guid') == from_guid), {})
                to_entity = next((e for e in lineage.get('guidEntityMap', {}).values() 
                                if e.get('guid') == to_guid), {})
                
                from_name = from_entity.get('attributes', {}).get('name', 'Unknown')
                to_name = to_entity.get('attributes', {}).get('name', 'Unknown')
                
                print(f"      {from_name} → {to_name}")
        else:
            print("   ⚠️  Nenhuma relação de lineage encontrada!")
            return False
        
        # 4. Verificar presença de camadas esperadas
        print("\n4️⃣  Verificando presença de todas as camadas...")
        
        expected_layers = ['mysql', 'raw', 'bronze', 'silver', 'gold']
        found_layers = set()
        
        all_entities = lineage.get('guidEntityMap', {}).values()
        for entity in all_entities:
            name = entity.get('attributes', {}).get('name', '').lower()
            for layer in expected_layers:
                if layer in name:
                    found_layers.add(layer)
        
        print(f"   Camadas encontradas: {', '.join(sorted(found_layers))}")
        missing_layers = set(expected_layers) - found_layers
        
        if missing_layers:
            print(f"   ⚠️  Camadas faltando: {', '.join(sorted(missing_layers))}")
            return False
        else:
            print("   ✅ Todas as camadas presentes!")
            return True

def main():
    print("\n" + "🔧 "*20)
    print("   VALIDADOR DE DATA LINEAGE - APACHE ATLAS")
    print("🔧 "*20 + "\n")
    
    validator = AtlasLineageValidator(ATLAS_URL, ATLAS_USER, ATLAS_PASS)
    
    # Verificar conexão
    if not validator.check_connection():
        print("\n❌ Não foi possível conectar ao Atlas!")
        print("\n💡 Dicas:")
        print("   1. Verifique se o container está rodando: docker ps | grep atlas")
        print("   2. Verifique os logs: docker logs apache-atlas")
        print("   3. Aguarde 2-5 minutos após iniciar o Atlas")
        sys.exit(1)
    
    # Validar lineage
    success = validator.validate_lineage_chain()
    
    print("\n" + "="*70)
    if success:
        print("✅ VALIDAÇÃO CONCLUÍDA COM SUCESSO!")
        print("="*70)
        print("\n🎉 O data lineage está funcionando corretamente!")
        print("\n📍 Próximos passos:")
        print("   1. Acesse http://localhost:21000 (admin/admin)")
        print("   2. Busque por uma tabela Gold")
        print("   3. Visualize a aba 'Lineage' para ver o diagrama completo")
        sys.exit(0)
    else:
        print("⚠️  VALIDAÇÃO INCOMPLETA")
        print("="*70)
        print("\n🔍 Possíveis causas:")
        print("   1. DAG ainda não foi executada")
        print("   2. ATLAS_REGISTER_PROCESSES não está habilitado")
        print("   3. Atlas ainda está processando as entidades (aguarde alguns minutos)")
        print("\n💡 Execute uma DAG que use mysql_to_medallion() e tente novamente")
        sys.exit(1)

if __name__ == "__main__":
    main()
