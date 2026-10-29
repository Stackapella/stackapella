# Stackapella's one image: Laravel (/server) and the PWA's files (/web), served from one origin
# (docs/architecture.md, "The system"). Built from the repository's root, because the PWA lives
# beside the server: App Platform's PHP buildpack only sees its source_dir, which would leave
# /web out. The web, scheduler, long worker and migrate components all run this image.

# Both base images are pinned by digest (their tags in the comments), so a build never changes
# under us; the weekly .github/workflows/outdated.yml says when a newer one is out, and the pin is
# bumped by hand. Without a buildpack, PHP and OS patches arrive only with those bumps.
# composer:2
FROM composer@sha256:b6dcd72ca98fa19d0e5b8476de179859704891fad06dd9e0b96833d4c430a5fc AS composer

# php:8.4-fpm-bookworm
FROM php@sha256:df5a5113e1179ddf4560cd15727eb3812f6457829756d2a96c6c11bb4c250089

COPY --from=composer /usr/bin/composer /usr/bin/composer
COPY Aptfile /tmp/Aptfile

RUN apt-get update \
    && grep -v '^\s*#' /tmp/Aptfile | xargs apt-get install -y --no-install-recommends nginx unzip libicu-dev libzip-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql intl gd bcmath pcntl zip opcache \
    && rm -rf /var/lib/apt/lists/* /tmp/Aptfile

COPY .do/php.ini /usr/local/etc/php/conf.d/stackapella.ini
COPY .do/php-fpm.conf /usr/local/etc/php-fpm.d/zz-stackapella.conf
COPY .do/nginx.conf /etc/nginx/nginx.conf
COPY .do/start-web.sh /usr/local/bin/start-web

WORKDIR /app/server

COPY server/composer.json server/composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --prefer-dist --no-autoloader

COPY server/ /app/server/
COPY web/ /app/web/
# The courses and songs, from their own repo at the commit content.lock pins, checked against its
# sha256 (docs/repos-plan.md); while it pins none they're in the tree above and this does nothing.
COPY .do/content.lock /app/.do/content.lock
RUN sh /app/server/tool/content.sh fetch /app
# The template format's schema: every official template is checked against it as it's loaded.
COPY contract/schemas/ /app/contract/schemas/

# A local build's leftovers (Dockerfile.dockerignore keeps them out; this is the backstop), and
# when this image was built, for the admin's version badge (/up's builtAt).
RUN rm -rf .env bootstrap/cache/*.php tests storage/logs/* \
    && composer dump-autoload --optimize --no-dev \
    && rm -rf /app/web/spike_audio /app/web/tests /app/web/tool /app/web/bin \
    && mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod +x /usr/local/bin/start-web \
    && date -u +%Y-%m-%dT%H:%M:%SZ > /app/server/BUILT_AT

ENV WEB_PATH=/app/web

EXPOSE 8080

CMD ["start-web"]
