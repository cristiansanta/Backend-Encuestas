FROM php:8.2-apache

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libonig-dev \
    libzip-dev \
    zip \
    libcurl4 \
    libcurl4-openssl-dev \ 
    unzip \
    libxml2-dev \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Habilitar módulos de Apache
RUN a2enmod rewrite
RUN a2enmod headers

# Copiar configuración de Apache
COPY ./docker/apache/apache-config.conf /etc/apache2/sites-available/000-default.conf

# Configurar document root
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Instalar extensiones PHP
RUN docker-php-ext-install pdo_pgsql pgsql zip curl xml mbstring

# Copiar configuración PHP
COPY ./docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Establecer directorio de trabajo
WORKDIR /var/www/html

# Copiar archivos de la aplicación
COPY . .

# Instalar dependencias de Composer
RUN composer install --optimize-autoloader

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 /var/www/html \
    && chmod -R 777 /var/www/html/storage \
    && chmod -R 777 /var/www/html/bootstrap/cache

# Limpiar caché de Laravel
RUN php artisan config:clear || true
RUN php artisan cache:clear || true
RUN php artisan route:clear || true
RUN php artisan view:clear || true

# Cambiar a usuario www-data
USER www-data

# Exponer puerto 80
EXPOSE 80

# Iniciar Apache
CMD ["/usr/sbin/apachectl", "-D", "FOREGROUND"]