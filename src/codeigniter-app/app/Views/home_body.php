<style>
    :root {
        --bronze: #CD7F32;
        --silver: #C0C0C0;
        --gold: #FFD700;
        --dark-bg: #1a1d29;
        --card-bg: #242938;
        --text-light: #e8eaed;
        --accent-blue: #4A90E2;
    }

    /* Botão vídeo explicativo - REMOVIDO
    .video-button-wrapper {
        position: relative;
        width: 100%;
        height: 0;
        margin-bottom: 20px;
    }

    .video-button {
        position: absolute;
        top: 10px;
        right: 20px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 12px 24px;
        background: linear-gradient(135deg, #e74c3c, #c0392b);
        color: white !important;
        text-decoration: none !important;
        border-radius: 50px;
        font-weight: 600;
        font-size: 1rem;
        transition: all 0.3s ease;
        box-shadow: 0 5px 20px rgba(231, 76, 60, 0.4);
        border: none;
        cursor: pointer;
        z-index: 100;
    }

    .video-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 30px rgba(231, 76, 60, 0.6);
        background: linear-gradient(135deg, #c0392b, #e74c3c);
        color: white !important;
    }

    .video-button i {
        font-size: 1.2rem;
    }
    */

    body {
        background: linear-gradient(135deg, #1a1d29 0%, #2d3142 100%);
        color: var(--text-light);
    }

    .hero-section {
        background: linear-gradient(135deg, rgba(74, 144, 226, 0.1) 0%, rgba(26, 29, 41, 0.9) 100%);
        padding: 60px 20px;
        text-align: center;
        border-radius: 15px;
        margin: 20px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .hero-title {
        font-size: 3rem;
        font-weight: 700;
        background: linear-gradient(90deg, var(--bronze), var(--silver), var(--gold));
        -webkit-background-clip: text;
        -webkit-text-fill-color: transparent;
        margin-bottom: 20px;
        text-shadow: 0 0 30px rgba(255, 215, 0, 0.3);
    }

    .hero-subtitle {
        font-size: 1.3rem;
        color: var(--text-light);
        margin-bottom: 30px;
        opacity: 0.9;
    }

    .medallion-flow {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 30px;
        margin: 40px auto;
        flex-wrap: wrap;
        max-width: 1200px;
    }

    .medallion-layer {
        flex: 1;
        min-width: 250px;
        padding: 30px;
        border-radius: 15px;
        text-align: center;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
    }

    .medallion-layer::before {
        content: '';
        position: absolute;
        top: -50%;
        left: -50%;
        width: 200%;
        height: 200%;
        background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
        opacity: 0;
        transition: opacity 0.3s ease;
    }

    .medallion-layer:hover::before {
        opacity: 1;
    }

    .medallion-layer:hover {
        transform: translateY(-10px);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.4);
    }

    .bronze-layer {
        background: linear-gradient(135deg, rgba(205, 127, 50, 0.2), rgba(205, 127, 50, 0.05));
        border: 2px solid var(--bronze);
    }

    .silver-layer {
        background: linear-gradient(135deg, rgba(192, 192, 192, 0.2), rgba(192, 192, 192, 0.05));
        border: 2px solid var(--silver);
    }

    .gold-layer {
        background: linear-gradient(135deg, rgba(255, 215, 0, 0.2), rgba(255, 215, 0, 0.05));
        border: 2px solid var(--gold);
    }

    .layer-icon {
        font-size: 3.5rem;
        margin-bottom: 15px;
        filter: drop-shadow(0 5px 15px rgba(0, 0, 0, 0.3));
    }

    .layer-title {
        font-size: 1.8rem;
        font-weight: 700;
        margin-bottom: 10px;
    }

    .layer-desc {
        font-size: 1rem;
        opacity: 0.85;
        line-height: 1.6;
    }

    .flow-arrow {
        font-size: 2.5rem;
        color: var(--accent-blue);
        animation: pulse 2s infinite;
    }

    @keyframes pulse {
        0%, 100% { opacity: 0.6; }
        50% { opacity: 1; }
    }

    .features-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin: 40px 20px;
    }

    .feature-card {
        background: var(--card-bg);
        padding: 30px;
        border-radius: 12px;
        border-left: 4px solid var(--accent-blue);
        transition: all 0.3s ease;
    }

    .feature-card:hover {
        transform: translateX(5px);
        box-shadow: 0 8px 25px rgba(74, 144, 226, 0.2);
    }

    .feature-icon {
        font-size: 2.5rem;
        color: var(--accent-blue);
        margin-bottom: 15px;
    }

    .feature-title {
        font-size: 1.4rem;
        font-weight: 600;
        margin-bottom: 10px;
        color: var(--text-light);
    }

    .feature-desc {
        color: rgba(232, 234, 237, 0.8);
        line-height: 1.6;
    }

    .tech-stack {
        background: var(--card-bg);
        border-radius: 12px;
        padding: 30px;
        margin: 40px 20px;
    }

    .tech-stack h3 {
        text-align: center;
        color: var(--gold);
        margin-bottom: 30px;
        font-size: 2rem;
    }

    .tech-list {
        display: flex;
        flex-wrap: wrap;
        justify-content: center;
        gap: 15px;
    }

    .tech-badge {
        background: linear-gradient(135deg, rgba(74, 144, 226, 0.2), rgba(74, 144, 226, 0.05));
        border: 1px solid var(--accent-blue);
        padding: 10px 20px;
        border-radius: 25px;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .tech-badge:hover {
        background: var(--accent-blue);
        transform: scale(1.05);
    }

    .table-responsive {
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        background: var(--card-bg);
        border-radius: 12px;
        padding: 20px;
        margin: 20px 0;
    }

    .table {
        color: var(--text-light);
    }

    .table thead {
        background: linear-gradient(135deg, var(--accent-blue), #3a7bc8);
        color: white;
    }

    .table-striped tbody tr:nth-of-type(odd) {
        background-color: rgba(255, 255, 255, 0.05);
    }

    .cta-section {
        text-align: center;
        padding: 60px 20px;
        margin: 40px 20px;
        background: linear-gradient(135deg, rgba(74, 144, 226, 0.15), rgba(255, 215, 0, 0.1));
        border-radius: 15px;
    }

    .cta-button {
        display: inline-block;
        padding: 15px 40px;
        background: linear-gradient(135deg, var(--accent-blue), #3a7bc8);
        color: white !important;
        text-decoration: none !important;
        border-radius: 50px;
        font-weight: 700;
        font-size: 1.2rem;
        transition: all 0.3s ease;
        box-shadow: 0 5px 20px rgba(74, 144, 226, 0.4);
    }

    .cta-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 30px rgba(74, 144, 226, 0.6);
        color: white !important;
    }

</style>

<!-- Botão Assistir Video Explicativo - REMOVIDO
<div class="video-button-wrapper">
    <button class="video-button" onclick="window.open('https://youtu.be/b2MESMEBHVk', '_blank')">
        <i class="fas fa-play-circle"></i>
        Assistir vídeo explicativo
    </button>
</div>
-->

<!-- Hero Section -->
<div class="hero-section">
    <h1 class="hero-title">🌊 Arquitetura Medalhão para Data Lake</h1>
    <p class="hero-subtitle">Transforme dados brutos em insights de negócio através de camadas Bronze, Silver e Gold</p>
</div>

<!-- Medallion Architecture Flow -->
<div class="medallion-flow">
    <div class="medallion-layer bronze-layer">
        <div class="layer-icon">🥉</div>
        <h2 class="layer-title">Bronze</h2>
        <p class="layer-desc">
            Ingestão de dados brutos de múltiplas fontes (MySQL, APIs, CSV). 
            Dados sem transformação, preservando o histórico completo.
        </p>
    </div>
    
    <div class="flow-arrow">→</div>
    
    <div class="medallion-layer silver-layer">
        <div class="layer-icon">🥈</div>
        <h2 class="layer-title">Silver</h2>
        <p class="layer-desc">
            Limpeza, validação e enriquecimento de dados. 
            Aplicação de regras de qualidade e transformações ETL.
        </p>
    </div>
    
    <div class="flow-arrow">→</div>
    
    <div class="medallion-layer gold-layer">
        <div class="layer-icon">🥇</div>
        <h2 class="layer-title">Gold</h2>
        <p class="layer-desc">
            Dados agregados e otimizados para análise. 
            Feature engineering para ML e dashboards de BI.
        </p>
    </div>
</div>

<!-- Features Grid -->
<div class="features-grid">
    <div class="feature-card">
        <div class="feature-icon">⚡</div>
        <h3 class="feature-title">Orquestração com Airflow</h3>
        <p class="feature-desc">
            DAGs automatizadas para pipelines de dados com scheduling, monitoramento e retry automático.
        </p>
    </div>
    
    <div class="feature-card">
        <div class="feature-icon">🔄</div>
        <h3 class="feature-title">Processamento Spark</h3>
        <p class="feature-desc">
            Processamento distribuído de grandes volumes de dados com Apache Spark e Delta Lake.
        </p>
    </div>
    
    <div class="feature-card">
        <div class="feature-icon">📊</div>
        <h3 class="feature-title">Integração BI</h3>
        <p class="feature-desc">
            Conecte Power BI, Tableau via Spark SQL Thrift Server para análises em tempo real.
        </p>
    </div>
    
    <div class="feature-card">
        <div class="feature-icon">🗄️</div>
        <h3 class="feature-title">Armazenamento MinIO</h3>
        <p class="feature-desc">
            Storage S3-compatible para Data Lake com alta disponibilidade e baixo custo.
        </p>
    </div>
    
    <div class="feature-card">
        <div class="feature-icon">🏷️</div>
        <h3 class="feature-title">Catálogo Apache Atlas</h3>
        <p class="feature-desc">
            Governança de dados com linhagem, metadados e classificação automática.
        </p>
    </div>
    
    <div class="feature-card">
        <div class="feature-icon">✅</div>
        <h3 class="feature-title">Qualidade de Dados</h3>
        <p class="feature-desc">
            Validações automáticas, detecção de anomalias e data quality checks em cada camada.
        </p>
    </div>
</div>

<!-- Tech Stack -->
<div class="tech-stack">
    <h3>🛠️ Stack Tecnológica</h3>
    <div class="tech-list">
        <span class="tech-badge">Apache Airflow</span>
        <span class="tech-badge">Apache Spark</span>
        <span class="tech-badge">Delta Lake</span>
        <span class="tech-badge">MinIO S3</span>
        <span class="tech-badge">Apache Atlas</span>
        <span class="tech-badge">PostgreSQL</span>
        <span class="tech-badge">MySQL</span>
        <span class="tech-badge">Python</span>
        <span class="tech-badge">PySpark</span>
        <span class="tech-badge">Docker</span>
    </div>
</div>

<!-- Pipeline Example Section 
<div style="margin: 40px 20px;">
    <h2 style="text-align: center; color: var(--gold); margin-bottom: 30px;">📋 Exemplo de Pipeline de Dados</h2>
    
    <div class="table-responsive">
        <h4 style="color: var(--bronze); margin-bottom: 15px;">Camada Bronze - Dados Brutos</h4>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Fonte</th>
                    <th>Tipo</th>
                    <th>Frequência</th>
                    <th>Formato</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>MySQL - Customers</td>
                    <td>Banco Relacional</td>
                    <td>Incremental (diário)</td>
                    <td>Parquet</td>
                </tr>
                <tr>
                    <td>API REST - Vendas</td>
                    <td>API Externa</td>
                    <td>Streaming (real-time)</td>
                    <td>JSON → Parquet</td>
                </tr>
                <tr>
                    <td>CSV - Produtos</td>
                    <td>Arquivo Batch</td>
                    <td>Semanal</td>
                    <td>CSV → Parquet</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="table-responsive">
        <h4 style="color: var(--silver); margin-bottom: 15px;">Camada Silver - Transformações</h4>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Transformação</th>
                    <th>Descrição</th>
                    <th>Validação</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Limpeza de Nulos</td>
                    <td>Remoção/preenchimento de valores ausentes</td>
                    <td>Data Quality Check</td>
                </tr>
                <tr>
                    <td>Padronização</td>
                    <td>Normalização de formatos (datas, strings)</td>
                    <td>Schema Validation</td>
                </tr>
                <tr>
                    <td>Deduplicação</td>
                    <td>Remoção de registros duplicados</td>
                    <td>Constraint Check</td>
                </tr>
                <tr>
                    <td>Enriquecimento</td>
                    <td>Joins com tabelas de referência</td>
                    <td>Referential Integrity</td>
                </tr>
            </tbody>
        </table>
    </div>
    
    <div class="table-responsive">
        <h4 style="color: var(--gold); margin-bottom: 15px;">Camada Gold - Analytics Ready</h4>
        <table class="table table-bordered table-striped">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Tipo</th>
                    <th>Uso</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>customer_lifetime_value</td>
                    <td>Agregação (SUM)</td>
                    <td>Segmentação de Clientes</td>
                </tr>
                <tr>
                    <td>recency_days</td>
                    <td>Temporal (DATEDIFF)</td>
                    <td>Modelo RFM</td>
                </tr>
                <tr>
                    <td>product_category_rank</td>
                    <td>Window Function</td>
                    <td>Análise de Performance</td>
                </tr>
                <tr>
                    <td>churn_probability</td>
                    <td>ML Feature</td>
                    <td>Predição de Churn</td>
                </tr>
            </tbody>
        </table>
    </div>
</div-->

<!-- CTA Section -->
<div class="cta-section">
    <h2 style="font-size: 2.2rem; margin-bottom: 20px;">Pronto para construir seu Data Lake?</h2>
    <p style="font-size: 1.2rem; margin-bottom: 30px; opacity: 0.9;">
        Configure suas DAGs, conecte suas fontes de dados e comece a transformar dados em valor
    </p>
    
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
