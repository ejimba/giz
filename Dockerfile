FROM thecodingmachine/php:8.3-v4-apache-node20
ENV APACHE_DOCUMENT_ROOT=public/ \
    APACHE_RUN_GROUP=www-data \
    APACHE_RUN_USER=www-data \
    PHP_EXTENSIONS="gd intl pdo_pgsql pgsql" \
    TEMPLATE_PHP_INI=production
RUN sudo apt-get update && sudo apt-get install -y lsb-release wget gnupg
RUN sudo sh -c 'echo "deb http://apt.postgresql.org/pub/repos/apt $(lsb_release -cs)-pgdg main" > /etc/apt/sources.list.d/pgdg.list' && \
    wget --quiet -O - https://www.postgresql.org/media/keys/ACCC4CF8.asc | sudo apt-key add -
RUN sudo apt-get update && sudo apt-get install -y postgresql-client-16 && sudo apt-get clean && sudo rm -rf /var/lib/apt/lists/*
COPY --chown=docker package.json package-lock.json .
RUN npm install
COPY composer.json composer.lock .
RUN composer install --no-scripts --no-interaction
COPY . .
RUN sudo npm run build && sudo chmod -R 777 bootstrap/cache storage && sudo chown -R www-data:www-data /var/www/html
