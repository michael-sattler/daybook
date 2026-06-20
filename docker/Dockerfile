# Local dev only - bundles Apache+PHP and MariaDB (MySQL-compatible) into a
# single container so you can run the whole app with one `docker run`.
# NOT used for production (GreenGeeks deployment has its own MySQL).
FROM php:8.2-apache

RUN apt-get update \
    && apt-get install -y --no-install-recommends mariadb-server supervisor \
    && docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && rm -rf /var/lib/apt/lists/*

COPY docker/apache-override.conf /etc/apache2/conf-available/override.conf
RUN a2enconf override

COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

ENV MYSQL_DATABASE=daybook
ENV MYSQL_USER=daybook
ENV MYSQL_PASSWORD=daybook

EXPOSE 80 3306
VOLUME /var/lib/mysql

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
