# Configuración

- [Configuración del Entorno](#environment)
- [Base de Datos](#database)
- [Caché y Sesiones](#cache-sessions)
- [Cola de Trabajos](#queue)
- [Correo Electrónico](#mail)

<a name="environment"></a>
## Configuración del Entorno

Las principales variables de entorno que debes configurar:

```env
APP_NAME="Sistema de Encuestas"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=localhost
DB_PORT=5432
DB_DATABASE=encuestas2
DB_USERNAME=postgres
DB_PASSWORD=sena123

CACHE_DRIVER=redis
SESSION_DRIVER=database
QUEUE_CONNECTION=database
```

<a name="database"></a>
## Base de Datos

### Configuración de PostgreSQL

1. Crear la base de datos:

```sql
CREATE DATABASE encuestas2;
```

2. Configurar índices:

```bash
php artisan db:seed --class=DatabaseSeeder
```

<a name="cache-sessions"></a>
## Caché y Sesiones

### Configuración de Redis (opcional)

```env
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379
```

### Configuración de Sesiones

```env
SESSION_DRIVER=database
SESSION_LIFETIME=120
```

<a name="queue"></a>
## Cola de Trabajos

Para procesar trabajos en segundo plano:

```bash
php artisan queue:table
php artisan migrate
php artisan queue:work
```

<a name="mail"></a>
## Correo Electrónico

### Configuración de SMTP

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@encuestas.com"
MAIL_FROM_NAME="${APP_NAME}"
```