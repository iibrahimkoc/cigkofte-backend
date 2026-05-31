FROM php:8.2-apache

RUN docker-php-ext-install pdo pdo_mysql \
    && a2enmod rewrite \
    && sed -ri 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

WORKDIR /var/www/html

COPY . /var/www/html/
COPY docker/start-apache.sh /usr/local/bin/start-apache

RUN chmod +x /usr/local/bin/start-apache \
    && mkdir -p /var/www/html/logs /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/logs /var/www/html/uploads

EXPOSE 10000

CMD ["start-apache"]
