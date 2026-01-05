<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro na Confirmação de Email - MyFlow</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 500px;
            width: 100%;
            padding: 40px;
            text-align: center;
        }
        .icon {
            font-size: 80px;
            margin-bottom: 20px;
        }
        h1 {
            color: #333;
            font-size: 28px;
            margin-bottom: 15px;
        }
        .message {
            color: #666;
            font-size: 16px;
            line-height: 1.6;
            margin-bottom: 30px;
        }
        .error-detail {
            background: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 30px;
            color: #856404;
            font-size: 14px;
        }
        .btn-group {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        .btn {
            display: inline-block;
            padding: 15px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s ease;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        .btn-secondary {
            background: #f8f9fa;
            color: #333;
            border: 2px solid #e9ecef;
        }
        .btn-secondary:hover {
            background: #e9ecef;
        }
        .help-text {
            margin-top: 30px;
            font-size: 13px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">⚠️</div>
        <h1>Ops! Algo deu errado</h1>
        <p class="message">Não foi possível confirmar seu email.</p>
        
        <div class="error-detail">
            <strong>Motivo:</strong> <?= htmlspecialchars($mensagem ?? 'Token inválido ou expirado.', ENT_QUOTES, 'UTF-8') ?>
        </div>

        <div class="btn-group">
            <a href="<?= base_url('loginUsuario') ?>" class="btn btn-primary">
                🔑 Fazer Login
            </a>
            <a href="<?= base_url('sigInUsuario') ?>" class="btn btn-secondary">
                📝 Criar Nova Conta
            </a>
        </div>

        <p class="help-text">
            Precisa de ajuda? Entre em contato: <a href="mailto:suporte@smarttables.x10.mx" style="color: #667eea;">suporte@smarttables.x10.mx</a>
        </p>
    </div>
</body>
</html>
