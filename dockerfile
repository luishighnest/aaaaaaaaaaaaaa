FROM php:8.2-apache

# Installa estensioni PHP necessarie
RUN apt-get update && apt-get install -y \
    libcurl4-openssl-dev \
    libzip-dev \
    zip \
    unzip \
    && docker-php-ext-install curl zip \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Abilita mod_rewrite e mod_headers di Apache
RUN a2enmod rewrite headers

# Configura Apache: index.php ha priorità su index.html
RUN echo '<IfModule mod_dir.c>\n\
    DirectoryIndex index.php index.html\n\
</IfModule>' > /etc/apache2/mods-enabled/dir.conf

# Configura PHP: sessioni, memoria, timeout
RUN echo "session.save_path = /tmp" >> /usr/local/etc/php/php.ini \
    && echo "memory_limit = 256M" >> /usr/local/etc/php/php.ini \
    && echo "max_execution_time = 120" >> /usr/local/etc/php/php.ini \
    && echo "upload_max_filesize = 32M" >> /usr/local/etc/php/php.ini \
    && echo "post_max_size = 32M" >> /usr/local/etc/php/php.ini

# Configurazione Apache per AllowOverride
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Copia tutti i file del sito
COPY . /var/www/html/

# Crea cartella per file JSON scrivibili e imposta permessi
RUN mkdir -p /var/www/html/data \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/data

# Crea i file JSON se non esistono
RUN touch /var/www/html/guida_tv_sky.json \
    && touch /var/www/html/desc_cache.json \
    && touch /var/www/html/user_profiles.json \
    && touch /var/www/html/agenda.json \
    && chown www-data:www-data /var/www/html/guida_tv_sky.json \
    && chown www-data:www-data /var/www/html/desc_cache.json \
    && chown www-data:www-data /var/www/html/user_profiles.json \
    && chown www-data:www-data /var/www/html/agenda.json \
    && chmod 666 /var/www/html/guida_tv_sky.json \
    && chmod 666 /var/www/html/desc_cache.json \
    && chmod 666 /var/www/html/user_profiles.json \
    && chmod 666 /var/www/html/agenda.json

EXPOSE 80

CMD ["apache2-foreground"]
