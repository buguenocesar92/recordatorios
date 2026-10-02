#!/usr/bin/env bash
# Corre la suite de ESTE checkout (un worktree o el principal) sin tocar la de nadie más.
#
#   tools/dev/test-aislado.sh <nombre> [args de pest…]   Pest en un contenedor desechable
#   tools/dev/test-aislado.sh <nombre> --pint            Pint sobre los archivos sucios
#   tools/dev/test-aislado.sh <nombre> --js              Vitest (npm run test:js) en el host
#
# Por qué existe: `make test` hace `docker compose exec app …` y corre contra
# `sistema_test`. Con dos sesiones (o un worktree) en paralelo, las dos suites se
# borran la base mutuamente con RefreshDatabase y, peor, el contenedor `app` tiene
# montado el checkout PRINCIPAL: desde un worktree se probaba código ajeno.
#
# Qué hace:
#   - `docker run --rm` con la imagen del stack, unido a la red del compose que ya
#     corre (alcanza `db` y `redis`; no crea redes ni reinicia contenedores).
#   - El contenedor MariaDB y la imagen se DEDUCEN del docker-compose.yml de este
#     checkout (`container_name: <prefijo>-app-mariadb` y la imagen del contenedor
#     `<prefijo>-app-frankenphp`), así que sirve igual en el scaffold que en un
#     sistema generado, cuyo prefijo cambia con el slug. Se pueden forzar con
#     TEST_AISLADO_DB y TEST_AISLADO_IMAGEN.
#   - Monta este checkout en /app. vendor/: el propio si existe; si no, el del
#     checkout principal EN SOLO LECTURA, y sólo si los composer.lock son idénticos.
#   - Base propia `sistema_def_<nombre>_test` (termina en `_test` porque
#     tests/bootstrap.php aborta con cualquier otra), creada con el root del
#     contenedor de MariaDB leído de SU propio entorno, sin imprimirlo.
#   - phpunit.xml fuerza DB_DATABASE=sistema_test (force="true"), así que
#     `-e DB_DATABASE` no aísla: se genera una copia en /tmp DENTRO del contenedor
#     con rutas absolutas y la base propia, y se pasa con `-c`. Nada queda en el repo.
#   - Redis en las bases 14/15 con prefijo propio, lejos de la 0/1 del stack.
#   - Credenciales de Reverb de mentira: sin .env el broadcaster `reverb` no se
#     construye y los tests de canales caen.
#
# Límites que conviene saber:
#   - `--parallel` NO funciona: el usuario de la BD sólo tiene permisos sobre la base
#     propia y no puede crear una base por worker (`..._test_1`, `_2`…). Corre en serie.
#   - `docker run` lleva `--user "$(id -u):$(id -g)"`: sin eso el contenedor escribe
#     storage/, .phpunit.result.cache y compañía como root en tu checkout.
set -euo pipefail

uso() {
    sed -n '2,6p' "$0" | sed 's/^# \{0,1\}//' >&2
    exit 2
}

[ $# -ge 1 ] || uso
nombre="$1"
shift
[[ "$nombre" =~ ^[a-z0-9_]{1,32}$ ]] || { echo "El nombre sólo admite [a-z0-9_], hasta 32." >&2; exit 2; }

raiz="$(cd "$(dirname "$0")/../.." && pwd)"
principal="$(dirname "$(git -C "$raiz" rev-parse --path-format=absolute --git-common-dir)")"

# ── --js: Vitest en el host ─────────────────────────────────────────────────────
if [ "${1:-}" = "--js" ]; then
    shift
    cd "$raiz"
    if [ ! -f node_modules/.package-lock.json ]; then
        cache="${TMPDIR:-/tmp}/npm-cache-test-aislado"
        mkdir -p "$cache"
        # --ignore-scripts: ninguna postinstall hace falta para Vitest, y algunas
        # (ffmpeg-static) intentan bajar un binario que falla tras un proxy.
        echo "node_modules ausente: npm ci --ignore-scripts --cache $cache" >&2
        npm ci --ignore-scripts --no-audit --no-fund --cache "$cache"
    fi
    exec npm run test:js -- "$@"
fi

# ── contenedores del stack (deducidos del compose de ESTE checkout) ────────────
prefijo="$(sed -n 's/^[[:space:]]*container_name:[[:space:]]*["'\'']\{0,1\}\([^"'\''[:space:]]*\)-app-[a-z]*["'\'']\{0,1\}[[:space:]]*$/\1/p' "$raiz/docker-compose.yml" | head -n1)"
db_cont="${TEST_AISLADO_DB:-${prefijo}-app-mariadb}"
if [ -n "${TEST_AISLADO_IMAGEN:-}" ]; then
    imagen="$TEST_AISLADO_IMAGEN"
else
    imagen="$(docker inspect "${prefijo}-app-frankenphp" --format '{{.Config.Image}}' 2>/dev/null || true)"
fi
[ -n "$imagen" ] || { echo "No pude deducir la imagen del stack (¿corre ${prefijo}-app-frankenphp?). Fuérzala con TEST_AISLADO_IMAGEN." >&2; exit 1; }
docker inspect "$db_cont" >/dev/null 2>&1 || { echo "No corre $db_cont: levanta el stack (make up) en el checkout principal." >&2; exit 1; }
red="$(docker inspect "$db_cont" --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}} {{end}}' | awk '{print $1}')"

# ── vendor/ ─────────────────────────────────────────────────────────────────────
montaje_vendor=()
if [ -f "$raiz/vendor/autoload.php" ]; then
    : # el propio, ya montado con el checkout
elif [ "$raiz" != "$principal" ] && [ -f "$principal/vendor/autoload.php" ] \
    && cmp -s "$principal/composer.lock" "$raiz/composer.lock"; then
    # .temp: Pest escribe ahí su caché de resultados aunque se le pida que no;
    # en un vendor de solo lectura va a un tmpfs del contenedor.
    montaje_vendor=(-v "$principal/vendor:/app/vendor:ro" --tmpfs /app/vendor/pestphp/pest/.temp:uid="$(id -u)",gid="$(id -g)")
else
    echo "Sin vendor/ utilizable: el composer.lock difiere del checkout principal y este" >&2
    echo "checkout no tiene vendor/ propio. Instálalo (composer install) antes." >&2
    exit 1
fi

# public/build: los tests que dibujan vistas con @vite necesitan el manifiesto. Si
# este checkout no lo compiló, se usa el del principal en solo lectura.
montaje_build=()
if [ ! -f "$raiz/public/build/manifest.json" ] && [ -f "$principal/public/build/manifest.json" ]; then
    montaje_build=(-v "$principal/public/build:/app/public/build:ro")
fi

# ── credenciales, sin imprimirlas ──────────────────────────────────────────────
base="sistema_def_${nombre}_test"
DB_USERNAME="$(docker exec "$db_cont" printenv MARIADB_USER)"
DB_PASSWORD="$(docker exec "$db_cont" printenv MARIADB_PASSWORD)"
export DB_USERNAME DB_PASSWORD

comun=(--rm --network "$red" --user "$(id -u):$(id -g)" --entrypoint ""
    -v "$raiz:/app" "${montaje_vendor[@]}" "${montaje_build[@]}" -w /app
    -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer)
tty=()
[ -t 1 ] && tty=(-t)

# ── --pint ──────────────────────────────────────────────────────────────────────
if [ "${1:-}" = "--pint" ]; then
    shift
    exec docker run "${comun[@]}" "${tty[@]}" "$imagen" ./vendor/bin/pint --dirty "$@"
fi

docker exec -e BASE="$base" "$db_cont" sh -c '
    mariadb -uroot -p"$MARIADB_ROOT_PASSWORD" -e "
        CREATE DATABASE IF NOT EXISTS \`$BASE\`;
        GRANT ALL PRIVILEGES ON \`$BASE\`.* TO \`$MARIADB_USER\`@\`%\`;"
'

# Un worktree nace sin .env y Laravel avisa (warning de PHPUnit en cada test) si
# no hay ninguno. Uno VACÍO basta: todo llega por -e, que manda sobre el archivo.
# Está en .gitignore; el .env de otro checkout nunca se lee ni se copia.
[ -e "$raiz/.env" ] || : > "$raiz/.env"

# APP_KEY desechable, generada en cada corrida.
app_key="base64:$(head -c 32 /dev/urandom | base64)"

exec docker run "${comun[@]}" "${tty[@]}" \
    -e APP_ENV=testing -e APP_KEY="$app_key" \
    -e DB_CONNECTION=mariadb -e DB_HOST=db -e DB_PORT=3306 -e DB_DATABASE="$base" \
    -e DB_USERNAME -e DB_PASSWORD \
    -e REDIS_HOST=redis -e REDIS_DB=14 -e REDIS_CACHE_DB=15 -e REDIS_PREFIX="def_${nombre}_" \
    -e REVERB_APP_ID=test-aislado -e REVERB_APP_KEY=test-aislado \
    -e REVERB_APP_SECRET="$(head -c 16 /dev/urandom | od -An -tx1 | tr -d ' \n')" \
    -e BASE="$base" \
    "$imagen" sh -c '
        set -e
        mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs
        sed -e "s#name=\"DB_DATABASE\" value=\"[^\"]*\"#name=\"DB_DATABASE\" value=\"$BASE\"#" \
            -e "s#bootstrap=\"tests/#bootstrap=\"/app/tests/#" \
            -e "s#noNamespaceSchemaLocation=\"vendor/#noNamespaceSchemaLocation=\"/app/vendor/#" \
            -e "s#<directory>\([a-z]\)#<directory>/app/\1#" \
            phpunit.xml > /tmp/phpunit.xml
        grep -q "value=\"$BASE\" force=\"true\"" /tmp/phpunit.xml || { echo "phpunit.xml cambió: no pude forzar la base." >&2; exit 1; }
        exec php -d memory_limit=1G ./vendor/bin/pest -c /tmp/phpunit.xml "$@"
    ' pest "$@"
