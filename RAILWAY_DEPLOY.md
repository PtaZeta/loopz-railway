# Railway Deployment Configuration

Este archivo contiene las instrucciones para desplegar LoopZ en Railway.

## Variables de Entorno Requeridas

Railway necesita las siguientes variables de entorno. Puedes copiarlas desde `.env.railway.example`:

### Esenciales (Debes configurar estas):
```
APP_NAME=LoopZ
APP_ENV=production
APP_KEY=base64:TU_KEY_AQUI (genera con: php artisan key:generate --show)
APP_DEBUG=false
APP_URL=https://tu-app.up.railway.app
```

### Base de Datos (Railway las inyecta automáticamente si agregas PostgreSQL):
Railway proporciona estas variables automáticamente cuando conectas un servicio PostgreSQL:
- `PGHOST`
- `PGPORT`
- `PGDATABASE`
- `PGUSER`
- `PGPASSWORD`

Las variables `DB_*` en `.env.railway.example` ya están configuradas para usar estas.

### Opcionales pero Recomendadas:
- **AWS S3**: Para almacenamiento persistente de archivos (fotos, música, etc.)
- **Mail**: Configura SMTP para envío de correos
- **Spotify API**: Si usas integración con Spotify

## Pasos para Desplegar en Railway

1. **Crear cuenta en Railway**: https://railway.app

2. **Crear nuevo proyecto**:
   - Click en "New Project"
   - Selecciona "Deploy from GitHub repo"
   - Conecta tu repositorio `loopz-railway`

3. **Agregar PostgreSQL**:
   - Click en "+ New Service"
   - Selecciona "Database" → "PostgreSQL"
   - Railway lo conectará automáticamente

4. **Configurar Variables de Entorno**:
   - En tu servicio web, ve a "Variables"
   - Agrega las variables del archivo `.env.railway.example`
   - **Importante**: Genera un nuevo `APP_KEY` con:
     ```bash
     php artisan key:generate --show
     ```

5. **Configurar el Dockerfile**:
   - En "Settings" → "Deploy"
   - Asegúrate de que use `Dockerfile.railway`
   - O renombra `Dockerfile.railway` a `Dockerfile`

6. **Deploy**:
   - Railway detectará automáticamente el Dockerfile
   - Hará build y deploy automáticamente

## Variables de Base de Datos

Si Railway no inyecta las variables automáticamente, agrégalas manualmente:

```
DB_CONNECTION=pgsql
DB_HOST=${PGHOST}
DB_PORT=${PGPORT}
DB_DATABASE=${PGDATABASE}
DB_USERNAME=${PGUSER}
DB_PASSWORD=${PGPASSWORD}
```

## Almacenamiento Persistente

⚠️ **IMPORTANTE**: Railway usa almacenamiento efímero. Los archivos subidos se perderán en cada deploy.

**Solución recomendada**: Usar AWS S3 para almacenamiento:

1. Crea un bucket en AWS S3
2. Configura estas variables en Railway:
```
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=tu_key
AWS_SECRET_ACCESS_KEY=tu_secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=tu-bucket
```

## Logs y Debugging

- Ver logs: Railway Dashboard → Tu servicio → "Logs"
- Si hay errores, activa temporalmente:
  ```
  APP_DEBUG=true
  LOG_LEVEL=debug
  ```
  (No olvides desactivarlo en producción)

## Comandos Útiles Post-Deploy

Los siguientes comandos se ejecutan automáticamente en el script de inicio:

```bash
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link
```

## Problemas Comunes

### 1. Error "APP_KEY not set"
- Genera una key: `php artisan key:generate --show`
- Agrégala a las variables de Railway

### 2. Error de conexión a base de datos
- Verifica que el servicio PostgreSQL esté conectado
- Revisa que las variables `DB_*` estén configuradas

### 3. Assets no se cargan
- Verifica que `APP_URL` apunte a tu dominio de Railway
- El build de Vite se ejecuta automáticamente

### 4. Archivos subidos desaparecen
- Configura AWS S3 como se indica arriba

## Recursos

- [Railway Docs](https://docs.railway.app/)
- [Laravel Deployment](https://laravel.com/docs/deployment)
