# Categorías OWASP aplicadas a Laravel

Checklist por categoría. Para cada una: qué buscar y dónde.

## A01 — Control de acceso roto

El más común y el de mayor impacto en sistemas municipales.

- **IDOR**: toda ruta o acción que reciba un ID debe verificar propiedad o
  permiso. `Solicitud::find($id)` sin `authorize()` es IDOR.
- Preferir route model binding con scoping:
  `Route::get('/solicitudes/{solicitud}')` + policy.
- Global scopes por tenant o por usuario donde aplique.
- Escalada de privilegios: ¿algún endpoint permite cambiar el rol propio?
- Rutas de admin sin middleware. Verificar con `route:list` que **toda** ruta
  administrativa tenga `auth` + autorización.
- Métodos `force*` o `restore` sin policy.

## A02 — Fallas criptográficas

- Datos personales sin cifrar en la base.
- `APP_KEY` versionado o compartido entre entornos.
- Contraseñas: `bcrypt`/`argon`, nunca `md5`, `sha1` ni `hash()` a secas.
- Tokens generados con `rand()` o `uniqid()` en vez de `Str::random()` o
  `random_bytes()`.
- Cookies sensibles sin `secure` ni `httpOnly`.
- HTTPS forzado en producción.

## A03 — Inyección

- `DB::raw()`, `whereRaw()`, `selectRaw()` con variables interpoladas.
  Deben usar bindings.
- `orderBy($request->sort)` — inyección por columna. Validar contra lista
  blanca.
- Comandos del sistema: `exec()`, `shell_exec()`, `Process::run()` con input
  del usuario. Escapar o eliminar.
- XSS: `{!! !!}` en Blade con contenido de usuario. Revisar cada uso.
- LDAP, XML (XXE en parseo de archivos subidos).

## A04 — Diseño inseguro

- ¿Hay límite de intentos en login, recuperación de clave y endpoints caros?
- ¿El flujo de recuperación de contraseña permite enumerar usuarios?
- ¿Las operaciones destructivas tienen confirmación y quedan en el log?
- ¿Hay separación entre lo que un funcionario puede ver y lo que puede
  modificar?

## A05 — Configuración incorrecta

- `APP_DEBUG=true` en producción. Es fuga de credenciales directa.
- `.env` accesible por web. Verificar el document root.
- Rutas de debug expuestas: Telescope, Horizon, Log Viewer sin auth.
- Headers de seguridad: CSP, X-Frame-Options, X-Content-Type-Options.

### CSP con nonce en Laravel + Filament — cuatro cosas verificadas

1. **Nunca implementar el nonce con un marcador y `str_replace` sobre el
   cuerpo.** Ese reemplazo recorre TODO el HTML, incluido lo que sale de la base
   de datos: el día que haya una inyección —el único día en que la política
   importa— al atacante le basta escribir el marcador como valor de su atributo
   `nonce` y el propio servidor se lo convierte en válido. Comprobado en un
   sistema real: la respuesta salía con el mismo nonce que declaraba la
   cabecera. Si hace falta cachear páginas, la salida es dejar fuera de la caché
   las respuestas con nonce, o autorizar por hash (que es estable).

2. **Compartir el nonce con Vite** (`Vite::useCspNonce($nonce)`). Sin eso, todo
   guion en línea que no etiquete la vista a mano queda bloqueado. Caso real:
   Laravel Boost inyecta su lector de consola en línea, y la política lo
   cortaba — la herramienta `browser-logs` no devolvía nada y el único aviso era
   un error de CSP en la consola del navegador, justo lo que esa herramienta
   existe para leer.

3. **El panel de Filament 5 todavía exige `'unsafe-inline'`.** Intentado quitar
   (2026-08-29) con el nonce ya repartido: cuatro bloqueos de política y tres
   errores de página; Alpine y Livewire arrancan, pero el panel queda degradado.
   Su documentación no ofrece forma de pasarle un nonce. `'unsafe-eval'` también
   es necesario —así funciona Alpine—, y ambos deben acotarse a `/admin`, que
   está detrás del ingreso y del segundo factor.

4. **`worker-src` y `media-src` explícitos.** Sin ellos el navegador cae a
   `default-src`, y el campo de subida deja las miniaturas en gris y los videos
   en negro, **fallando en silencio**.

Cómo se comprueba: abrir la página en el navegador y contar los errores de
consola. Una política no se audita leyendo la cabecera — se audita mirando qué
bloquea.
- CORS permisivo (`*`) en endpoints autenticados.
- Mensajes de error que revelan estructura interna.
- Permisos de archivos en el VPS: `storage/` y `bootstrap/cache/` no deben
  ser world-writable.

## A06 — Componentes vulnerables

```
composer audit
npm audit
```
Revisar también paquetes abandonados y forks propios sin actualizar.

## A07 — Fallas de identificación y autenticación

- Sesión: ¿se regenera el ID al iniciar sesión?
- ¿Se invalidan las sesiones al cambiar contraseña?
- MFA: verificar que cubra **todas** las acciones, no solo el login (ver el
  gotcha de middleware persistente).
- Timeout de sesión razonable para un panel con datos personales.
- "Recordarme" en sistemas municipales: evaluar si corresponde.

## A08 — Fallas de integridad

- Deserialización de datos no confiables (`unserialize()` sobre input).
- Jobs en cola con payload manipulable.
- Webhooks sin verificación de firma. Todo webhook necesita HMAC o
  equivalente.
- Actualizaciones o migraciones aplicadas automáticamente sin revisión.

## A09 — Fallas de registro y monitoreo

- ¿Se registran los intentos fallidos de login y de autorización?
- ¿Se registra el acceso a datos personales? (obligatorio bajo Ley 21.719)
- ¿Alguien revisa esos logs, o solo se acumulan?
- Los logs no deben contener contraseñas, tokens ni PII en claro.

## A10 — SSRF

Ver sección 8 de `livewire-filament.md`. Es la categoría más subestimada en
aplicaciones que generan PDFs, previsualizan enlaces o integran APIs
configurables.

## Comandos de reconocimiento

```bash
php artisan route:list --except-vendor          # rutas y middleware
grep -rn "DB::raw\|whereRaw\|selectRaw" app/    # inyección potencial
grep -rn "{!!" resources/views/                 # XSS potencial
grep -rn "public function" app/Livewire/        # superficie Livewire
grep -rn "public \$" app/Livewire/              # propiedades expuestas
grep -rn "Locked" app/Livewire/                 # cuáles SÍ están protegidas
grep -rn "authorize\|can(" app/                 # dónde hay autorización
composer audit && npm audit
```
