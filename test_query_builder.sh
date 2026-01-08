#!/bin/bash

# Usar a URL da webapp
BASE_URL="http://localhost:29088"

# Fazer login
echo "🔐 Fazendo login como admin..."
COOKIE_JAR="/tmp/cookies.txt"
rm -f "$COOKIE_JAR"

# Tentar login
LOGIN_RESPONSE=$(curl -s -c "$COOKIE_JAR" -d "email=admin@example.com&password=admin&submit=Entrar" \
  "$BASE_URL/loginUsuario" -L)

if grep -q "query-builder\|Query\|arquivo" <<< "$LOGIN_RESPONSE"; then
  echo "✅ Login bem-sucedido"
else
  echo "❌ Login falhou"
  echo "$LOGIN_RESPONSE" | head -20
fi

# Tentar acessar QueryBuilder com cookie
echo -e "\n🔍 Acessando /query-builder..."
curl -s -b "$COOKIE_JAR" "$BASE_URL/query-builder" | grep -o "Album\|Customer\|Employee" | head -5

