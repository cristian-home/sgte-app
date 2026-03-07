# =============================================================================
# Stage 1: Build frontend assets
# =============================================================================
FROM node:24-alpine AS node

WORKDIR /app

COPY package.json package-lock.json ./
RUN npm ci

COPY . .
RUN npm run build:ssr

# =============================================================================
# Stage 2: Install PHP dependencies
# =============================================================================
FROM composer:latest AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader

# =============================================================================
# Stage 3: Production image
# =============================================================================
FROM dunglas/frankenphp:latest-php8.4-bookworm AS production

# Install PHP extensions
RUN install-php-extensions \
    pcntl \
    pgsql \
    pdo_pgsql \
    redis \
    gd \
    intl \
    bcmath \
    zip \
    mbstring \
    xml \
    curl \
    igbinary \
    msgpack \
    imagick

# Install Node.js runtime (for Inertia SSR) and Supervisor
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        supervisor \
        curl \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

WORKDIR /app

# Copy application source code
COPY . .

# Copy vendor from composer stage
COPY --from=composer /app/vendor ./vendor

# Copy built frontend assets from node stage
COPY --from=node /app/public/build ./public/build
COPY --from=node /app/bootstrap/ssr ./bootstrap/ssr

# Copy production config files
COPY docker/production/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/production/start-container /usr/local/bin/start-container
RUN chmod +x /usr/local/bin/start-container

# Create required directories
RUN mkdir -p \
    storage/logs \
    storage/framework/cache \
    storage/framework/sessions \
    storage/framework/views \
    bootstrap/cache

# Cache config, routes, views, and events
RUN php artisan config:cache \
    && php artisan route:cache \
    && php artisan view:cache \
    && php artisan event:cache

# Create non-root user
RUN groupadd --force -g 1000 app \
    && useradd -ms /bin/bash --no-user-group -g 1000 -u 1000 app \
    && chown -R app:app /app

EXPOSE 8000
EXPOSE 8080

ENTRYPOINT ["start-container"]
