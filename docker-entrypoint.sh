#!/bin/sh
set -e

# Asegurar directorios de storage requeridos por Laravel
mkdir -p /app/storage/framework/cache/data \
         /app/storage/framework/sessions \
         /app/storage/framework/views \
         /app/storage/logs

# Instalar dependencias si no existen (en bind mount de dev).
#
# Va bajo cerrojo por la misma razón que APP_KEY, más abajo: `app` y `worker`
# arrancan a la vez, comparten /app por bind mount y corren este mismo script.
# Sin cerrojo los dos veían vendor/ ausente y lanzaban `composer install` a la
# vez sobre el mismo directorio: se pisaban los zips temporales de
# vendor/composer/ («unzip: can't open …/tmp-….zip», «corrupted zip archive
# (0 bytes)», «Install of X failed») y composer moría en los dos. Lo que lo
# convertía en bucle era el marcador: el vendor/ a medias ya existía, así que
# en cada reinicio `-d /app/vendor` lo daba por instalado, nadie reinstalaba y
# artisan caía en «Failed opening required vendor/autoload.php» para siempre.
# Por eso el marcador es vendor/autoload.php —composer lo escribe al FINAL,
# después de bajar todos los paquetes— y se comprueba DENTRO del cerrojo: el
# que esperaba ve lo que instaló el otro y no reinstala. Si composer falla,
# `set -e` aborta, el contenedor reinicia y el siguiente arranque reinstala.
(
    flock 9
    if [ ! -f "/app/vendor/autoload.php" ]; then
        echo "📦 Instalando dependencias de Composer..."
        # `policy.advisories.block false`: composer se niega a instalar si una
        # dependencia tiene un aviso de seguridad. Se desactiva a propósito: el
        # que bloquea es el paso «composer audit» de CI, que además dice cuál es
        # el paquete. Un contenedor de desarrollo no debe negarse a levantar
        # porque anoche se publicó un aviso.
        composer config policy.advisories.block false
        composer install --no-interaction --prefer-dist --optimize-autoloader
    fi

    # Node y assets, bajo el mismo cerrojo: `npm ci` borra node_modules/ antes
    # de instalar, así que dos a la vez se dejan sin archivos mutuamente.
    if [ ! -f "/app/public/build/manifest.json" ]; then
        echo "📦 Instalando dependencias de Node y compilando assets..."
        npm ci
        npm run build
    fi
) 9>/app/storage/framework/.dependencias.lock   # en /app, que SÍ es compartido; /tmp es de cada contenedor

# Generar APP_KEY si no existe.
#
# Va bajo cerrojo porque `app` y `worker` arrancan a la vez, comparten el mismo
# /app/.env por bind mount y corren este mismo script: los dos veían el archivo
# sin clave y los dos ejecutaban key:generate. El segundo hace preg_replace
# sobre «APP_KEY=» cuando el primero ya escribió, y deja la clave concatenada
# («base64:AAA=base64:BBB=»). Eso da «Unsupported cipher or incorrect key
# length», el worker no llega a frankenphp_handle_request() y el contenedor
# entra en bucle de reinicio. Se vuelve a comprobar DENTRO del cerrojo: si el
# otro contenedor ya la generó, acá no hay nada que hacer.
(
    flock 9
    if ! grep -q "^APP_KEY=base64" /app/.env 2>/dev/null; then
        echo "🔑 Generando APP_KEY..."
        php artisan key:generate --force
    fi
) 9>/app/storage/framework/.appkey.lock   # en /app, que SÍ es compartido; /tmp es de cada contenedor

# Entorno efectivo, para decidir si este arranque puede migrar y sembrar.
# La variable del proceso manda (producción la fija en el compose); en
# desarrollo no viene del compose sino de /app/.env, así que se lee de ahí.
APP_ENV_EFECTIVO="${APP_ENV:-}"
if [ -z "$APP_ENV_EFECTIVO" ] && [ -f /app/.env ]; then
    APP_ENV_EFECTIVO="$(grep -m1 '^APP_ENV=' /app/.env | cut -d= -f2- | tr -d "\"' \r")"
fi
# Lista blanca y no «distinto de production»: con APP_ENV vacío o con un valor
# desconocido (typo, «prod», «staging») NO se migra ni se siembra. Fail-safe.
case "$APP_ENV_EFECTIVO" in
    local|development|testing) PUEDE_MIGRAR=1 ;;
    *) PUEDE_MIGRAR=0 ;;
esac

echo "⏳ Esperando a MariaDB..."
until php artisan db:show >/dev/null 2>&1; do
    sleep 2
done

# Ejecución de comandos personalizados (ej. Worker)
if [ $# -gt 0 ]; then
    echo "⚡ Ejecutando comando personalizado (Worker/CLI): $@"
    exec "$@"
fi

echo "🧹 Limpiando caché..."
php artisan config:clear
php artisan route:clear
php artisan view:clear
php artisan filament:clear-cached-components || true

echo "🔗 Sincronizando Storage..."
php artisan storage:link --force

# Producción nunca migra sola (regla del dueño: el SQL se revisa y se aplica a
# mano, ver `make migraciones-sql`). Este es el entrypoint de la imagen: un
# servicio de producción que no redefina `entrypoint:` pasaría por acá.
if [ "$PUEDE_MIGRAR" = "1" ]; then
    echo "🚀 Ejecutando Migraciones..."
    php artisan migrate --force
else
    echo "⏭  APP_ENV='${APP_ENV_EFECTIVO}': no se migra (solo local/development/testing)."
fi

# --option=permissions: SOLO los permisos. Sin esta opción, shield:generate
# además REESCRIBE las policies en disco con el formato crudo de su plantilla y
# de paso borra los métodos que no son los estándar (ver
# ProvisionEnvironment::handle()): en rrhh se llevó el export() de tres
# policies. Este entrypoint corre en cada `make up`, así que sin la opción
# cualquier método propio de una policy —como OnboardingStepPolicy, reducida a
# propósito— se reescribía en cada arranque de desarrollo.
echo "🛡  Generando permisos de Shield..."
php artisan shield:generate --all --panel=admin --option=permissions --no-interaction || true

# Los seeders cargan datos de demostración: nunca fuera de desarrollo.
if [ "$PUEDE_MIGRAR" = "1" ]; then
    echo "🌱 Sembrando datos..."
    php artisan db:seed --force
else
    echo "⏭  APP_ENV='${APP_ENV_EFECTIVO}': no se siembra."
fi

echo "⚡ Iniciando Octane (FrankenPHP worker mode + watch)..."
# 100 peticiones por worker antes de reciclarlo: acota la ventana de vida de
# cualquier estado en memoria (es el equivalente a «pm.max_requests» de PHP-FPM).
exec php artisan octane:start \
    --server=frankenphp \
    --host=0.0.0.0 \
    --port=8000 \
    --workers=auto \
    --max-requests=100 \
    --watch
