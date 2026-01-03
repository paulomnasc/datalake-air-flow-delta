#!/bin/bash
# Servidor HTTP simples para testar a documentação

PORT=8080
DOCS_DIR="/root/datalake-air-flow-delta/docs"

echo ""
echo "======================================================================="
echo "📚 SERVIDOR DE DOCUMENTAÇÃO HTML"
echo "======================================================================="
echo ""
echo "📂 Pasta: $DOCS_DIR"
echo "🌐 Porta: $PORT"
echo ""
echo "🔗 Acesse: http://localhost:$PORT"
echo ""
echo "Pressione Ctrl+C para parar o servidor"
echo "======================================================================="
echo ""

cd "$DOCS_DIR" && python3 -m http.server $PORT
