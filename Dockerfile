FROM php:8.3-cli

RUN docker-php-ext-install pdo_mysql

WORKDIR /app
COPY . /app

RUN mkdir -p /app/logs /app/uploads \
    && chown -R www-data:www-data /app/logs /app/uploads

USER www-data

EXPOSE 10000

CMD ["sh", "-c", "php -S 0.0.0.0:${PORT:-10000} index.php"]
