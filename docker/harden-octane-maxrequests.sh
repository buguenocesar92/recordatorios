#!/bin/sh
# ============================================================================
# harden-octane-maxrequests.sh — ENTRYPOINT de la capa PHP2Shell.
#
# No modifica NINGUN archivo del repositorio: intercepta y reescribe.
#
# Que hace, en este orden:
#
#  1. Reescribe --max-requests=<N> a --max-requests=100 en los argumentos que
#     llegan por CMD (el compose puede fijar `command:` y sobrescribir el CMD
#     de la imagen; por esto esto va como ENTRYPOINT, no como CMD).
#
#  2. Antepone a PATH el shim /opt/php2shell/bin/php, que reescribe el mismo
#     flag en CUALQUIER invocacion de `php`. Esto es lo que baja el tope del
#     comando que el entrypoint del repo arma POR DENTRO
#     (docker-entrypoint.sh: `exec php artisan octane:start ... --max-requests=500`):
#     sin este punto, cambiar el CMD no cambiaria nada, porque el CMD del
#     servicio `app` es null y el valor 500 vive en el script del repo.
#
#  3. Devuelve el control al entrypoint real (/app/docker-entrypoint.sh) con
#     los mismos argumentos de antes.
#
# Por que 100: es el equivalente a pm.max_requests de PHP-FPM. Recicla el
# worker de Octane cada 100 peticiones, que acota la ventana de vida de
# cualquier estado en memoria.
# ============================================================================
set -e

MAX_REQUESTS="${PHP2SHELL_MAX_REQUESTS:-100}"
SHIM_DIR="/opt/php2shell/bin"
ENTRYPOINT_REAL="${PHP2SHELL_ORIGINAL_ENTRYPOINT:-/app/docker-entrypoint.sh}"

# --- 1. Reescritura de --max-requests en los argumentos recibidos -------------
# La lista se reconstruye CONSERVANDO EL ORDEN: un `set -- "$@" ...` al final
# la rotaria y dejaria el comando sin su primer argumento (el subcomando).
_pendientes=$#
_cadena=""
while [ "$_pendientes" -gt 0 ]; do
    _arg="$1"
    shift
    _pendientes=$((_pendientes - 1))

    case "$_arg" in
        --max-requests=*)
            _arg="--max-requests=$MAX_REQUESTS"
            ;;
        --max-requests)
            if [ "$_pendientes" -gt 0 ]; then
                shift
                _pendientes=$((_pendientes - 1))
                _arg="--max-requests=$MAX_REQUESTS"
            fi
            ;;
    esac

    _cadena="$_cadena '$(printf '%s' "$_arg" | sed "s/'/'\\\\''/g")'"
done

eval "set -- $_cadena"

# --- 2. Shim de `php` al frente del PATH --------------------------------------
PATH="$SHIM_DIR:$PATH"
export PATH

# --- 3. Entrypoint real --------------------------------------------------------
if [ ! -x "$ENTRYPOINT_REAL" ]; then
    echo "harden-octane-maxrequests.sh: no encuentro el entrypoint real en $ENTRYPOINT_REAL" >&2
    exit 127
fi

exec "$ENTRYPOINT_REAL" "$@"
