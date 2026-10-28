# HOTELGO – hardened Dockerfile for Render (replace the Dockerfile that came with LavaLust)
ARG PHP_VERSION=8.5
FROM php:${PHP_VERSION}-apache

# Database driver (MySQL / Aiven)
RUN docker-php-ext-install pdo pdo_mysql

# Apache: pretty URLs + allow .htaccess + hide the server version
RUN a2enmod rewrite headers \
 && sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf \
 && printf 'ServerTokens Prod\nServerSignature Off\nTraceEnable Off\n' > /etc/apache2/conf-available/hotelgo-security.conf \
 && a2enconf hotelgo-security

# PHP safety settings: never show errors to visitors, safer session cookies
RUN { \
  echo 'expose_php=Off'; \
  echo 'display_errors=Off'; \
  echo 'log_errors=On'; \
  echo 'allow_url_include=Off'; \
  echo 'session.use_strict_mode=1'; \
  echo 'session.use_only_cookies=1'; \
  echo 'session.cookie_httponly=1'; \
  echo 'session.cookie_samesite=Lax'; \
  echo 'session.cookie_secure=1'; \
} > /usr/local/etc/php/conf.d/hotelgo-hardening.ini

# App files
COPY . /var/www/html/

# Composer: builds vendor/autoload.php (the project's own classes in app/support are loaded through it)
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader --no-interaction --no-progress

RUN chown -R www-data:www-data /var/www/html \
 && chmod -R 755 /var/www/html \
 && chmod -R 775 /var/www/html/runtime

# Only the public/ folder is reachable from the internet
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot ${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/sites-available/000-default.conf \
 && sed -i 's|<Directory /var/www/html>|<Directory ${APACHE_DOCUMENT_ROOT}>|g' /etc/apache2/apache2.conf

EXPOSE 80
