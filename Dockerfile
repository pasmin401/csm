FROM ubuntu:22.04

ENV DEBIAN_FRONTEND=noninteractive

RUN apt-get update && apt-get install -y \
    apache2 \
    php8.1 \
    php8.1-pgsql \
    php8.1-pdo \
    libapache2-mod-php8.1 \
    && rm -rf /var/lib/apt/lists/*

# Enable only prefork MPM (no conflict)
RUN a2dismod mpm_event && a2enmod mpm_prefork php8.1 rewrite headers

WORKDIR /var/www/html
COPY . .
COPY apache.conf /etc/apache2/sites-available/000-default.conf

RUN mkdir -p /tmp/uploads && chmod 777 /tmp/uploads
RUN chown -R www-data:www-data /var/www/html

RUN printf '#!/bin/bash\nPORT="${PORT:-80}"\nsed -i "s/Listen 80/Listen ${PORT}/" /etc/apache2/ports.conf\nsed -i "s/<VirtualHost \\*:80>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf\nexec apache2ctl -D FOREGROUND\n' > /start.sh && chmod +x /start.sh

EXPOSE 8080
CMD ["/start.sh"]
