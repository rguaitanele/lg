FROM php:8.1-apache

RUN apt update \
    && apt -y install bash ssh openssl libgmp-dev libgmp3-dev sshpass graphviz \
    && pear install Image_GraphViz-1.3.0 \
    && ln -s /usr/include/x86_64-linux-gnu/gmp.h /usr/include/gmp.h \
    && docker-php-ext-install -j$(nproc) gmp \
    && a2enmod remoteip \
    && apt purge -y \
    && rm -rf /var/lib/apt/lists/* \
    && mkdir -p /var/log/ \
    && touch /var/log/looking-glass.log \
    && chown www-data /var/log/looking-glass.log
