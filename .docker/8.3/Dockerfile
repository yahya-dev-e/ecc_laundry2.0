FROM node:22-bookworm-slim AS assets
WORKDIR /src
COPY package*.json ./
RUN npm ci --ignore-scripts
COPY resources resources
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php:8.3-apache-bookworm
RUN apt-get update && apt-get install -y --no-install-recommends libicu-dev libonig-dev libzip-dev libsqlite3-dev unzip \
    && docker-php-ext-install pdo_mysql pdo_sqlite mbstring intl zip opcache \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
COPY . .
RUN composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader \
    && sed -ri 's!/var/www/html!/var/www/html/public!g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /var/www/html/public>\nAllowOverride All\nRequire all granted\n</Directory>\n' > /etc/apache2/conf-available/laravel.conf \
    && a2enconf laravel && chown -R www-data:www-data storage bootstrap/cache
COPY --from=assets /src/public/build public/build
EXPOSE 80
CMD ["apache2-foreground"]
