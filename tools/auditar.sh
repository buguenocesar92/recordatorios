#!/usr/bin/env bash
# ═══════════════════════════════════════════════════════════════════════════════
# auditar.sh — Auditoría rápida de calidad/seguridad/higiene para los sistemas
# Laravel del ecosistema municipal de Graneros.
#
# Cristaliza los chequeos que se hacían a mano (grep por grep) en una sola pasada
# reproducible. Encodea los MATICES aprendidos para evitar falsos positivos:
#   - "todo" (palabra española) NO es un TODO real.
#   - whereRaw con placeholders `?` es seguro; lo que huele es addslashes/concat.
#   - {!! !!} booleano→HTML estático o dato interno no es XSS.
#   - $guarded=[] en modelos de VISTA (solo lectura) no es mass-assignment abierto.
#
# Uso:
#   tools/auditar.sh                 # audita todos los repos Laravel hermanos
#   tools/auditar.sh /ruta/al/repo   # audita uno o varios repos concretos
#   VERBOSE=1 tools/auditar.sh       # muestra cada coincidencia (file:line)
#
# Salida: informe por categoría. Código de salida ≠ 0 si hay hallazgos ALTOS
# (los que casi siempre son reales) → usable como gate de CI.
#
# Sin dependencias pesadas: solo bash + grep + git.
# ═══════════════════════════════════════════════════════════════════════════════
set -uo pipefail

VERBOSE="${VERBOSE:-0}"
ALTOS=0          # contador de hallazgos de severidad ALTA (define el exit code)
REVISAR=0        # contador de "verificar" (posibles, requieren ojo humano)

# ── Colores (se desactivan si la salida no es un terminal) ─────────────────────
if [ -t 1 ]; then
  R=$'\e[31m'; Y=$'\e[33m'; G=$'\e[32m'; B=$'\e[36m'; DIM=$'\e[2m'; Z=$'\e[0m'; BOLD=$'\e[1m'
else
  R=''; Y=''; G=''; B=''; DIM=''; Z=''; BOLD=''
fi

# Descubre los repos a auditar: los argumentos, o los hermanos con `artisan`.
descubrir_repos() {
  if [ "$#" -gt 0 ]; then printf '%s\n' "$@"; return; fi
  local base; base="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
  for d in "$base"/*/ "$base"/*/*/; do
    [ -f "${d}artisan" ] && printf '%s\n' "${d%/}"
  done
}

# Ejecuta un grep sobre app/ y reporta. $1=titulo $2=severidad(ALTO|MEDIO|INFO|VERIFICAR)
# $3=patrón grep-E  $4=filtro-exclusión(grep-vE, opcional)  $5=ámbito(app|views|repo)
chequeo() {
  local titulo="$1" sev="$2" patron="$3" excluir="${4:-}" ambito="${5:-app}"
  local dir
  case "$ambito" in
    app)   dir="app" ;;
    views) dir="resources/views" ;;
    repo)  dir="." ;;
  esac
  [ -d "$dir" ] || { return; }

  local res
  res="$(grep -rnE "$patron" "$dir" 2>/dev/null)"
  [ -n "$excluir" ] && res="$(printf '%s\n' "$res" | grep -vE "$excluir")"
  res="$(printf '%s\n' "$res" | grep -v '^[[:space:]]*$')"
  local n; n="$(printf '%s\n' "$res" | grep -c . || true)"

  if [ "$n" -eq 0 ]; then
    printf '  %s✓%s %s\n' "$G" "$Z" "$titulo"
    return
  fi

  local etiqueta color
  case "$sev" in
    ALTO)      etiqueta="ALTO";      color="$R"; ALTOS=$((ALTOS + n)) ;;
    MEDIO)     etiqueta="MEDIO";     color="$Y" ;;
    VERIFICAR) etiqueta="VERIFICAR"; color="$B"; REVISAR=$((REVISAR + n)) ;;
    *)         etiqueta="INFO";      color="$DIM" ;;
  esac
  printf '  %s✗ [%s]%s %s %s(%d)%s\n' "$color" "$etiqueta" "$Z" "$titulo" "$DIM" "$n" "$Z"
  if [ "$VERBOSE" = "1" ] || [ "$sev" = "ALTO" ]; then
    printf '%s\n' "$res" | sed "s/^/      ${DIM}/; s/$/${Z}/" | head -8
  fi
}

auditar_repo() {
  local repo="$1"
  cd "$repo" || return
  printf '\n%s══ %s ══%s\n' "$BOLD" "$(basename "$repo")" "$Z"

  printf '%s· Seguridad%s\n' "$B" "$Z"
  # Inyección SQL: SOLO el olor real — addslashes o concatenación de una variable
  # dentro del SQL crudo. Los `?` (bindings) y las cadenas constantes son seguros
  # y NO se marcan (matiz aprendido: casi todo whereRaw del ecosistema es seguro).
  local sql; sql="$(grep -rnE '(whereRaw|selectRaw|havingRaw|orderByRaw|DB::raw)\(' app 2>/dev/null \
    | grep -E 'addslashes|\.[[:space:]]*\$[a-zA-Z_]|\$[a-zA-Z_][a-zA-Z0-9_>-]*[[:space:]]*\.[[:space:]]*.?[A-Z(]' )"
  local nsql; nsql="$(printf '%s\n' "$sql" | grep -c . || true)"
  if [ "${nsql:-0}" -eq 0 ]; then
    printf '  %s✓%s SQL crudo sin concatenación de variables (bindings/constantes)\n' "$G" "$Z"
  else
    printf '  %s✗ [VERIFICAR]%s SQL crudo con concatenación/addslashes %s(%d)%s\n' "$B" "$Z" "$DIM" "$nsql" "$Z"
    REVISAR=$((REVISAR + nsql))
    printf '%s\n' "$sql" | sed "s/^/      ${DIM}/; s/$/${Z}/" | head -8
  fi
  # PII en logs (Ley 21.719): datos personales no deben registrarse.
  chequeo "PII en logs (rut/nombre/documento/secreto)" ALTO \
    "Log::(info|debug|warning|error|notice).*(\brut\b|nombre|apellido|nro_documento|->email|two_factor|password|secret)" \
    "getMessage|config\(|'error'|token no|sin token"
  # Open redirect: redirección a input del usuario sin validar.
  chequeo "Open redirect (redirect a input del usuario)" ALTO \
    "redirect\((\\\$request->|request\(\)->(input|query|get))"
  # Mass assignment abierto (revisar: en modelos de VISTA es aceptable).
  chequeo "Mass assignment abierto (\$guarded=[] / unguard)" VERIFICAR \
    "guarded = \[\]|->unguard\(|Model::unguard"

  printf '%s· Higiene%s\n' "$B" "$Z"
  # env() fuera de config/ rompe con `config:cache`.
  chequeo "env() fuera de config/ (rompe con config:cache)" MEDIO \
    "\benv\(" "//"
  # Sentencias de depuración olvidadas.
  chequeo "Depuración olvidada (dd/dump/var_dump/ray)" ALTO \
    "\b(dd|dump|var_dump|ray)\(|->dd\(|->dump\("
  # TODO/FIXME/HACK reales (mayúsculas, palabra completa; NO la palabra española 'todo').
  chequeo "TODO/FIXME/HACK pendientes" INFO \
    "\b(TODO|FIXME|HACK|XXX)\b" "phpstan"

  printf '%s· Higiene de git%s\n' "$B" "$Z"
  # Artefactos de cache versionados por error.
  local cache_track; cache_track="$(git ls-files 2>/dev/null | grep -E 'storage/framework/(cache|sessions|views)/.*\.php$' | grep -c . || true)"
  if [ "${cache_track:-0}" -gt 0 ]; then
    printf '  %s✗ [MEDIO]%s Artefactos de cache versionados %s(%s)%s\n' "$Y" "$Z" "$DIM" "$cache_track" "$Z"
  else
    printf '  %s✓%s Sin artefactos de cache versionados\n' "$G" "$Z"
  fi
  # .gitignore estándar de Laravel en storage/framework/cache.
  if [ -f "storage/framework/cache/.gitignore" ]; then
    printf '  %s✓%s .gitignore de storage/framework/cache presente\n' "$G" "$Z"
  else
    printf '  %s✗ [MEDIO]%s Falta storage/framework/cache/.gitignore\n' "$Y" "$Z"
  fi

  # ── Imagen de producción ────────────────────────────────────────────────────
  # Solo para los Dockerfile que parten de FrankenPHP: son los que se publican y
  # corren en el VPS municipal. Otros Dockerfile del repo (herramientas, bridges)
  # no tienen por qué cumplir con esto.
  if grep -qE '^FROM[[:space:]]+dunglas/frankenphp:' Dockerfile 2>/dev/null; then
    printf '%s· Imagen de producción%s\n' "$B" "$Z"

    # Etiqueta flotante: `dunglas/frankenphp:1-php8.5-alpine` la reapunta el
    # mantenedor con cada versión menor que publica, así que dos construcciones
    # del mismo commit dan imágenes distintas. Un rebuild de rutina puede meter
    # en el servidor un FrankenPHP que nadie eligió, sin commit que lo explique
    # y sin etiqueta vieja a la que volver.
    local flotantes; flotantes="$(grep -nE '^FROM[[:space:]]+dunglas/frankenphp:' Dockerfile \
      | grep -vE 'dunglas/frankenphp:[0-9]+\.[0-9]+\.[0-9]+-php[0-9]+\.[0-9]+')"
    local nflot; nflot="$(printf '%s\n' "$flotantes" | grep -c . || true)"
    if [ "${nflot:-0}" -eq 0 ]; then
      printf '  %s✓%s Imagen base de FrankenPHP fijada a una versión concreta\n' "$G" "$Z"
    else
      printf '  %s✗ [ALTO]%s Etiqueta flotante de FrankenPHP %s(%d)%s\n' "$R" "$Z" "$DIM" "$nflot" "$Z"
      ALTOS=$((ALTOS + nflot))
      printf '%s\n' "$flotantes" | sed "s|^|      ${DIM}Dockerfile:|; s|$|${Z}|" | head -8
    fi

    # Sin HEALTHCHECK, Docker da por «corriendo» un contenedor cuyo proceso sigue
    # vivo aunque Octane haya dejado de responder: el despliegue lo acepta como
    # bueno y el reinicio automático nunca se dispara.
    if grep -qE '^HEALTHCHECK' Dockerfile; then
      # La forma exec (`CMD ["curl", …]`) no tiene shell: el `|| exit 1` llegaría
      # como dos argumentos más del binario en vez de decidir el resultado, y el
      # contenedor quedaría sano pasara lo que pasara.
      if grep -A2 -E '^HEALTHCHECK' Dockerfile | grep -qE 'CMD[[:space:]]*\['; then
        printf '  %s✗ [ALTO]%s HEALTHCHECK en forma exec: sin shell el «|| exit 1» no hace nada\n' "$R" "$Z"
        ALTOS=$((ALTOS + 1))
      # El chequeo heredado de `dunglas/frankenphp` apunta a la API admin de
      # Caddy (`localhost:2019/metrics`), que responde 200 mientras Caddy esté
      # vivo aunque Octane no atienda ni una petición: declara sano un
      # contenedor con la aplicación caída. El del Dockerfile existe justamente
      # para sobreescribirlo, así que no puede volver a apuntar ahí.
      elif grep -A2 -E '^HEALTHCHECK' Dockerfile | grep -q ':2019'; then
        printf '  %s✗ [ALTO]%s HEALTHCHECK contra la API admin de Caddy: responde 200 con Octane caído\n' "$R" "$Z"
        ALTOS=$((ALTOS + 1))
      else
        printf '  %s✓%s HEALTHCHECK declarado, en forma con shell\n' "$G" "$Z"
      fi
    else
      printf '  %s✗ [ALTO]%s El Dockerfile de producción no declara HEALTHCHECK\n' "$R" "$Z"
      ALTOS=$((ALTOS + 1))
    fi
  fi

  printf '%s· Calidad%s\n' "$B" "$Z"
  # Nivel de PHPStan y presencia de baseline (deuda congelada).
  local nivel; nivel="$(grep -E '^\s*level:' phpstan.neon 2>/dev/null | grep -oE '[0-9]+' | head -1)"
  local base="no"; grep -q "phpstan-baseline" phpstan.neon 2>/dev/null && base="SÍ (deuda congelada)"
  printf '  %s·%s PHPStan nivel %s%s%s · baseline: %s\n' "$DIM" "$Z" "$BOLD" "${nivel:-?}" "$Z" "$base"
  # Supresiones @phpstan-ignore (deuda de tipos puntual).
  local ign; ign="$(grep -rnE '@phpstan-ignore' app 2>/dev/null | grep -c . || true)"
  printf '  %s·%s @phpstan-ignore: %s\n' "$DIM" "$Z" "${ign:-0}"

  cd - >/dev/null || true
}

# ── Main ───────────────────────────────────────────────────────────────────────
printf '%s%s Auditoría del ecosistema — %s %s\n' "$BOLD" "$B" "$(date +%F)" "$Z"
mapfile -t REPOS < <(descubrir_repos "$@")
if [ "${#REPOS[@]}" -eq 0 ]; then
  echo "No se encontraron repos Laravel (busca hermanos con 'artisan')." >&2
  exit 2
fi
for r in "${REPOS[@]}"; do auditar_repo "$r"; done

printf '\n%s── Resumen ──%s\n' "$BOLD" "$Z"
printf '  %sAltos (fallan el gate): %d%s\n' "$([ "$ALTOS" -gt 0 ] && echo "$R" || echo "$G")" "$ALTOS" "$Z"
printf '  %sA verificar (posibles): %d%s\n' "$B" "$REVISAR" "$Z"
printf '%s  Los "VERIFICAR" pueden ser falsos positivos: revísalos como se hizo en la auditoría manual.%s\n' "$DIM" "$Z"

[ "$ALTOS" -gt 0 ] && exit 1 || exit 0
