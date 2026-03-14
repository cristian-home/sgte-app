# =============================================================================
# Stage 1: Install PHP dependencies
# =============================================================================
FROM composer:latest AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --optimize-autoloader --ignore-platform-reqs

# =============================================================================
# Stage 2: Base image with PHP extensions and Node.js
# =============================================================================
FROM dunglas/frankenphp:1-php8.5-trixie AS base

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
    imagick \
    exif

# Install Node.js runtime and Supervisor
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        supervisor \
        curl \
    && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
    && apt-get install -y --no-install-recommends nodejs \
    && apt-get -y autoremove \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# =============================================================================
# Stage 3: Build frontend assets (requires PHP for Wayfinder route generation)
# =============================================================================
FROM base AS build

WORKDIR /app

# Copy application source code and PHP dependencies
COPY . .
COPY --from=composer /app/vendor ./vendor

# Install npm dependencies and build frontend assets
# Wayfinder Vite plugin runs `php artisan wayfinder:generate` during build
RUN npm ci && npm run build:ssr

# =============================================================================
# Stage 4: Production image
# =============================================================================
FROM base AS production

WORKDIR /app

# Copy application source code
COPY . .

# Copy vendor from composer stage
COPY --from=composer /app/vendor ./vendor

# Copy built frontend assets from build stage
COPY --from=build /app/public/build ./public/build
COPY --from=build /app/bootstrap/ssr ./bootstrap/ssr

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

# Cache views and events (these don't depend on runtime env vars)
RUN php artisan view:cache \
    && php artisan event:cache

# Create non-root user
RUN groupadd --force -g 1000 app \
    && useradd -ms /bin/bash --no-user-group -g 1000 -u 1000 app \
    && chown -R app:app /app

EXPOSE 8000
EXPOSE 8080

ENTRYPOINT ["start-container"]
