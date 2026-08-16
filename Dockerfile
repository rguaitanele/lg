FROM php:7.4-apache

ARG BUILD_DATE=unknown
ARG VCS_REF=unknown
ARG IMAGE_VERSION=dev

ENV HOME=/var/www

LABEL org.opencontainers.image.title="HSDN PHP Looking Glass" \
      org.opencontainers.image.description="BGP Looking Glass com suporte a SSH, IPv4 e IPv6" \
      org.opencontainers.image.source="https://gitlab.blz.com.br/Infra/lg" \
      org.opencontainers.image.url="https://hub.docker.com/r/rguaitanele/lg_hsdn" \
      org.opencontainers.image.created="${BUILD_DATE}" \
      org.opencontainers.image.revision="${VCS_REF}" \
      org.opencontainers.image.version="${IMAGE_VERSION}"

RUN apt-get update \
    && apt-get install -y --no-install-recommends bash ssh openssl libgmp-dev libgmp3-dev sshpass graphviz \
    && ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h \
    && docker-php-ext-install -j$(nproc) gmp \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/ /var/www/.ssh /var/lib/lg \
    && touch /var/log/looking-glass.log \
    && touch /var/www/.ssh/known_hosts \
    && chown www-data:www-data /var/log/looking-glass.log \
    && chown -R www-data:www-data /var/www/.ssh \
    && chown -R www-data:www-data /var/lib/lg \
    && chmod 700 /var/www/.ssh \
    && chmod 600 /var/www/.ssh/known_hosts

# The PEAR channel is unreliable; pin the official library source by commit.
ADD https://raw.githubusercontent.com/pear/Image_GraphViz/7830ac2772ec2701cff073874981905c62a5722b/Image/GraphViz.php /usr/local/lib/php/Image/GraphViz.php
RUN chmod 644 /usr/local/lib/php/Image/GraphViz.php

COPY htdocs/ /var/www/html/

EXPOSE 80

HEALTHCHECK --interval=30s --timeout=5s --start-period=10s --retries=3 \
    CMD php -r 'exit(@file_get_contents("http://127.0.0.1/") === false ? 1 : 0);'
