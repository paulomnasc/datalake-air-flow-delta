#!/usr/bin/env bash
# -------------------------------------------------------------
# Script de Reinicialização Rápida da Stack Airflow/Spark/CodeIgniter
# Mantém/recupera configurações de runtime (ex: Xdebug) aplicadas
# diretamente no container.
# -------------------------------------------------------------
set -euo pipefail

COMPOSE_FILE="docker-compose.yml"
SERVICE="codeigniter-app"

echo "== l-restart: preservando configurações do serviço '$SERVICE' =="

TMPDIR=$(mktemp -d /tmp/l-restart.XXXX)
BACKUP_MADE=0

echo "1) Detectando container em execução para '$SERVICE'..."
CID=$(docker-compose -f "$COMPOSE_FILE" ps -q "$SERVICE" || true)
if [ -n "$CID" ]; then
	echo " - container encontrado: $CID"
	echo " - procurando arquivos de configuração do Xdebug em /usr/local/etc/php/conf.d ..."
	mkdir -p "$TMPDIR/conf.d"
	for f in $(docker exec "$CID" sh -c "ls -1 /usr/local/etc/php/conf.d 2>/dev/null || true"); do
		if docker exec "$CID" sh -c "grep -Iq xdebug /usr/local/etc/php/conf.d/'$f' 2>/dev/null || true"; then
			echo "   - salvando /usr/local/etc/php/conf.d/$f"
			docker cp "$CID":/usr/local/etc/php/conf.d/"$f" "$TMPDIR/conf.d/$f" >/dev/null 2>&1 || true
			BACKUP_MADE=1
		fi
	done

	for p in "/etc/php" "/etc/php/7" "/etc/php/8"; do
		if docker exec "$CID" sh -c "test -d $p 2>/dev/null && echo yes || true" | grep -q yes; then
			for f in $(docker exec "$CID" sh -c "grep -ril --line-number xdebug $p 2>/dev/null || true"); do
				reldir=$(dirname "$f")
				mkdir -p "$TMPDIR$reldir"
				echo "   - salvando $f"
				docker cp "$CID":"$f" "$TMPDIR$reldir/" >/dev/null 2>&1 || true
				BACKUP_MADE=1
			done
		fi
	done

	if docker exec "$CID" sh -c "test -f /tmp/xdebug.log && echo yes || true" | grep -q yes; then
		echo "   - salvando /tmp/xdebug.log"
		docker cp "$CID":/tmp/xdebug.log "$TMPDIR/" >/dev/null 2>&1 || true
		BACKUP_MADE=1
	fi
else
	echo " - nenhum container em execução encontrado para '$SERVICE'. Pulando etapa de backup." 
fi

echo "2) Reiniciando o serviço: stop -> rm -> build -> up"
docker-compose -f "$COMPOSE_FILE" stop "$SERVICE" || true
docker-compose -f "$COMPOSE_FILE" rm -f "$SERVICE" || true
docker-compose -f "$COMPOSE_FILE" build --no-cache "$SERVICE"
docker-compose -f "$COMPOSE_FILE" up -d "$SERVICE"

if [ "$BACKUP_MADE" -eq 1 ]; then
	echo "3) Restaurando arquivos de configuração de $TMPDIR para o novo container..."
	NEWCID=$(docker-compose -f "$COMPOSE_FILE" ps -q "$SERVICE" || true)
	if [ -n "$NEWCID" ]; then
		find "$TMPDIR" -type f -print0 | while IFS= read -r -d '' file; do
			rel=${file#"$TMPDIR"}
			if [[ "$rel" == /conf.d/* ]]; then
				dest="/usr/local/etc/php/conf.d/${rel#/conf.d/}"
			else
				dest="$rel"
			fi
			echo "   - copiando $file -> $dest"
			docker cp "$file" "$NEWCID":"$dest" >/dev/null 2>&1 || true
		done

		echo "   - tentando recarregar Apache dentro do container..."
		docker exec "$NEWCID" sh -c "apachectl -k graceful 2>/dev/null || { service apache2 reload 2>/dev/null || true; }" || true
	else
		echo "   - não foi possível detectar novo container; pulando restore automático." 
	fi
else
	echo "3) Nenhum backup encontrado — nada a restaurar." 
fi

echo "4) Verificação rápida do Xdebug no container (se estiver up):"
docker-compose -f "$COMPOSE_FILE" exec -T "$SERVICE" php -i | egrep -i "xdebug|client_host|client_port" || true

echo "Backup temporário mantido em: $TMPDIR"
echo "l-restart concluído."
