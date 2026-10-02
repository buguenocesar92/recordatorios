#!/bin/sh
# ──────────────────────────────────────────────────────────────────────────────
# Entrypoint de PRODUCCIÓN
# ──────────────────────────────────────────────────────────────────────────────
set -e

# El contenedor arranca como root SOLO para preparar permisos (storage, .env) y baja
# a www-data para TODO lo demás: tareas de arranque y servidor. Sin esto, Octane y el
# worker correrían como root en producción.
APP_USER=www-data

# Correr un comando de artisan/composer como el usuario de la app (nunca root).
como_app() {
    su-exec "$APP_USER" "$@"
}

# ──────────────────────────────────────────────────────────────────────────────
# .env legible por el usuario que sirve.
#
# En el host el .env es 600 del usuario de deploy (correcto: tiene secretos). Si se
# monta directo en /app/.env y el servidor NO corre como root, no puede leerlo:
# Laravel arranca SIN APP_KEY y el worker de FrankenPHP muere al bootear
# ("failed to initialize workers"). Los comandos de consola no lo notan porque el
# compose les pasa DB_* por environment: por eso el síntoma despista.
#
# Aquí, que aún somos root, se copia con el dueño correcto.
# ──────────────────────────────────────────────────────────────────────────────
if [ -f /app/.env.host ]; then
    cp /app/.env.host /app/.env
    chown "${APP_USER:-root}:${APP_USER:-root}" /app/.env 2>/dev/null || true
    chmod 600 /app/.env
fi


# vendor/ viene HORNEADO en la imagen (ver Dockerfile, etapa "vendor"): el
# arranque de producción ya no depende de github.com/packagist, no corre
# `composer install` como root con internet, y un rollback a la imagen
# anterior vuelve exactamente con el vendor/ que esa imagen construyó. Si
# faltara acá, algo se rompió en el build — fallar es mejor que reinstalar en
# silencio con lo que resuelva composer.lock ese día.
if [ ! -d "/app/vendor" ]; then
    echo "✘ Falta /app/vendor: esta imagen no se construyó con la etapa 'vendor' del Dockerfile." >&2
    exit 1
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views \
         storage/app/public storage/logs bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache 2>/dev/null || true

echo "⏳ Esperando a MariaDB..."
until php artisan db:show >/dev/null 2>&1; do
    sleep 2
done

if [ $# -gt 0 ]; then
    echo "⚡ Ejecutando comando personalizado (Worker/CLI): $@"
    exec su-exec "$APP_USER" "$@"
fi

echo "🚀 Migraciones (idempotente, SIN datos demo)..."
como_app php artisan migrate --force

echo "🛡  Generando permisos de Shield (idempotente)..."
como_app php artisan shield:generate --all --option=permissions --panel=admin --no-interaction || true

echo "🔗 Storage link..."
# storage:link crea el enlace dentro de public/ (propiedad de root en la imagen):
# se le da el dueño para que www-data pueda escribirlo.
rm -f public/storage
chown "$APP_USER:$APP_USER" public 2>/dev/null || true
como_app php artisan storage:link --force

echo "⚙️  Cacheando configuración para producción..."
como_app php artisan config:cache
como_app php artisan route:cache
como_app php artisan filament:optimize || true

echo "⚡ Iniciando Octane (FrankenPHP worker mode)..."
exec su-exec "$APP_USER" php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=8000 \
    --workers="${OCTANE_WORKERS:-2}" \
    --max-requests=500
