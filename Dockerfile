FROM dunglas/frankenphp

RUN install-php-extensions apcu

ENV SERVER_NAME=:80

# Enable PHP production settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# COPY . /app/public