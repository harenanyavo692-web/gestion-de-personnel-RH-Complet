# Build assets with Node
FROM node:20-alpine AS node
WORKDIR /app

COPY package.json package-lock.json vite.config.js .
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

WORKDIR /var/www/html

COPY --from=node /app/public/build public/build
COPY . .

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist
RUN cp .env.example .env
RUN php artisan key:generate --ansi
RUN php artisan config:cache

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80
CMD ["apache2-foreground"]
