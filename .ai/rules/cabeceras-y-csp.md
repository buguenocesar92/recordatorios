---
paths:
  - 'app/Support/Csp/**'
  - 'app/Http/Middleware/CabecerasDeEndurecimiento.php'
  - 'app/Http/Middleware/NoncePorPeticion.php'
  - 'config/csp.php'
---

# Cabeceras de seguridad y política de contenido

## Quién escribe qué

| Pieza | Trabajo | Dónde se monta |
|---|---|---|
| `Spatie\Csp\AddCspHeaders` | Escribe `Content-Security-Policy` desde los presets | grupos `web` y `sitio` (`bootstrap/app.php`), panel (`AdminPanelProvider`) y ruta `/verificar-mfa` |
| `App\Support\Csp\PoliticaDelSitio` | La política estricta: `script-src 'self'` + nonce | `config/csp.php` → `presets` |
| `App\Support\Csp\PoliticaDelPanel` | La excepción de Filament: `'unsafe-eval' 'unsafe-inline'`, `worker-src`/`media-src` con `blob:` | parámetro del middleware: `AddCspHeaders::class.':'.PoliticaDelPanel::class` |
| `App\Support\Csp\NonceParaVite` | Genera el nonce y se lo entrega a Vite en el mismo acto | `config/csp.php` → `nonce_generator` |
| `App\Http\Middleware\NoncePorPeticion` | Resuelve el nonce ANTES de dibujar y olvida el de la petición anterior | junto a `AddCspHeaders`, en `web`, `sitio` y el panel |
| `App\Http\Middleware\CabecerasDeEndurecimiento` | Todo lo que no es CSP: X-Frame-Options, nosniff, Referrer, Permissions, COOP, HSTS, X-Robots-Tag | junto a las anteriores, en `web`, `sitio` y el panel |

El grupo `sitio` (`bootstrap/app.php`, la landing pública) monta las mismas tres
piezas que `web` —`NoncePorPeticion`, `AddCspHeaders`, `CabecerasDeEndurecimiento`—
más caché con ETag; ver `.ai/rules/sitio-publico.md`.

**No se vuelve al middleware artesanal.** `SecurityHeaders.php` (170 líneas) era una de
16 copias divergentes del ecosistema, cada una con su versión del nonce horneado en caché
y del `worker-src` ausente. Con spatie la política se lee como una lista de directivas
tipadas y el nonce lo reparte el contenedor: no hay tres sitios que tengan que coincidir.
`CabecerasDeEndurecimientoTest` falla si reaparece la clase o si algún archivo de `app/`
escribe `'Content-Security-Policy'` a mano. El panel no pasa por el grupo `web`: lo que se
añada a uno se añade al otro.

## En las vistas: `@cspNonce`, no `$cspNonce`

```blade
<script @cspNonce>…</script>
```

La directiva de spatie escribe `nonce="…"` leyendo `app('csp-nonce')`. Ya no existe
`View::share('cspNonce')`. `@vite` y Livewire no necesitan nada: toman el nonce de
`Vite::cspNonce()`, adonde lo deja `NonceParaVite`.

## El panel NO lleva nonce en `script-src` (ni en `style-src`)

Por especificación, cuando la directiva declara un nonce el navegador **ignora
`'unsafe-inline'`**. Filament 5.7.6 escribe cinco `<script>` en línea sin atributo
(`vendor/filament/filament/resources/views/components/layout/base.blade.php`, líneas 96,
100, 104, 148 y 156) y `grep -rn nonce vendor/filament` devuelve cero archivos: no hay
forma de etiquetarlos. Con un nonce en la política del panel esos guiones dejan de correr
sin un solo error de servidor (visto el 2026-08-29: cuatro bloqueos y tres errores de
página). El nonce existe igual en la petición y Livewire etiqueta los suyos con él.

Cuando Filament etiquete sus guiones, endurecer es quitar `UNSAFE_INLINE` de
`PoliticaDelPanel::guiones()` y añadir `->addNonce(Directive::SCRIPT)`, **comprobando el
panel en el navegador**. `PoliticaDeContenidoTest` fija hoy que el panel no lleve `nonce-`.

## Un nonce vive exactamente una petición

Spatie lo guarda como binding `scoped`. Octane los vacía en cada operación, pero el
proceso de tests no, y un binding convertido en singleton tampoco. `NoncePorPeticion`
hace `forgetInstance('csp-nonce')` y lo resuelve de nuevo al empezar cada petición;
`NoncePorPeticionTest` exige nonces distintos en dos visitas seguidas del mismo proceso.

Si algún día se añade una caché de páginas, la salida **no** es un marcador sustituido en
el cuerpo (regalaba un nonce válido a cualquier inyección; `NonceNoSeRegalaTest`): es dejar
fuera de la caché las respuestas con nonce, o autorizar esos guiones por hash.

## Las dos directivas que fallan en silencio

El campo de subida de Filament (FilePond) necesita dos directivas que **no** están en
`default-src`. Sin ellas el navegador las bloquea sin un solo error visible:

| Directiva | Para qué | Síntoma si falta |
|---|---|---|
| `worker-src 'self' blob:` | FilePond decodifica la imagen en un Web Worker nacido de un `blob:` | recuadro **gris** para siempre |
| `media-src 'self' blob:` | el video se reproduce desde otro `blob:` | rectángulo **negro** con los controles puestos, en 0:00 |

El del video engaña: parece un video que tarda o que pesa demasiado. Costó una tarde
persiguiendo un problema de rendimiento que no existía.

**Ambas van solo en `PoliticaDelPanel`.** El sitio público se queda sin ellas (caen a
`default-src 'self'`): no bajes `blob:` a `PoliticaBase` «para que sea uniforme».

Diagnóstico —el único método que lo delata—: leer el elemento (`v.error` da
`MEDIA_ELEMENT_ERROR: Media load rejected by URL safety check`, `readyState 0`,
`networkState 3`) e inyectar un listener de `securitypolicyviolation` en el **mundo
principal** de la página, no en el mundo aislado de una extensión, que no aplica el CSP.

## Lo que no se configura por `.env`

`config/csp.php` fija `enabled` y `nonce_enabled` en `true` sin `env()`: ya pasó en el
ecosistema que una CSP quedara en Report-Only en producción porque nadie escribió la
variable. Lo único por despliegue es `CSP_REPORT_URI` (un colector propio, nunca un
tercero). Spatie aplica la CSP a toda respuesta, no solo a `text/html`; es inocuo.

Candados: `PoliticaDeContenidoTest`, `NoncePorPeticionTest`, `NonceCompartidoTest`,
`NonceNoSeRegalaTest`, `CabecerasDeEndurecimientoTest`, `CabecerasSeguridadPanelTest`.

## Al escribir un test de cabeceras

- Una ruta declarada dentro del propio test necesita `->middleware('web')` explícito, o
  el caso pasa en verde sin ejercitar ninguna cabecera.
- `expect($x)->toContain('a', 'mensaje')` **no falla nunca**: el segundo argumento es
  otro patrón a buscar, no un mensaje. Los mensajes van en `toBe()` / `toBeTrue()` /
  `toBeFalse()` con `str_contains()` a mano. Candado: `NingunCandadoUsaToContainConMensajeTest`.
- Un candado que hace grep del archivo no detecta una línea comentada. Para el middleware
  de una ruta, pregúntale al router (`$ruta->gatherMiddleware()`).
