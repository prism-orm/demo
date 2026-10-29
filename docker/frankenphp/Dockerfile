# La démo servie par FrankenPHP en mode worker
FROM dunglas/frankenphp:1-php8.4

RUN install-php-extensions pdo_mysql pdo_pgsql intl opcache zip
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# un worker = un processus PHP qui sert des milliers de requêtes : la mémoire est mesurée
RUN { echo 'opcache.enable=1'; echo 'opcache.enable_cli=1'; echo 'memory_limit=256M'; } > /usr/local/etc/php/conf.d/prism-demo.ini
