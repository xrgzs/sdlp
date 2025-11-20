FROM dunglas/frankenphp

RUN install-php-extensions apcu

ENV SERVER_NAME=:80

# Enable PHP production settings
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" && \
    echo "expose_php = Off" >> "$PHP_INI_DIR/conf.d/0_security.ini" && \
    echo "disable_functions = exec,system,passthru,shell_exec,popen,proc_open,pcntl_exec,eval,assert" >> "$PHP_INI_DIR/conf.d/0_security.ini" && \
    echo "allow_url_fopen = Off" >> "$PHP_INI_DIR/conf.d/0_security.ini" && \
    echo "cgi.fix_pathinfo = 0" >> "$PHP_INI_DIR/conf.d/0_security.ini" && \
    rm -rf /app/public/index.php

COPY . /app/public
