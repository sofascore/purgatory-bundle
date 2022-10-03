ARG PHP_VERSION=8.1.11

FROM php:${PHP_VERSION}-cli-alpine3.16

ARG COMPOSER_VERSION=2.1.3
ARG PHP_CS_FIXER_VERSION=3.0.0
ARG XDEBUG_VERSION=3.1.5

ENV XDEBUG_CONFIG="client_host=host.docker.internal"

# This is where we're going to store all of our non-project specific binaries
RUN mkdir -p /app/bin
ENV PATH /app/bin:$PATH

# Install needed core and PECL extensions
RUN apk add --update --no-cache --virtual .build-deps \
        ${PHPIZE_DEPS} \
        libxml2-dev \
        libzip-dev \
        zlib-dev \
    && docker-php-ext-install -j $(getconf _NPROCESSORS_ONLN) \
        xml \
        zip \
    && pecl install \
        xdebug-${XDEBUG_VERSION} \
    && docker-php-ext-enable \
        xdebug \
    && apk del --purge .build-deps

RUN mv ${PHP_INI_DIR}/php.ini-development ${PHP_INI_DIR}/php.ini

COPY xdebug.ini ${PHP_INI_DIR}/conf.d/xdebug.ini

# Utilities needed to run this image
RUN apk add --update --no-cache \
        git \
        libzip \
        unzip \
        su-exec \
        shadow

# Composer
RUN curl --show-error https://getcomposer.org/installer | php -- \
        --install-dir=/app/bin \
        --filename=composer \
        --version=${COMPOSER_VERSION}

# PHP CS Fixer
RUN curl --location --output /app/bin/php-cs-fixer --show-error \
        https://github.com/FriendsOfPHP/PHP-CS-Fixer/releases/download/v${PHP_CS_FIXER_VERSION}/php-cs-fixer.phar \
    && chmod +x /app/bin/php-cs-fixer

# Create the user that's going to run our application
RUN useradd -ms /bin/sh app

COPY entrypoint.sh /usr/local/bin/docker-entrypoint

VOLUME /app/src
WORKDIR /app/src

ENTRYPOINT ["docker-entrypoint"]
CMD ["php", "-a"]
