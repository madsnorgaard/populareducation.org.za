FROM drupal:11-php8.4-apache

RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        git nano default-mysql-client unzip; \
    rm -rf /var/lib/apt/lists/*

RUN pecl install redis \
    && docker-php-ext-enable redis

COPY docker/php-drupal.ini /usr/local/etc/php/conf.d/99-drupal.ini

RUN mkdir -p /opt/drupal/web/sites/default/files \
    && mkdir -p /opt/drupal/private \
    && mkdir -p /opt/drupal/backups \
    && chown -R www-data:www-data /opt/drupal/web/sites/default/files /opt/drupal/private \
    && chmod -R 775 /opt/drupal/web/sites/default/files /opt/drupal/private

COPY composer.json /opt/drupal/
COPY composer.lock /opt/drupal/
COPY settings.php /opt/drupal/web/sites/default/settings.php
COPY config/ /opt/drupal/config/

WORKDIR /opt/drupal
RUN composer install --no-dev --no-interaction --optimize-autoloader

RUN chown -R www-data:www-data /opt/drupal /opt/drupal/backups \
    && chmod -R 775 /opt/drupal

CMD ["apache2-foreground"]
