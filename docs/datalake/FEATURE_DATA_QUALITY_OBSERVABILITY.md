# Feature: Data Quality Observability & Detailed Logging

## 📋 Status
**Estado**: Backlog  
**Prioridade**: Alta  
**Esforço Estimado**: 3-6 sprints (faseado)  
**Categoria**: Data Quality, Observability, Product Differentiator  

---

## 🎯 Problema Atual

### Limitação Identificada
Atualmente, a camada Silver adiciona colunas de qualidade de dados:
- `DataQualityRulesPass: 3`
- `DataQualityRulesFail: 2`  
- `DataQualityRulesSkip: 0`
- `DataQualityEvaluationResult: "Failed"`

**Problema**: Usuário sabe que tem falhas, mas **não sabe quais regras específicas falharam**.

### Cenário Real
```sql
SELECT * FROM silver.customers WHERE DataQualityEvaluationResult = 'Failed';

-- Resultado:
-- customerNumber=103, DataQualityRulesFail=2
-- ❌ Quais das 5 regras falharam?
-- ❌ Por que falharam?
-- ❌ Qual o valor problemático?
```

### Impacto no Negócio
- ⏱️ **Time-to-resolution aumenta**: Analista precisa investigar manualmente
- 🔍 **Debugging difícil**: Sem rastreabilidade clara
- 📊 **Dashboards limitados**: Não dá para criar "Top 5 problemas de qualidade"
- 💼 **Compliance**: Dificulta auditoria (LGPD, SOX, ISO)

---

## 🚀 Solução Proposta

### Implementação em 3 Fases

---

## **FASE 1: MVP - Quick Win** (Sprint 1-2)
**Objetivo**: Adicionar detalhes mínimos sem complexidade

### Features
1. **Nova coluna `DataQualityFailureReasons`** (string/array)
   ```python
   DataQualityFailureReasons: "duplicate,email_invalid,outlier_creditlimit"
   ```

2. **Nova coluna `DataQualityDetails`** (JSON - opcional)
   ```json
   {
     "null_check": "pass",
     "type_validation": "pass", 
     "duplicate_check": "fail",
     "range_validation": "fail:creditLimit=999999 (z-score=6.7)",
     "pattern_validation": "fail:email='invalid-email'"
   }
   ```

### Queries Habilitadas
```sql
-- Encontrar todas duplicatas
SELECT * FROM silver.customers 
WHERE DataQualityFailureReasons LIKE '%duplicate%';

-- Contar problemas por tipo
SELECT 
  CASE 
    WHEN DataQualityFailureReasons LIKE '%email%' THEN 'email'
    WHEN DataQualityFailureReasons LIKE '%duplicate%' THEN 'duplicate'
    WHEN DataQualityFailureReasons LIKE '%outlier%' THEN 'outlier'
  END as tipo_problema,
  COUNT(*) as total
FROM silver.customers
WHERE DataQualityEvaluationResult = 'Failed'
GROUP BY tipo_problema;
```

### Documentação
- Atualizar `TRANSFORMACOES_SILVER.md` com novos campos
- Criar queries de exemplo no Power BI

### Esforço
- **Dev**: 3-5 dias
- **QA**: 2 dias
- **Docs**: 1 dia

---

## **FASE 2: Enterprise - Auditoria Completa** (Sprint 3-4)

### Features

#### 1. **Tabela de Auditoria Separada**
```sql
CREATE TABLE silver.data_quality_audit (
  audit_id BIGINT PRIMARY KEY,
  table_name VARCHAR(100),        -- Ex: "customers"
  row_identifier VARCHAR(500),    -- Ex: "customerNumber=103"
  rule_name VARCHAR(100),          -- Ex: "email_validation"
  rule_status VARCHAR(10),         -- "pass" / "fail" / "skip"
  failure_reason TEXT,             -- Ex: "Email format invalid: john@"
  field_name VARCHAR(100),         -- Ex: "email"
  field_value TEXT,                -- Ex: "john@invalid"
  severity VARCHAR(20),            -- "warning" / "critical"
  timestamp TIMESTAMP,
  processing_batch_id VARCHAR(100) -- Rastreamento do pipeline
);
```

#### 2. **Dashboard de Qualidade**
- **Top 5 regras que mais falham**
- **Tendência histórica** (piora/melhora ao longo do tempo)
- **Taxa de aprovação por tabela**
- **Alertas**: quando taxa < 80%

#### 3. **API REST para Consultas**
```bash
GET /api/quality/failures?table=customers&rule=email_validation&limit=100
GET /api/quality/summary?date=2026-01-18
GET /api/quality/trends?table=customers&days=30
```

#### 4. **Views Pré-criadas**
```sql
-- View: Problemas críticos (último processamento)
CREATE VIEW vw_quality_critical_issues AS
SELECT 
  table_name,
  row_identifier,
  rule_name,
  failure_reason,
  timestamp
FROM silver.data_quality_audit
WHERE severity = 'critical' 
  AND timestamp > NOW() - INTERVAL '24 hours'
ORDER BY timestamp DESC;

-- View: Top problemas (agregado)
CREATE VIEW vw_quality_top_issues AS
SELECT 
  rule_name,
  COUNT(*) as failure_count,
  COUNT(DISTINCT table_name) as affected_tables
FROM silver.data_quality_audit
WHERE rule_status = 'fail'
  AND timestamp > NOW() - INTERVAL '7 days'
GROUP BY rule_name
ORDER BY failure_count DESC
LIMIT 10;
```

### Integrações
- **Grafana/PowerBI**: Template de dashboard pronto
- **Slack/Teams**: Notificação quando falhas > threshold
- **Email**: Relatório semanal executivo

### Esforço
- **Dev**: 10-15 dias
- **Infra**: 3 dias (storage, API)
- **QA**: 5 dias
- **Docs**: 2 dias

---

## **FASE 3: SaaS Premium - Observability as a Service** (Sprint 5-8)

### Features

#### 1. **Machine Learning para Detecção de Anomalias**
- Detectar quando padrão muda: "Duplicatas aumentaram 300% hoje"
- Prever problemas: "Taxa de falha vai subir 20% amanhã baseado em tendência"
- Classificação automática de severidade

#### 2. **Root Cause Analysis Automática**
```
❌ 1.547 linhas falharam em email_validation hoje

🔍 Análise Automática:
  - 98% dos erros vêm da fonte "import_csv_manual.csv"
  - Todos os erros ocorreram entre 14:00-15:00
  - Padrão comum: emails sem "@"
  
💡 Sugestão: Adicionar validação pré-upload no frontend
```

#### 3. **Data Quality Score por Dataset**
```
📊 Quality Score: 87/100

Breakdown:
  ✅ Completude: 95/100 (5% campos nulos)
  ⚠️  Conformidade: 78/100 (22% emails inválidos)
  ✅ Consistência: 92/100 (8% duplicatas)
  ✅ Precisão: 89/100 (11% outliers)
```

#### 4. **Políticas de Qualidade Customizáveis**
```yaml
# quality_policy.yaml
tables:
  customers:
    min_quality_score: 90
    critical_rules:
      - email_validation
      - duplicate_check
    actions:
      on_failure: "quarantine"  # isola registros ruins
      notify: ["data-team@company.com"]
      block_gold_layer: true     # não permite subir para Gold
```

#### 5. **Lineage de Qualidade**
- Rastrear problema até fonte original
- "Este erro veio do arquivo X, linha Y, processado em Z"

### Modelo de Negócio - Tiers

| Feature | Free (Bronze) | Pro (Silver) | Enterprise (Gold) |
|---------|---------------|--------------|-------------------|
| Contadores básicos (Pass/Fail/Skip) | ✅ | ✅ | ✅ |
| Coluna `DataQualityFailureReasons` | ✅ | ✅ | ✅ |
| Tabela de auditoria | ❌ | ✅ | ✅ |
| Dashboard Grafana | ❌ | ✅ | ✅ |
| API REST | ❌ | ✅ | ✅ |
| Alertas Slack/Teams | ❌ | ✅ (5 alertas/dia) | ✅ (ilimitado) |
| Machine Learning (anomalias) | ❌ | ❌ | ✅ |
| Root Cause Analysis | ❌ | ❌ | ✅ |
| Quality Score (0-100) | ❌ | ❌ | ✅ |
| Políticas customizáveis | ❌ | ❌ | ✅ |
| Lineage tracking | ❌ | ❌ | ✅ |
| SLA/Suporte | ❌ | Email 48h | Phone/Chat 4h |
| **Preço** | **$0** | **$299/mês** | **$1.999/mês** |

### Esforço
- **Dev**: 30-45 dias
- **ML/DS**: 15 dias
- **DevOps**: 10 dias
- **Product/UX**: 10 dias
- **QA**: 10 dias
- **Docs/Marketing**: 5 dias

---

## 🎯 Diferencial Competitivo

### Concorrentes
- **Great Expectations**: Complexo, requer Python, caro
- **Deequ** (AWS): Vendor lock-in, configuração manual
- **Soda**: Bom, mas caro ($$$)
- **Monte Carlo**: Enterprise-only, $$$$$

### Nossa Vantagem
| Atributo | Concorrentes | Nossa Solução |
|----------|-------------|---------------|
| Setup | Código Python complexo | ✅ Zero-code (automático) |
| Configuração | YAML/JSON manual | ✅ Auto-detecção inteligente |
| Integração | Ferramenta separada | ✅ Embedded no pipeline |
| Preço Inicial | $500-2000/mês | ✅ Free tier generoso ($0) |
| Time-to-Value | Semanas | ✅ Minutos |
| Target | Data Engineers | ✅ Analistas + Engineers |

### Posicionamento
**"Data Quality Observability que funciona sozinha"**
- Setup em 5 minutos
- Insights em tempo real
- Do free ao enterprise

---

## 📊 Métricas de Sucesso

### KPIs - Fase 1 (MVP)
- ✅ 100% tabelas Silver com `DataQualityFailureReasons`
- ✅ Documentação completa com queries exemplo
- ✅ Redução de 50% no tempo de debugging de problemas de qualidade

### KPIs - Fase 2 (Enterprise)
- ✅ Dashboard com <2s de load time
- ✅ 80% usuários usando views pré-criadas
- ✅ 10+ integrações Slack/Teams ativas
- ✅ Redução de 70% em "data quality incidents"

### KPIs - Fase 3 (SaaS)
- ✅ 100+ clientes pagantes
- ✅ NPS > 50
- ✅ 90% precisão em detecção de anomalias
- ✅ ROI comprovado (economizar X horas/mês)

---

## 🛠️ Stack Técnico Sugerido

### Fase 1
- Python (pandas) - já existe
- Delta Lake/Parquet - já existe
- SQL - já existe

### Fase 2
- PostgreSQL/MySQL - tabela auditoria
- Grafana - dashboards
- FastAPI - API REST
- Redis - cache queries
- RabbitMQ - alertas assíncronos

### Fase 3
- Python scikit-learn - ML anomalias
- Apache Airflow - orquestração avançada
- Elasticsearch - busca full-text em logs
- Kafka - streaming tempo real
- React - UI customizada

---

## 🚧 Riscos e Mitigações

| Risco | Probabilidade | Impacto | Mitigação |
|-------|---------------|---------|-----------|
| Performance (tabela auditoria muito grande) | Alta | Alto | Particionamento por data, retenção 90 dias, archive S3 |
| Custo storage | Média | Médio | Compressão, tier gratuito limitado |
| Complexidade UI/UX | Média | Alto | Contratar UX designer, testes usuário |
| Concorrência | Alta | Médio | Focar em "ease of use" + preço agressivo |
| Adoção interna | Baixa | Alto | Change management, treinamentos, champions |

---

## 📅 Roadmap Sugerido

### Q1 2026 (Jan-Mar)
- ✅ Validação problema (conversas usuários)
- ✅ Design técnico Fase 1
- 🔨 Desenvolvimento Fase 1
- 🧪 Beta testing Fase 1

### Q2 2026 (Abr-Jun)
- 🚀 Launch Fase 1 (free tier)
- 🔨 Desenvolvimento Fase 2
- 📣 Marketing inicial

### Q3 2026 (Jul-Set)
- 🚀 Launch Fase 2 (tier pago)
- 💰 Primeiros clientes pagantes
- 🔨 Início Fase 3 (ML)

### Q4 2026 (Out-Dez)
- 🚀 Launch Fase 3 (enterprise)
- 📈 Scale comercial
- 🏆 Casos de sucesso

---

## 💡 Próximos Passos

### Imediato (Esta Sprint)
1. [ ] Validar com 3-5 usuários se resolução é útil
2. [ ] Criar PoC Fase 1 (1-2 dias dev)
3. [ ] Decisão: seguir com feature ou não

### Curto Prazo (Próximo Mês)
1. [ ] Refinamento técnico Fase 1
2. [ ] Estimar custos infraestrutura
3. [ ] Criar user stories detalhadas
4. [ ] Priorizar no backlog

### Médio Prazo (3-6 Meses)
1. [ ] Contratar/alocar recursos (dev, UX, marketing)
2. [ ] Buildar Fase 1 + 2
3. [ ] Testar go-to-market Fase 2

---

## 📚 Referências

- [Great Expectations Docs](https://docs.greatexpectations.io/)
- [AWS Deequ](https://github.com/awslabs/deequ)
- [Soda Data Quality](https://www.soda.io/)
- [Monte Carlo Data Observability](https://www.montecarlodata.com/)
- [Data Quality Dimensions (Gartner)](https://www.gartner.com/en/documents/3883464)

---

## 👥 Stakeholders

- **Product Owner**: [Nome]
- **Tech Lead**: [Nome]
- **Data Engineering**: [Nome]
- **UX/UI**: [Nome]
- **Go-to-Market**: [Nome]

---

**Criado em**: 2026-01-18  
**Última Atualização**: 2026-01-18  
**Versão**: 1.0
