# syntax=docker/dockerfile:1
#
# Production image for masterpiecemovie.
#
# Render builds this automatically on every `git push`. It reproduces the WAMP
# setup: Apache serving www/ as the web root, unknown paths routed to
# www/index.php, and PHP 8.3 (the version used locally).

FROM php:8.3-apache

# gd        - avatar and image resizing
# pdo_mysql - database access
# opcache   - caches compiled PHP; matters with this many includes per request
RUN apt-get update \
 && apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev ca-certificates \
 && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
 && docker-php-ext-install -j"$(nproc)" gd pdo_mysql opcache \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# Render tells the container which port to listen on through $PORT.
# 8080 is only the default for running the image yourself.
ENV PORT=8080
RUN sed -i 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf

COPY docker/apache-vhost.conf /etc/apache2/sites-available/000-default.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zz-app.ini

# Aiven's CA certificate. It's public, not a secret. Bundling it means the
# database connection doesn't depend on a Render Secret File being present,
# readable by Apache's www-data user, and pasted in full.
COPY docker/db-ca.pem /etc/ssl/certs/db-ca.pem
ENV DB_SSL_CA_BUNDLED=/etc/ssl/certs/db-ca.pem

WORKDIR /var/www/html
COPY . .

# .env/config.php is git-ignored because it holds secrets, so it never reaches
# the build. Use the committed version, which reads every value from the
# environment variables set in the Render dashboard.
RUN cp .env/config.env.php .env/config.php \
 && mkdir -p v1/cache/tmdb www/uploads/avatars \
 && chown -R www-data:www-data v1/cache www/uploads \
 && chmod 644 /etc/ssl/certs/db-ca.pem
