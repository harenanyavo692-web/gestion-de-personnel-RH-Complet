# Build assets with Node
FROM node:20-alpine AS node
WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources resources
COPY public public

RUN npm ci
RUN npm run build

FROM php:8.2-apache

RUN apt-get update && apt-get install -y --no-install-recommends \
    libzip-dev \
    zlib1g-dev \
    libsqlite3-dev \
    unzip \
    git \
    curl \
  && docker-php-ext-install pdo pdo_sqlite zip \
  && a2enmod rewrite \
  && rm -rf /var/lib/apt/lists/*

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
ENV APP_ENV=production
ENV NODE_ENV=production

WORKDIR /var/www/html

RUN printf '%s\n' "<VirtualHost *:80>" \
    "    ServerName localhost" \
    "    DocumentRoot ${APACHE_DOCUMENT_ROOT}" \
    "    DirectoryIndex index.php index.html" \
    "" \
    "    <Directory ${APACHE_DOCUMENT_ROOT}>" \
    "        AllowOverride All" \
    "        Require all granted" \
    "        <IfModule mod_rewrite.c>" \
    "            RewriteEngine On" \
    "            RewriteCond %{REQUEST_FILENAME} !-f" \
    "            RewriteCond %{REQUEST_FILENAME} !-d" \
    "            RewriteRule ^ index.php [QSA,L]" \
    "        </IfModule>" \
    "    </Directory>" \
    "" \
    "    ErrorLog ${APACHE_LOG_DIR}/error.log" \
    "    CustomLog ${APACHE_LOG_DIR}/access.log combined" \
    "</VirtualHost>" > /etc/apache2/sites-available/000-default.conf && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    a2ensite 000-default

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

COPY . .
COPY --chown=www-data:www-data --from=node /app/public/build public/build

RUN mkdir -p storage/framework/cache/data storage/framework/views storage/logs database && \
    chown -R www-data:www-data /var/www/html

RUN if [ ! -f .env ]; then cp .env.example .env 2>/dev/null || echo "APP_KEY=base64:$(openssl rand -base64 32)" > .env; fi

RUN touch database/database.sqlite && chmod 666 database/database.sqlite

RUN if [ -f artisan ]; then php artisan key:generate --ansi --force; fi

# Ajout de l'entrypoint : config:cache et migrate se font maintenant au démarrage du conteneur
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

EXPOSE 80
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
