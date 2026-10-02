# ─── Stage 1: Dependencias de Composer ──────────────────────────────────────
# Etapa propia, no el entrypoint: así el `vendor/` que sirve producción es el
# que compiló ESTE build a partir de composer.lock, no lo que un
# `composer install` corrido al arrancar el contenedor decida bajar de
# internet ese día —con el arranque de producción dependiendo de github.com y
# de packagist, y sin garantía de que un rollback a la imagen anterior vuelva
# con el mismo `vendor`—.
FROM composer:2 AS vendor
# Mismas extensiones que la etapa de runtime (más abajo): sin esto, `composer
# install` verifica los requisitos de plataforma de cada paquete contra ESTE
# PHP —el de la imagen composer:2, no el de producción— y falla en el primer
# paquete que declare "ext-intl" (filament/support la exige) aunque la imagen
# final sí la traiga.
RUN install-php-extensions pdo_mysql pcntl redis zip gd intl
WORKDIR /app

# Solo lo que decide QUÉ paquetes bajar. Así esta capa —la única que llama a
# github.com/packagist— se invalida SOLO cuando cambian las dependencias, no
# cuando cambia cualquier otro archivo del repo (un .php de app/, un test, el
# README): si copiara todo el código antes de instalar (como antes), un
# commit ajeno a composer.json forzaba un `composer install` con red real en
# cada `docker build`/`up --build` — la misma dependencia de red que este
# hallazgo sacó del arranque del contenedor, reintroducida en el build. El
# stage `assets`, más abajo, ya sigue este mismo patrón con `package.json`.
COPY composer.json composer.lock ./

# El secreto vive SOLO en este mount: no queda en ninguna capa de la imagen ni
# en su historial (a diferencia de un ARG o un ENV con el token, que build args
# y env sí dejan grabados). El runner de despliegue ya tiene COMPOSER_GH_TOKEN
# (ver .github/workflows/desplegar.yml, que lo pasa como secreto de build, el
# mismo token que ya usa ci.yml). Este scaffold, sin ese secreto, construye
# igual: este proyecto no depende de ningún paquete privado (ver README.md,
# «Decisión de origen»); el secreto solo haría falta si alguna vez se agrega uno,
# y sin él ese `composer install` simplemente no lo resuelve, en vez de dejar
# un token corriendo en el entorno de un contenedor de producción como root
# con acceso a internet.
#
# --no-scripts / --no-autoloader: los scripts de composer.json
# (`post-autoload-dump`: `package:discover`, `filament:upgrade`) necesitan
# `artisan` y el resto de la app, que en esta capa todavía no existe —se
# corren más abajo, después de copiar el código, sin volver a tocar la red—.
# El secreto llega en DOS formatos posibles, y ambos son legítimos:
#   - Token crudo: lo que pasa `desplegar.yml` (`secrets.COMPOSER_GH_TOKEN`,
#     un fine-grained token de GitHub tal cual, sin comillas ni llaves).
#   - `auth.json`: lo que genera `composer config --auth github-oauth...` a
#     mano en desarrollo (ver docker-compose.yml, bloque `secrets:`), que es
#     JSON, no un token. Antes esta capa asumía SIEMPRE token crudo y le
#     pasaba el JSON entero a `--global github-oauth.github.com`: Composer
#     terminaba con un "token" que empezaba por `{"github-oauth"...}` y
#     packagist devolvía 401/404 sin ninguna pista de qué estaba mal.
#   Se distingue por el primer carácter no vacío: `{` es JSON, cualquier otra
#   cosa es el token crudo.
#
# `policy.advisories.block false`, más abajo: composer se niega a instalar si
# alguna dependencia tiene un aviso de seguridad publicado. Se desactiva a
# propósito —no se puede comentar en la línea, porque esta RUN es un único
# script de shell y un `#` ahí comentaría también el `composer install` que
# sigue—: quien decide es el paso «composer audit» de CI, que bloquea el
# merge y además dice cuál es el paquete. Una imagen de producción no debe
# negarse a construirse porque anoche se publicó un aviso.
RUN --mount=type=secret,id=composer_auth,required=false \
    if [ -s /run/secrets/composer_auth ]; then \
        primer_caracter="$(tr -d '[:space:]' < /run/secrets/composer_auth | head -c 1)"; \
        if [ "$primer_caracter" = '{' ]; then \
            mkdir -p "$COMPOSER_HOME" && \
            cp /run/secrets/composer_auth "$COMPOSER_HOME/auth.json"; \
        else \
            composer config --global github-oauth.github.com "$(tr -d '[:space:]' < /run/secrets/composer_auth)"; \
        fi; \
    fi && \
    composer config policy.advisories.block false && \
    composer install --no-dev --no-interaction --prefer-dist \
        --no-scripts --no-autoloader

# El resto del código: `--classmap-authoritative` escanea `app/` (PSR-4) para
# construir el mapa de clases, y `package:discover`/`filament:upgrade`
# necesitan `artisan`. Esta capa se invalida en cada cambio de código —es
# inevitable, es lo que hace `dump-autoload`—, pero ya no arrastra con ella
# ninguna llamada de red: no baja paquetes, solo lee lo que ya está en
# `vendor/` y en el propio código copiado.
COPY . .
# Las carpetas de caché de Laravel tienen que existir ANTES de `dump-autoload`:
# su hook `post-autoload-dump` (`package:discover`) arranca la aplicación, y la
# config por defecto de Laravel resuelve `view.compiled` con
# `realpath(storage/framework/views)`, que devuelve false si la carpeta no está
# → «Please provide a valid cache path» y el build muere acá (primer despliegue
# de dispositivos-graneros, run 36892413754). En el disco de quien desarrolla
# las carpetas existen porque la app ya corrió; en el checkout limpio del CI
# sólo existen si git las versiona (su .gitkeep), y el .dockerignore además
# deja fuera su contenido. No es estado de runtime: son carpetas vacías, y el
# entrypoint vuelve a asegurarlas al arrancar. El candado es
# tests/Feature/CarpetasDeCacheEnElBuildTest.php.
RUN mkdir -p storage/framework/views storage/framework/cache/data \
             storage/framework/sessions storage/framework/testing \
             storage/logs bootstrap/cache
RUN composer dump-autoload --no-interaction --optimize --classmap-authoritative

# ─── Stage 2: Compilación de assets con Vite ───────────────────────────────
FROM node:26-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
# «npm ci» a secas instalaba también las devDependencies —ahí vive
# `demo-engine`, la herramienta con la que se graban los videos tutoriales,
# que NUNCA corre en el servidor— y ejecutaba los `postinstall` de terceros
# dentro del build. `demo-engine` arrastra `ffmpeg-static`, cuyo `postinstall`
# se baja un binario de ~70 MB desde GitHub mientras se construye la imagen:
# la imagen de producción no se puede construir si GitHub no responde, y
# entra al build código que ningún archivo del repo declara ni verifica.
# `--omit=dev` deja fuera todo eso; `--ignore-scripts` es la segunda
# cerradura, para que ninguna dependencia de producción ejecute un
# `postinstall` propio en el futuro (esbuild no lo necesita: resuelve su
# binario por `require.resolve` contra `@esbuild/linux-x64`, que sí se instala
# porque es `optionalDependencies`, no un script).
#
# Consecuencia obligatoria: `vite`, `laravel-vite-plugin`, `@tailwindcss/vite`
# y `tailwindcss` (la cadena que invoca `vite.config.js`) y `axios` (lo
# importa `resources/js/bootstrap.js`, que entra al bundle de `app.js`) tienen
# que estar en `dependencies`, no en `devDependencies` como es costumbre en un
# Laravel recién generado: acá esa división decide qué entra a la imagen de
# producción. El candado `higieneDeLaEtapaDeAssets`
# (`tests/Feature/CandadosDelEcosistemaTest.php`) falla si alguien vuelve a
# mover alguno de vuelta o a soltar el `npm ci` a secas.
RUN npm ci --omit=dev --ignore-scripts
# Tailwind 4 se configura desde el CSS: ya no hay tailwind.config.js ni
# postcss.config.js que copiar, y dejarlos en esta línea rompe el build de la
# imagen con «file not found».
COPY vite.config.js ./
COPY resources ./resources
# Tailwind 4 recorre el código para saber qué clases generar, y `app` es una de
# las fuentes que declara `resources/css/app.css`: hay clases escritas en línea
# en los render hooks de Filament. Sin este COPY la carpeta no existe dentro de
# la imagen y el CSS sale incompleto —sin ruido, sin error—: la hoja bajaba de
# 20 kB a 9,5 kB y se perdían utilidades que las vistas sí usan.
COPY app ./app
RUN npm run build

# ─── Stage 3: Aplicación (FrankenPHP) ───────────────────────────────────────
# La versión va COMPLETA y no `1-php8.5-alpine`: esa etiqueta la reapunta el
# mantenedor cada vez que publica una menor, así que dos construcciones del
# mismo commit dan imágenes distintas. Basta con que el CI reconstruya —una
# dependencia actualizada, un reintento— para que el servidor se lleve un
# FrankenPHP que nadie eligió y que ningún commit explica; y si trae una
# regresión no hay a qué volver, porque la etiqueta vieja ya apunta a otra
# imagen. Es la misma versión en todos los sistemas del ecosistema, para que un
# problema reproducido en uno valga para los demás.
#
# Para subirla: se cambia el número acá a propósito, en su propio commit, se
# construye la imagen y se prueba. El candado de
# tests/Feature/HigieneDeDespliegueTest.php falla si alguien vuelve a dejar una
# etiqueta flotante, y `tools/auditar.sh` lo comprueba en todos los repos
# hermanos de una pasada.
FROM dunglas/frankenphp:1.12.7-php8.5-alpine

# Instalación de extensiones de alto rendimiento
RUN install-php-extensions \
    pdo_mysql \
    pcntl \
    opcache \
    redis \
    zip \
    gd \
    intl \
    # pcov mide qué líneas tocan las pruebas. Va apagado —«pcov.enabled=0»— y
    # solo se enciende al pedirlo («make cobertura»), así que no cuesta nada en
    # producción. Se elige pcov y no Xdebug porque para esto es varias veces más
    # rápido y no trae depurador ni profiler, que en una imagen de producción
    # son superficie de más.
    pcov

# Supervisor, cliente MariaDB, Node y NPM (para dev y compilación de assets),
# curl (lo usa el HEALTHCHECK más abajo -- sin esto el contenedor queda
# "unhealthy" para siempre aunque la app esté sana)
RUN apk add --no-cache curl supervisor mariadb-client nodejs npm su-exec

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# El worker mode lo gestiona `php artisan octane:start --server=frankenphp`
# (ver entrypoints). No usamos FRANKENPHP_CONFIG/APP_RUNTIME porque Octane
# genera su propio Caddyfile y esas variables entrarían en conflicto.

WORKDIR /app
COPY . .

# vendor/ compilado en la etapa 1: composer.lock manda, no lo que resuelva un
# `composer install` corrido al arrancar el contenedor.
COPY --from=vendor /app/vendor ./vendor

# Copiar assets Vite compilados en stage 2
COPY --from=assets /app/public/build ./public/build

# Copiar configuración de supervisor
COPY docker/supervisord.conf /etc/supervisord.conf

# Ajustes de PHP propios (ver el archivo: hoy solo expose_php)
COPY docker/php-seguridad.ini /usr/local/etc/php/conf.d/zz-seguridad.ini

# Las reglas de caché de los estáticos. Se copian a /etc/caddy porque es la ruta
# que busca config/octane.php: si el archivo no está, la clave queda vacía y el
# sitio funciona igual, solo que cada visitante se vuelve a bajar todo.
COPY docker/caddy/estaticos.caddyfile /etc/caddy/estaticos.caddyfile

# Permisos. Las carpetas de caché se crean también acá, y no sólo en el
# entrypoint: `php artisan` arranca la aplicación igual que `package:discover`
# (ver la etapa vendor), y una imagen cuyo storage/framework/views sólo lo crea
# el entrypoint falla con cualquier `docker run --entrypoint` (depuración, un
# job puntual). En producción /app/storage es un volumen con nombre, que se
# puebla con esto la primera vez.
RUN mkdir -p storage/framework/views storage/framework/cache/data \
             storage/framework/sessions storage/framework/testing \
             storage/logs bootstrap/cache && \
    chown -R www-data:www-data /app/storage /app/bootstrap/cache && \
    chmod -R 775 /app/storage /app/bootstrap/cache

# FrankenPHP/Caddy necesita dónde escribir su config y sus certificados. Al correr
# como www-data (no root), su HOME no es escribible: se le dan directorios propios.
ENV XDG_DATA_HOME=/data XDG_CONFIG_HOME=/config
RUN mkdir -p /data/caddy /config/caddy && chown -R www-data:www-data /data /config

EXPOSE 8000

# Contra la ruta de salud de Laravel (bootstrap/app.php: health: '/up'), que
# comprueba la app sin tocar la base de datos ni la cola. `start-period` le da
# margen al arranque de Octane/FrankenPHP antes de contar el primer fallo.
#
# Esto SOBREESCRIBE el chequeo que trae `dunglas/frankenphp`, que apunta a
# `localhost:2019/metrics` —la API admin de Caddy—. Ese puerto lo abre Caddy y
# responde 200 aunque Octane haya dejado de atender en el 8000: el contenedor se
# declaraba sano con la aplicación caída, el despliegue lo aceptaba y el reinicio
# automático nunca se disparaba. Ojo: borrar estas dos líneas no deja la imagen
# sin chequeo, la deja con el heredado, que miente en verde.
#
# `-o /dev/null` porque `/up` devuelve la página de salud de Laravel entera, y
# sin eso Docker guarda ese HTML en el registro de cada chequeo.
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
    CMD curl -fsS http://localhost:8000/up -o /dev/null || exit 1

ENTRYPOINT ["/app/docker-entrypoint.sh"]
