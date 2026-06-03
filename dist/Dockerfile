# syntax=docker/dockerfile:1

# =============================================================================
# Taskline – self-hosted image (PHP-FPM + baked frontend build + vendor)
# Multi-stage: composer deps -> frontend build (node) -> final php-fpm runtime.
#
# IMPORTANT: the build context is the PROJECT ROOT, not dist/. Build with:
#   docker buildx build --platform linux/amd64,linux/arm64 -f dist/Dockerfile <project-root>
# (dist/build-and-push.sh does this for you. COPY paths below are relative to
# the project root, hence `dist/php/...`, `dist/entrypoint.sh`.)
# =============================================================================

# ---- Stage 1: PHP dependencies (no dev, optimized autoloader) ----------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
        --no-dev \
        --no-scripts \
        --prefer-dist \
        --no-interaction \
        --no-progress \
        --ignore-platform-reqs \
        --optimize-autoloader

# ---- Stage 2: frontend assets (Vite build) -----------------------------------
FROM node:20-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
# Build inputs
COPY vite.config.js postcss.config.js tailwind.config.js jsconfig.json ./
COPY resources ./resources
COPY public ./public
# app.js imports ZiggyVue from vendor/tightenco/ziggy — provide it from stage 1.
COPY --from=vendor /app/vendor/tightenco/ziggy ./vendor/tightenco/ziggy
# NOTE: no VITE_REVERB_* are passed here on purpose — the websocket host/port/
# scheme are resolved at runtime from the browser + server config, so a single
# image works on any domain/IP without rebuilding.
RUN npm run build

# ---- Stage 3: final runtime --------------------------------------------------
FROM php:8.5-fpm AS app

# PHP extensions (mirrors the server's existing image).
# ADD + RUN chmod (instead of `ADD --chmod`) so this builds with the classic
# builder too, not only BuildKit/buildx.
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/install-php-extensions
RUN chmod +x /usr/local/bin/install-php-extensions \
    && install-php-extensions \
        pdo_pgsql \
        mbstring \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
        exif \
        pcntl

# Production php.ini overrides (opcache, upload limits, ...)
COPY dist/php/taskline.ini /usr/local/etc/php/conf.d/zz-taskline.ini

WORKDIR /var/www/html

# Application source (vendor / node_modules / build excluded via .dockerignore)
COPY . .
# Real, optimized vendor + freshly built assets from the earlier stages
COPY --from=vendor   /app/vendor       ./vendor
COPY --from=frontend /app/public/build ./public/build

# Keep a pristine copy of the built public/ outside the volume mount point so the
# entrypoint can sync it into the shared nginx volume on every start (handles
# upgrades, since named volumes are only seeded from the image when empty).
RUN cp -a /var/www/html/public /var/www/html/.public-template \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY dist/entrypoint.sh /usr/local/bin/entrypoint
RUN chmod +x /usr/local/bin/entrypoint

EXPOSE 9000
ENTRYPOINT ["entrypoint"]
CMD ["php-fpm"]
