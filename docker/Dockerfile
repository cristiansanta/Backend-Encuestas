FROM php:8.2-apache

# Configurar zona horaria
ENV TZ=America/Bogota
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

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
    postgresql-client \
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

# Instalar Redis extension
RUN pecl install redis && docker-php-ext-enable redis

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

# Crear directorio de imágenes privadas y configurar permisos específicos
RUN mkdir -p /var/www/html/storage/app/private/images \
    && chown -R www-data:www-data /var/www/html/storage/app/private/images \
    && chmod -R 755 /var/www/html/storage/app/private/images \
    && chmod g+s /var/www/html/storage/app/private/images

# Limpiar caché de Laravel
RUN php artisan config:clear || true
RUN php artisan cache:clear || true
RUN php artisan route:clear || true
RUN php artisan view:clear || true

# Configurar Apache para puerto 8000
RUN sed -i 's/Listen 80/Listen 8000/' /etc/apache2/ports.conf

# Crear script de inicialización para permisos
RUN echo '#!/bin/bash' > /usr/local/bin/setup-permissions.sh \
    && echo 'echo "Configurando permisos de imágenes..."' >> /usr/local/bin/setup-permissions.sh \
    && echo 'mkdir -p /var/www/html/storage/app/private/images' >> /usr/local/bin/setup-permissions.sh \
    && echo 'chown -R www-data:www-data /var/www/html/storage/app/private/images' >> /usr/local/bin/setup-permissions.sh \
    && echo 'chmod -R 755 /var/www/html/storage/app/private/images' >> /usr/local/bin/setup-permissions.sh \
    && echo 'chmod g+s /var/www/html/storage/app/private/images' >> /usr/local/bin/setup-permissions.sh \
    && echo 'find /var/www/html/storage/app/private/images -type f -exec chmod 644 {} \;' >> /usr/local/bin/setup-permissions.sh \
    && echo 'echo "Permisos de imágenes configurados correctamente"' >> /usr/local/bin/setup-permissions.sh \
    && echo 'exec /usr/sbin/apachectl -D FOREGROUND' >> /usr/local/bin/setup-permissions.sh \
    && chmod +x /usr/local/bin/setup-permissions.sh

# Exponer puerto 8000
EXPOSE 8000

# Iniciar con script de permisos
CMD ["/usr/local/bin/setup-permissions.sh"]