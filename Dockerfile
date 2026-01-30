# Dockerfile optimizado para Railway
FROM php:8.4-cli

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    libpq-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Instalar extensiones de PHP
RUN docker-php-ext-install pdo_pgsql pgsql mbstring exif pcntl bcmath gd opcache

# Configurar opcache para producción
RUN echo "opcache.enable=1" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.memory_consumption=256" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.max_accelerated_files=20000" >> /usr/local/etc/php/conf.d/opcache.ini \
    && echo "opcache.validate_timestamps=0" >> /usr/local/etc/php/conf.d/opcache.ini

# Instalar Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Instalar Node.js 20.x
RUN curl -fsSL https://deb.nodesource.com/setup_20.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/*

# Configurar directorio de trabajo
WORKDIR /var/www/html

# Copiar todo el código primero (más simple y confiable)
COPY . .

# Instalar dependencias de PHP (sin dev)
RUN composer install --no-dev --optimize-autoloader --no-interaction --prefer-dist

# Instalar dependencias de Node.js (con dev para el build)
RUN npm ci

# Build de assets con Vite
RUN npm run build

# Limpiar node_modules para ahorrar espacio (ya no se necesitan después del build)
RUN rm -rf node_modules

# Crear directorios necesarios y establecer permisos
RUN mkdir -p storage/framework/{sessions,views,cache} \
# Crear directorios necesarios y establecer permisos
RUN mkdir -p storage/framework/{sessions,views,cache} \
    && mkdir -p storage/logs \
    && mkdir -p bootstrap/cache \
    && chmod -R 775 storage bootstrap/cache

# Script de inicio simplificado
COPY <<'EOF' /start.sh
#!/bin/bash

echo "=== Iniciando aplicación Laravel en Railway ==="

# Crear archivo .env si no existe (Railway usa variables de entorno)
if [ ! -f /var/www/html/.env ]; then
    echo "Creando archivo .env desde variables de entorno..."
    cat > /var/www/html/.env << ENVFILE
APP_NAME="${APP_NAME:-Laravel}"
APP_ENV="${APP_ENV:-production}"
APP_KEY="${APP_KEY}"
APP_DEBUG="${APP_DEBUG:-false}"
APP_URL="${APP_URL:-http://localhost}"
APP_TIMEZONE="${APP_TIMEZONE:-UTC}"
APP_LOCALE="${APP_LOCALE:-en}"
APP_FALLBACK_LOCALE="${APP_FALLBACK_LOCALE:-en}"
APP_FAKER_LOCALE="${APP_FAKER_LOCALE:-en_US}"

LOG_CHANNEL="${LOG_CHANNEL:-stack}"
LOG_LEVEL="${LOG_LEVEL:-error}"

DB_CONNECTION="${DB_CONNECTION:-pgsql}"
DB_HOST="${DB_HOST:-${PGHOST}}"
DB_PORT="${DB_PORT:-${PGPORT}}"
DB_DATABASE="${DB_DATABASE:-${PGDATABASE}}"
DB_USERNAME="${DB_USERNAME:-${PGUSER}}"
DB_PASSWORD="${DB_PASSWORD:-${PGPASSWORD}}"

SESSION_DRIVER="${SESSION_DRIVER:-database}"
SESSION_LIFETIME="${SESSION_LIFETIME:-120}"

BROADCAST_CONNECTION="${BROADCAST_CONNECTION:-log}"
FILESYSTEM_DISK="${FILESYSTEM_DISK:-public}"
QUEUE_CONNECTION="${QUEUE_CONNECTION:-database}"

CACHE_STORE="${CACHE_STORE:-database}"
CACHE_PREFIX="${CACHE_PREFIX}"

MAIL_MAILER="${MAIL_MAILER:-log}"
MAIL_HOST="${MAIL_HOST:-127.0.0.1}"
MAIL_PORT="${MAIL_PORT:-2525}"
MAIL_USERNAME="${MAIL_USERNAME}"
MAIL_PASSWORD="${MAIL_PASSWORD}"
MAIL_ENCRYPTION="${MAIL_ENCRYPTION}"
MAIL_FROM_ADDRESS="${MAIL_FROM_ADDRESS:-hello@example.com}"
MAIL_FROM_NAME="${MAIL_FROM_NAME:-${APP_NAME}}"

AWS_ACCESS_KEY_ID="${AWS_ACCESS_KEY_ID}"
AWS_SECRET_ACCESS_KEY="${AWS_SECRET_ACCESS_KEY}"
AWS_DEFAULT_REGION="${AWS_DEFAULT_REGION}"
AWS_BUCKET="${AWS_BUCKET}"
AWS_URL="${AWS_URL}"

SCOUT_DRIVER="${SCOUT_DRIVER:-database}"

REDIS_HOST="${REDIS_HOST:-127.0.0.1}"
REDIS_PASSWORD="${REDIS_PASSWORD}"
REDIS_PORT="${REDIS_PORT:-6379}"
ENVFILE
    echo "Archivo .env creado"
else
    echo "Archivo .env ya existe"
fi

# Verificar variables críticas
if [ -z "$APP_KEY" ]; then
    echo "ERROR: APP_KEY no está configurada"
    exit 1
fi

echo "Variables de entorno cargadas correctamente"

# Ejecutar migraciones con manejo de errores
echo "Ejecutando migraciones..."
if php artisan migrate --force 2>&1; then
    echo "Migraciones completadas exitosamente"
else
    echo "WARNING: Las migraciones fallaron, pero continuando..."
fi

# Ejecutar seeders basado en configuración
if [ "$RUN_SEEDERS" = "true" ]; then
    echo "RUN_SEEDERS=true detectado, ejecutando seeders..."
    if php artisan db:seed --force 2>&1; then
        echo "Seeders completados exitosamente"
    else
        echo "WARNING: Los seeders fallaron, pero continuando..."
    fi
else
    echo "Verificando si necesitamos ejecutar seeders..."
    GENEROS_COUNT=$(php artisan tinker --execute="echo \App\Models\Genero::count();" 2>/dev/null || echo "0")
    if [ "$GENEROS_COUNT" = "0" ]; then
        echo "Base de datos vacía, ejecutando seeders..."
        if php artisan db:seed --force 2>&1; then
            echo "Seeders completados exitosamente"
        else
            echo "WARNING: Los seeders fallaron, pero continuando..."
        fi
    else
        echo "La base de datos ya tiene datos, saltando seeders"
    fi
fi

# Limpiar caché antiguo
echo "Limpiando caché..."
php artisan cache:clear 2>&1 || true
php artisan config:clear 2>&1 || true
php artisan route:clear 2>&1 || true
php artisan view:clear 2>&1 || true

# Cachear configuración solo si no falla
echo "Cacheando configuración..."
php artisan config:cache 2>&1 || echo "WARNING: config:cache falló"
php artisan route:cache 2>&1 || echo "WARNING: route:cache falló"
php artisan view:cache 2>&1 || echo "WARNING: view:cache falló"

# Crear enlace simbólico de storage
echo "Creando enlace simbólico de storage..."
php artisan storage:link 2>&1 || echo "Storage link ya existe o falló"

echo "=== Iniciando servidor PHP en puerto ${PORT:-8000} ==="
# Iniciar servidor PHP integrado
exec php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
EOF

RUN chmod +x /start.sh

EXPOSE 8000

CMD ["/start.sh"]
