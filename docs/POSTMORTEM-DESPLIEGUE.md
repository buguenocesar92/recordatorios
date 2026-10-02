# Postmortem — El release que tumbó producción (y cómo no repetirlo)

> Proyectos generados con este scaffold corren en producción como `www-data` (no
> root). Este documento existe para que **el incidente del 13-07-2026 no vuelva a
> pasar en ningún sistema del ecosistema**. Léelo antes de tocar el arranque de prod.

## Qué pasó

Un release `develop→main` de un sistema en producción (discapacidad) dejó el
servicio **caído ~1,5 horas**. La causa de fondo no fue un bug nuevo: fue un
endurecimiento correcto —pasar de servir como **root** a servir como **`www-data`**—
que **destapó 5 problemas que root venía tapando**.

## Los 5 bugs (todos por el cambio root → www-data)

| # | Bug | Síntoma | Fix |
|---|-----|---------|-----|
| 1 | La red compartida `municipio_servicios` no existía en el VPS | `network ... declared as external, but could not be found` | El deploy la crea si falta (idempotente) |
| 2 | `user: "1000:1000"` del compose **base** se heredaba en prod | `The file "./composer.json" is not writable` | `user: "root"` en `docker-compose.prod.yml` |
| 3 | `shield:generate --all` **escribe archivos** `.php` en `app/Policies/` | `file_put_contents(...Policy.php): Permission denied` | `--option=permissions` (solo BD) |
| 4 | `git pull` en el VPS tras reescribir historia | `divergent branches` | `git fetch` + `git reset --hard origin/<rama>` |
| 5 | **El `.env` con `chmod 600` (dueño deploy) era ilegible por `www-data`** | `failed to initialize workers: too many consecutive failures` | El `.env` se monta como `/app/.env.host` y el entrypoint (root) lo **copia** a `/app/.env` con dueño `www-data` |

## Por qué el #5 costó horas: el síntoma MENTÍA

El error de FrankenPHP (`failed to initialize workers`) no decía nada del `.env`.
Y cuatro pistas apuntaban en la dirección equivocada:

- **`migrate` y `config:cache` funcionaban** → porque el compose les pasa `DB_*` por
  `environment:`, no los lee del `.env`.
- **El contenedor `worker` arrancaba sano** → no sirve HTTP, no necesita `APP_KEY`.
- **A mano con `docker run --env-file .env` funcionaba** → Docker lee el archivo
  **como root** y lo inyecta como variables.
- **Antes del release funcionaba** → el servidor corría como **root**, que sí podía
  leer el archivo.

Sin poder leer el `.env`, Laravel arrancaba **sin `APP_KEY`** → el boot HTTP moría →
el worker de FrankenPHP no llegaba a atender ninguna petición.

**Cómo se encontró:** bisección con `docker run` en el VPS, agregando de a una las
diferencias del compose (volumen, límites, env, redes). Todas pasaban; el fallo
apareció al **quitar `--env-file`** → eso probó que la app dependía de esas
variables, es decir, que **no estaba leyendo el `.env`**.

## Cómo NO repetirlo

### 1. El scaffold ya trae el patrón correcto
Todo proyecto generado sirve como `www-data`, monta el `.env` como `.env.host` y el
entrypoint lo copia con el dueño correcto. **No lo deshagas.**

### 2. Regla de oro del compose
`docker-compose.prod.yml` **hereda `user:` y `environment:` del base**. Antes de
desplegar, mira qué se filtra:
```bash
docker compose -f docker-compose.yml -f docker-compose.prod.yml config | less
```

### 3. Checklist antes de un release a producción
- [ ] `docker compose ... config` revisado: `user: root` en app/worker, `.env` montado como `.env.host`.
- [ ] El entrypoint corre `composer install` y el `chown` de `public/` **como root**; el resto con `su-exec www-data`.
- [ ] `shield:generate` usa `--option=permissions` (no escribe Policies).
- [ ] Octane con `--workers` **fijo** (nunca `auto`: levanta uno por CPU del host).
- [ ] El workflow deja el `.env` en `chmod 600` (el entrypoint lo copia dentro; **no** `chgrp` en el host: falla con "Operation not permitted").

### 4. Verificar el arranque de verdad (no solo `/up`)
Construir la imagen de prod, levantarla con el `.env` montado como `.env.host`, y comprobar:
```bash
# el servidor corre como www-data
docker exec <c> sh -c "ps -o user,args | grep octane"
# el .env quedó legible por www-data
docker exec <c> sh -c "ls -l /app/.env"          # -> www-data 600
# la app ve el APP_KEY
docker exec <c> sh -c 'su-exec www-data php artisan tinker --execute="echo config(\"app.key\")?\"OK\":\"SIN KEY\";"'
# una página real (no solo /up) responde 200
docker exec <c> sh -c "curl -s -o /dev/null -w '%{http_code}' localhost:8000/login"
```

### 5. Diagnóstico cuando un deploy quede unhealthy
El workflow ya vuelca `docker compose logs app` + `storage/logs/laravel.log` + recursos
del host al fallar. Si FrankenPHP dice `failed to initialize workers`, es un **fatal
de PHP al bootear** que el servidor se traga: casi siempre `.env`/`APP_KEY` o permisos.

---

*Incidente del 2026-07-13. Los 5 fixes están en el scaffold y en los 6 sistemas del
ecosistema (verificado construyendo la imagen de producción real).*
