FROM php:7.3-fpm-alpine

ARG BUILD_ENVIRONMENT="development"

COPY --from=mlocati/php-extension-installer:1.1.3 /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions grpc
RUN install-php-extensions protobuf
RUN install-php-extensions xdebug

RUN apk --no-cache add pcre-dev ${PHPIZE_DEPS} && \
    docker-php-ext-enable grpc && \
    docker-php-ext-enable protobuf && \
    docker-php-ext-install sysvshm && \
    if [ "${BUILD_ENVIRONMENT}" == "development" ]; then \
      ln -sf /usr/local/etc/php/php.ini-development /usr/local/etc/php/php.ini && \
      echo "display_errors = 0" >> /usr/local/etc/php/php.ini && \
      docker-php-ext-enable xdebug ; \
    fi && \
    apk del pcre-dev ${PHPIZE_DEPS}

COPY src ./

