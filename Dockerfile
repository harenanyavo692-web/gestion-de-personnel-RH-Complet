# Build assets with Node
FROM node:20-alpine AS node
WORKDIR /app

COPY package.json package-lock.json vite.config.js ./
COPY resources resources
COPY public public

RUN npm ci
RUN npm run build

# PHP application server
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

ENV APACHE_DOCUMENT_ROOT /var/www/html/public

WORKDIR /var/www/html

# Create a strict Apache VirtualHost for Laravel and set ServerName/DirectoryIndex
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

# Install Composer first and install PHP dependencies using cached layers
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files first to leverage Docker cache for vendor installation
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist || true

# Copy built frontend assets from node stage, then the rest of the app
COPY --from=node /app/public/build public/build
COPY . .

# Ensure Laravel cache/view directories and database exist in the container
RUN mkdir -p storage/framework/cache/data storage/framework/views storage/logs database && \
    chown -R www-data:www-data storage bootstrap/cache database

# If no .env exists but .env.example is present, copy it (safe fallback)
RUN if [ ! -f .env ] && [ -f .env.example ]; then cp .env.example .env; fi

# Create SQLite database file if it doesn't exist
RUN touch database/database.sqlite && chmod 666 database/database.sqlite

# Run Laravel optimisation commands only if artisan exists
RUN if [ -f artisan ]; then php artisan key:generate --ansi || true; fi
RUN if [ -f artisan ]; then php artisan config:cache || true; fi
RUN if [ -f artisan ]; then php artisan migrate --force || true; fi

# Fix permissions for all directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache /var/www/html/database || true

EXPOSE 80
CMD ["apache2-foreground"]
