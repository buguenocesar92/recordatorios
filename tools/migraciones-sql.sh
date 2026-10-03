#!/bin/sh
# Genera el SQL de las migraciones para que el dueño lo REVISE y lo aplique a mano.
#
# Uso: tools/migraciones-sql.sh [salida.sql]   (o `make migraciones-sql`)
#
# Corre `artisan migrate --pretend` contra una base DESECHABLE (`sistema_migsql`,
# creada vacía y borrada al terminar). `--pretend` no ejecuta nada: imprime el SQL
# de cada migración pendiente. Contra una base vacía son TODAS las migraciones, o
# sea el esquema completo. Nunca toca la base de desarrollo ni una de producción.
#
# `--pretend` no imprime lo que Laravel hace con la tabla `migrations`, así que el
# script lo agrega: el CREATE TABLE y un INSERT por migración. Sin eso, el primer
# `php artisan migrate` posterior en producción creyó pendiente todo lo ya aplicado.
set -eu

DC="docker compose"
BASE="sistema_migsql"
FECHA="$(date +%F)"
SALIDA="${1:-docs/migraciones/${FECHA}.sql}"

mkdir -p "$(dirname "$SALIDA")"
CRUDO="$(mktemp)"
trap 'rm -f "$CRUDO"' EXIT

# El usuario de la app no puede crear bases: root, con la clave que ya está dentro
# del contenedor (MARIADB_ROOT_PASSWORD), sin pasarla por la línea de comandos.
sql_root() {
    $DC exec -T db sh -c 'mariadb -uroot -p"$MARIADB_ROOT_PASSWORD"' <<EOF
$1
EOF
}

sql_root "DROP DATABASE IF EXISTS \`$BASE\`; CREATE DATABASE \`$BASE\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; GRANT ALL PRIVILEGES ON \`$BASE\`.* TO 'sistema'@'%'; FLUSH PRIVILEGES;"
trap 'rm -f "$CRUDO"; sql_root "DROP DATABASE IF EXISTS \`$BASE\`;" || true' EXIT

$DC exec -T -e DB_DATABASE="$BASE" app php artisan migrate --pretend --no-interaction > "$CRUDO"

{
    echo "-- ============================================================================"
    echo "-- SQL de migraciones para REVISAR y aplicar a mano. Generado el ${FECHA}"
    echo "-- con tools/migraciones-sql.sh (artisan migrate --pretend, base desechable vacía)."
    echo "-- Contiene el esquema COMPLETO. Contra una base que ya tiene migraciones"
    echo "-- aplicadas, ejecutar solo los bloques de las migraciones NUEVAS (ver README.md)."
    echo "-- Las sentencias select son sondeos de migraciones que dependen de datos ya"
    echo "-- existentes (renombrar permisos Shield, olvidar tokens de sesión): en una base"
    echo "-- nueva no hacen nada; en una con datos, leer la migración PHP correspondiente."
    echo "-- Nada de este archivo se ejecuta solo."
    echo "-- ============================================================================"
    echo
    echo "-- Tabla de control de Laravel (omitir si la base ya la tiene)."
    echo "CREATE TABLE IF NOT EXISTS \`migrations\` (\`id\` int unsigned NOT NULL AUTO_INCREMENT, \`migration\` varchar(255) NOT NULL, \`batch\` int NOT NULL, PRIMARY KEY (\`id\`)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    echo
    # Formato de `--pretend`: una línea «<migración> ......» por migración y,
    # debajo, una «⇂ <sentencia>» por cada sentencia. Se limpian los colores ANSI.
    sed -e 's/\x1b\[[0-9;]*m//g' "$CRUDO" | awk '
        /Creating migration table/ { next }
        /^ *(INFO|WARN) / { next }
        /^ *$/ { next }
        /^ *⇂ / {
            sentencia = $0
            sub(/^ *⇂ /, "", sentencia)
            sub(/ +$/, "", sentencia)
            print sentencia ";"
            next
        }
        / \.\.\.+ *$/ {
            cabecera = $0
            sub(/^ */, "", cabecera)
            sub(/ +\.\.\.+ *$/, "", cabecera)
            print ""
            print "-- Migración: " cabecera
            next
        }
        { print "-- (salida sin clasificar) " $0 }'
    echo
    echo "-- Registro de lo aplicado (para que \`php artisan migrate\` no lo repita)."
    for archivo in database/migrations/*.php; do
        nombre="$(basename "$archivo" .php)"
        echo "INSERT INTO \`migrations\` (\`migration\`, \`batch\`) VALUES ('${nombre}', 1);"
    done
} > "$SALIDA"

echo "SQL escrito en ${SALIDA} (revisar antes de aplicar)."
