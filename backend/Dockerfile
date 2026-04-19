# Solar Proposal MVP backend — Laravel 11 on PHP 8.4.
#
# The MVP is file-backed (no DB, no queue), so a single PHP container running
# `php artisan serve` is enough. GD is required by the rendering pipeline that
# writes roof_base.png / roof_overlay.png. PHP 8.4 is required because the
# committed composer.lock resolves several symfony/* packages at v8.0.x which
# hard-require PHP >= 8.4.

FROM php:8.4-cli-alpine

# imagemagick-tiff + tiff supply the libtiff delegate ImageMagick needs to
# decode the GeoTIFFs the Solar API's Data Layers endpoint returns; without
# them `magick` fails with "no decode delegate for this image format" and the
# rgb/mask/flux PNG conversions never run.
RUN apk add --no-cache \
        git \
        unzip \
        imagemagick \
        imagemagick-tiff \
        imagemagick-jpeg \
        tiff \
        libzip-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd zip pdo pdo_mysql mbstring bcmath

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Raise memory + execution limits so Gemini base64 payloads and GD
# compositing don't trip PHP's defaults during a live render.
COPY docker/php-overrides.ini /usr/local/etc/php/conf.d/zz-solar-overrides.ini

WORKDIR /app

# Install dependencies first so the vendor layer is cached across source edits.
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist \
    && composer clear-cache

COPY . .

# Finish the composer post-install hooks now that the full app tree is present,
# and make sure the storage + bootstrap cache dirs are writable at runtime.
RUN composer dump-autoload --optimize --no-dev \
    && mkdir -p storage/app/projects storage/app/config storage/framework/cache/data storage/framework/sessions storage/framework/views bootstrap/cache \
    && chmod -R 0777 storage bootstrap/cache

EXPOSE 8000

# docker-entrypoint.sh copies .env.example if missing and generates an app key
# before starting artisan serve, so the container is self-contained.
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
