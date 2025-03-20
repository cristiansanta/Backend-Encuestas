# Despliegue

- [Preparación](#preparation)
- [Docker](#docker)
- [Producción](#production)
- [CI/CD](#cicd)

<a name="preparation"></a>
## Preparación

1. Optimizar autoloader:
```bash
composer install --optimize-autoloader --no-dev
```

2. Optimizar configuración:
```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

<a name="docker"></a>
## Docker

```dockerfile
// filepath: /home/midudev/Documents/dev-2025/Backend-Encuestas/Dockerfile
FROM php:8.1-fpm

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    postgresql-dev

# Install PHP extensions
RUN docker-php-ext-install pdo_pgsql mbstring exif pcntl bcmath gd

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

COPY . .

RUN composer install --optimize-autoloader --no-dev
```

<a name="production"></a>
## Producción

1. Variables de entorno:
```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://encuestas.example.com

DB_CONNECTION=pgsql
DB_HOST=production-db-host
DB_DATABASE=encuestas_prod
```

2. Configurar Nginx:
```nginx
server {
    listen 80;
    server_name encuestas.example.com;
    root /var/www/public;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

<a name="cicd"></a>
## CI/CD con GitHub Actions

```yaml
// filepath: /home/midudev/Documents/dev-2025/Backend-Encuestas/.github/workflows/deploy.yml
name: Deploy

on:
  push:
    branches: [ main ]

jobs:
  deploy:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
          
      - name: Deploy to production
        run: |
          composer install --no-dev --optimize-autoloader
          php artisan config:cache
          php artisan route:cache
```