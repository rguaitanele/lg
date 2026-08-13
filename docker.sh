#!/usr/bin/env bash
set -eu

docker run -d \
    -p 8082:80 \
    --name=lg \
    --restart=always \
    -v "$PWD/htdocs/lg_config.php:/var/www/html/lg_config.php:ro" \
    -v "$PWD/keys:/opt/lg/keys:ro" \
    rguaitanele/lg_hsdn:latest
