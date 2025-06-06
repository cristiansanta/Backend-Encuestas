# Instalación

- [Requisitos Previos](#prerequisites)
- [Pasos de Instalación](#installation-steps)
- [Verificación](#verification)
- [Solución de Problemas](#troubleshooting)

<a name="prerequisites"></a>
## Requisitos Previos

Antes de comenzar, asegúrate de tener instalado:

```bash
PHP >= 8.1
Composer
PostgreSQL >= 14
Node.js >= 16
NPM >= 8
```

<a name="installation-steps"></a>
## Pasos de Instalación

1. Clonar el repositorio:

```bash
git clone https://github.com/tu-usuario/sistema-encuestas.git
cd sistema-encuestas
```

2. Instalar dependencias PHP:

```bash
composer install
```

3. Copiar el archivo de entorno:

```bash
cp .env.example .env
```

4. Configurar la base de datos en `.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=encuestas2
DB_USERNAME=postgres
DB_PASSWORD=sena123
```

5. Generar la clave de aplicación:

```bash
php artisan key:generate
```

6. Ejecutar las migraciones:

```bash
php artisan migrate
```

7. Instalar dependencias frontend:

```bash
npm install
npm run dev
```

<a name="verification"></a>
## Verificación

Para verificar la instalación:

1. Ejecutar el servidor:

```bash
php artisan serve
```

2. Ejecutar las pruebas:

```bash
php artisan test
```

<a name="troubleshooting"></a>
## Solución de Problemas

### Problemas Comunes

1. Error de permisos:
```bash
chmod -R 775 storage bootstrap/cache
```

2. Error de conexión a PostgreSQL:
```bash
sudo service postgresql restart
```

3. Error de caché:
```bash
php artisan config:clear
php artisan cache:clear
```