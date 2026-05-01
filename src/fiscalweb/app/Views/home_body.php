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
    <h1 class="hero-title">Plataforma Minha Fiscalização</h1>
    <p class="hero-subtitle">Transforme dados brutos em insights de negócio através de camadas Bronze, Silver e Gold</p>
</div>



<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
