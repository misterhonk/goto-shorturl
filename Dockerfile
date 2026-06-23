# Lokale Testumgebung für GOTO – PHP 8.2 + Apache (wie typisches Shared-Hosting)
FROM php:8.2-apache

# mbstring (für saubere Umlaut-Behandlung) + Apache-Module für .htaccess
RUN apt-get update \
 && apt-get install -y --no-install-recommends libonig-dev \
 && docker-php-ext-install mbstring \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# .htaccess (FallbackResource / RewriteRule / Require all denied) zulassen
RUN sed -ri 's#AllowOverride None#AllowOverride All#g' /etc/apache2/apache2.conf
