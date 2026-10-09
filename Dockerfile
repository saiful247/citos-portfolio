# syntax=docker/dockerfile:1

# CITOS static site + contact form, for Google Cloud Run.
#
# Apache serves the HTML/CSS pages and runs contact.php with PHP.
# SMTP secrets are NOT baked into the image. They are supplied at runtime:
# Cloud Run environment variables / secrets, or `docker compose` with .env.

FROM php:8.3-apache

# Cloud Run tells the container which port to listen on through $PORT.
# Apache reads ${PORT} when it starts, so any container port set in Cloud Run works.
ENV PORT=8080

RUN sed -ri 's/^Listen 80$/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -ri 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
 && a2enmod headers expires

COPY docker/apache-site.conf /etc/apache2/conf-available/citos.conf
RUN a2enconf citos

# The site itself. .dockerignore keeps .env, .git and build files out.
COPY --chown=www-data:www-data . /var/www/html/
RUN rm -rf /var/www/html/docker

EXPOSE 8080
