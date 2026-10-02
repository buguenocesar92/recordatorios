---
paths:
  - 'routes/sitio.php'
  - 'app/Http/Controllers/Sitio/**'
  - 'app/Support/Sitio/**'
  - 'resources/views/sitio/**'
  - 'resources/views/components/sitio/**'
  - 'resources/css/sitio.css'
  - 'tools/paletas-de-entidad.php'
---

# El sitio público (landing)

Aplica a `routes/sitio.php`, `app/Http/Controllers/Sitio/**`, `app/Support/Sitio/**`,
`resources/views/sitio/**`, `resources/views/components/sitio/**`, `resources/css/sitio.css`
y `tools/paletas-de-entidad.php`.

- **Sin JavaScript.** Ningún entry de `resources/js` en `@vite`, ningún `<script>` salvo el
  JSON-LD con `@cspNonce`. Sin Livewire ni Alpine. `PortadaTest` lo vigila.
- **Sin sesión.** Las rutas van en el grupo `sitio` (bootstrap/app.php), no en `web`: no hay
  cookies, no hay CSRF. Un formulario público NO va acá: va al portal (`/portal`).
- **Direcciones desde `app.url`.** Canonical, Open Graph y sitemap usan
  `App\Support\Sitio\Direccion`; nunca `url()->current()` ni el host de la petición. Los
  assets de `@vite` salen igual: `Vite::createAssetPathsUsing()` en `AppServiceProvider::boot()`
  fuerza que toda URL de asset sea relativa a la raíz (`/build/…`), no absoluta con el host
  de la petición (`asset()` de fábrica) ni con `app.url`. La portada se sirve con
  `Cache-Control: public, s-maxage=300`: un `Host` forjado en una petición cacheada, sin
  este resolver, quedaría escrito en el `<link>`/`<script>` servido a todo el mundo durante
  esos 300 s.
- **Enlaces internos relativos.** `route($nombre, [], false)`; nunca `route()` a secas ni
  `url()->current()`: el HTML no puede depender del host de la petición (lo vigila
  `PortadaTest`).
- **Colores desde `--marca-*`.** Ningún hex en vistas ni en utilidades. La paleta neutra del
  scaffold vive entre los marcadores de `sitio.css` y el generador la reemplaza; las paletas
  están en `tools/paletas-de-entidad.php` y `PaletaDelSitioSigueLaEntidadTest` mide el contraste.
  Utilidades: `bg-lienzo`, `text-tinta`, `text-atenuado`, `bg-acento`, `text-sobre-acento`, `border-borde`, `bg-acento-fuerte` (hover de botones: contrasta en los dos modos).
- **Cacheable.** `Cache-Control: public` + ETag. Lo que se muestre tiene que ser igual para
  todo el mundo: nada que dependa de sesión, usuario o petición.
- **ETag sin nonce.** La portada calcula su ETag sobre el HTML con el nonce de CSP quitado
  (`PortadaController`): el nonce cambia por petición y un ETag automático nunca daría 304; un
  ETag desde configuración no cambiaría en un deploy. Si se agrega otra página pública
  cacheable, se copia ese patrón, nunca el «marcador de nonce» que prohíbe
  `cabeceras-y-csp.md`. Si alguna vez la portada lleva un `<script @cspNonce>` ejecutable,
  deja de poder llevar ETag: un 304 reusa el cuerpo con el nonce viejo bajo una cabecera con
  nonce nuevo.
- **Accesible por defecto.** Skip link primero, landmarks con nombre, un solo `<h1>`,
  `:focus-visible` de 2 px, área táctil ≥ 44 px, sin animación. `e2e/tests/sitio.spec.ts`
  lo mide con axe en claro y oscuro.
- **Nuevas URLs públicas** se registran en el sitemap con `Sitemap::agregar()` desde un
  service provider, y se prueban en `SitemapTest`.
