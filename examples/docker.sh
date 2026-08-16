#!/usr/bin/env bash
set -eu

IMAGE="${IMAGE:-rguaitanele/lg_hsdn:dev}"
CONTAINER_NAME="${CONTAINER_NAME:-lg}"
CONFIG="${CONFIG:-$PWD/config/lg_config.php}"
KEYS="${KEYS:-$PWD/keys}"

if [ ! -f "$CONFIG" ]; then
    echo "ERRO: configuração não encontrada em $CONFIG" >&2
    exit 1
fi

if [ ! -d "$KEYS" ]; then
    echo "ERRO: diretório de chaves não encontrado em $KEYS" >&2
    exit 1
fi

docker pull "$IMAGE"

if docker container inspect "$CONTAINER_NAME" >/dev/null 2>&1; then
    docker rm -f "$CONTAINER_NAME"
fi

docker run -d \
    --name "$CONTAINER_NAME" \
    --restart always \
    -p 8082:80 \
    -v "$CONFIG:/var/www/html/lg_config.php:ro" \
    -v "$KEYS:/opt/lg/keys:ro" \
    "$IMAGE"
