# Consola de operador con Inertia + React (`--frontend=inertia-react`)

Documento de arquitectura del frontend alternativo del scaffold. Explica **cuándo NO usarlo**
(que es casi siempre), qué añade exactamente al proyecto, qué no toca, y las obligaciones de
seguridad y accesibilidad que arrastra.

Origen de la decisión: `~/Dev/IDEA_INERTIA_REACT_APPS_COMPLEJAS_2026-09-05.md` y el spec
`docs/superpowers/specs/2026-09-05-frontend-inertia-react-design.md`.

---

## 1. Cuándo NO usarlo (leer esto primero)

**El valor por defecto sigue siendo Livewire.** `--frontend=livewire` produce el proyecto de
siempre, byte a byte. Esta opción existe para un caso acotado, no para «modernizar» un sistema.

Un sistema califica sólo si cumple **al menos una** de estas cuatro señales, **medida, no
imaginada**:

1. **Estado de cliente complejo que debe sobrevivir sin ida y vuelta al servidor**: varios
   paneles sincronizados entre sí, deshacer/rehacer, arrastrar y soltar con reglas de negocio
   evaluadas en el cliente.
2. **Interacción a ritmo de teclado de operador** donde 100–200 ms de red por acción se notan.
   El umbral es INP **medido en campo** por encima de 200 ms **y** con la causa identificada en
   la ida y vuelta, no en el render. Un INP malo por render pesado no se arregla cambiando de
   framework.
3. **Offline serio**: cola de sincronización, resolución de conflictos, trabajo prolongado sin
   red.
4. **Un módulo JS plano que supera ~2.000–3.000 líneas** sin estructura ni gestión de estado.

Si **ninguna** se cumple, la respuesta correcta es **quedarse en Livewire y arreglar el dolor
real**:

- pasar el JS plano a TypeScript con un store pequeño;
- parchear el DOM con Echo + Alpine en vez de redibujar la página entera por Livewire;
- aislar lo caro en islas (`@island`) para no recalcular doce widgets por actualizar uno;
- usar optimistic UI (`wire:show` / `wire:text` / `wire:bind`) antes que culpar a la red.

### El incidente que esto previene

La duda nació revisando **seguridad-graneros (CAD)**: parecía el caso obvio de «Livewire se
queda corto». Al medir apareció otra cosa: la parte interactiva de ese sistema **ya no era
Livewire** — 1 componente Livewire propio, 74 archivos Filament y ~1.950 líneas de JS plano
(`mapa.js` 997, `terreno.js` 395) hablando directo con MapLibre y Echo. Con menos de 20 móviles
en calle no había dolor que medir. El sistema que «necesitaba React» no calificaba por ninguna
de las cuatro señales.

Moraleja: **traer React sin haber medido cambia el frontend y deja el problema intacto**, y
además obliga al proyecto a mantener dos frontends para siempre.

### Lo que ya está descartado (no volver a proponerlo)

- **Vue o Svelte con Inertia**: el kit oficial los trae; React se eligió por ecosistema
  (dnd-kit, TanStack, shadcn, react-map-gl) y por ser lo más común en el mercado local para
  quien herede el sistema.
- **Todo-React sin Filament**: tira Shield, los Resources, el panel ARCOP y muni-ui.
- **React incrustado dentro de páginas Livewire/Filament** (raíz React dentro de `wire:ignore`):
  el morphing de Livewire y el ciclo de vida de React se pisan. Si hace falta React, la página
  es de Inertia **completa**.
- **Micro-frontends / module federation**: descartados en el `CLAUDE.md` del ecosistema.

---

## 2. Cómo se activa

```bash
./scaffold new "Sistema" --shared=muni --frontend=inertia-react
```

- `--shared` sigue siendo **obligatorio** y sin valor por defecto (ver
  [`docs/PAQUETE-COMPARTIDO.md`](PAQUETE-COMPARTIDO.md)).
- `--frontend` es **opcional**, con default `livewire`. Un valor desconocido mata el generador
  **antes** de copiar nada, enumerando los válidos.
- El modo asistido (`./scaffold` sin argumentos) pregunta el frontend; respuesta vacía =
  `livewire`.
- El orden de aplicación importa: copiar → parametrizar → `usar-paquete-compartido` →
  `aplicar-frontend-inertia-react` → `.env` → npm → git → up. **El frontend se aplica después
  del paquete compartido porque consume sus tokens CSS**; al revés, la consola nace con una
  paleta distinta a la del panel.

### Por qué es un preset y no una rama del árbol base

El scaffold tiene cientos de casos de prueba que asumen «panel Filament + Livewire y nada más»
(`UnaSolaPuertaDeAccesoTest`, `PoliticaDeContenidoTest`, `PanelComoUnaSolaAplicacionTest`…).
Meter Inertia en el árbol base obligaría a **todo** proyecto generado a arrastrar React aunque
no lo use, y a mantener dos juegos de tests en verde para siempre.

Por eso el frontend alternativo vive fuera del árbol Laravel, en `presets/inertia-react/`, y se
inyecta sólo cuando se pide. `presets/` se excluye de la copia: el proyecto nuevo recibe los
archivos **ya aplicados**, nunca el preset.

---

## 3. Qué añade exactamente al proyecto

| Qué | Dónde |
|---|---|
| Rutas de la consola | `routes/consola.php`, prefijo `/consola`, nombres `consola.escritorio` y `consola.eventos` |
| Middleware de esas rutas | `['web','auth','auth.session','throttle:consola','sesion.acotada','mfa', SinCacheParaAutenticados::class, HandleInertiaRequests::class]` — el mismo trío del panel más lo propio de la consola |
| Vista raíz de Inertia | `resources/views/consola.blade.php` (vista raíz `consola`) |
| Props compartidas | `app/Http/Middleware/HandleInertiaRequests.php`, declarado en el grupo de `routes/consola.php` — **no** en el grupo `web` global, para que `Inertia::share()` y `setRootView('consola')` no alcancen a ninguna ruta pública |
| Controladores | `app/Http/Controllers/Consola/{Escritorio,Eventos}Controller.php` → `Inertia::render('Escritorio')`, `Inertia::render('Eventos')` |
| Ziggy con lista blanca | `config/ziggy.php` → `only` con las rutas enumeradas **una a una** (`consola.escritorio`, `consola.eventos`, `salir`), no `consola.*` |
| Entradas de Vite | `resources/css/consola.css` y `resources/js/consola/app.tsx`; páginas en `./pages/{name}.tsx`; tipos generados en `resources/js/consola/types/generated.d.ts`, tipos del navegador en `types/inertia.d.ts` |
| Herramientas JS | `tsconfig.json` (strict, alias `@/`), `vitest.config.ts`, scripts `dev` / `types:generate` / `types:check` / `test:js` |
| CSP | ninguna propia: `/consola` hereda `PoliticaDelSitio` (spatie/laravel-csp) por el grupo `web` |
| Data classes | `app/Data/Consola/` — lo único que se pasa a `Inertia::render` (`PropsDeLaConsolaSonDataTest`) |
| Provider de tipos | `app/Providers/TypeScriptTransformerServiceProvider.php`, registrado en `bootstrap/providers.php` |
| Tests | `tests/Feature/Consola/*` (Pest) y `e2e/tests/consola-accesibilidad.spec.ts` (Playwright + axe) |
| Tests del generador | `tests/Generador/*` — no viajan al proyecto generado (`LosTestsDelGeneradorNoViajanTest`) |
| CI | **workflow aparte**, `.github/workflows/consola.yml` (`npx tsc --noEmit` + Vitest + build). No es un job dentro de `ci.yml`: insertar YAML en un archivo que el proyecto pudo tocar se rompe en silencio y deja la CI entera sin correr |
| Dockerfile | **nada**. El aplicador no lo toca — ver §4 |

Dependencias que declara: `inertiajs/inertia-laravel`, `tightenco/ziggy`, `spatie/laravel-data`
y `spatie/laravel-typescript-transformer` en `composer.json`; `@inertiajs/react`,
`@inertiajs/vite`, `react`, `react-dom`, `@vitejs/plugin-react`, `ziggy-js` y `vitest` en
`package.json`.

---

## 4. Qué NO cambia

Esto es lo importante y es la razón por la que el riesgo está acotado: **Inertia son
controladores Laravel que devuelven props en vez de una vista Blade**. Todo lo demás sigue
igual.

- **Filament 5** sigue siendo el panel administrativo: CRUD, reportes, filtros, exportaciones.
- **Shield** sigue generando y aplicando permisos; las policies de `app/Policies/` mandan.
- **El módulo ARCOP** (Ley 21.719) y **muni-ui** no se tocan.
- **Modelos, jobs, eventos, migraciones, seeders y tests de dominio** quedan intactos.
- **Despliegue** igual: FrankenPHP/Octane, mismo Dockerfile, mismo entrypoint, mismo workflow
  de despliegue. El aplicador **no toca el `Dockerfile`**, y eso es una decisión, no un olvido:
  el stage `assets` copia `package.json`, `vite.config.js`, `resources` y `app`, y nada más.

  El alias `@/` NO se declara en `vite.config.js`: lo pone `laravel-vite-plugin` por
  defecto (`'@': '/resources/js'`). `tsconfig.json` y `vitest.config.ts` sí lo declaran a
  mano (`tsc` y Vitest no cargan `laravel-vite-plugin`), y sólo los lee lo que corre fuera
  de la imagen. Lo que compila en el portátil compila en la imagen.
  `TsconfigYViteCoincidenTest` exige que `tsconfig.json`/`vitest.config.ts` sigan
  apuntando a `resources/js` y que `vite.config.js` no vuelva a declarar el alias a mano
  (eso obligaría a `import ... from 'node:url'`, que el candado del ecosistema
  `HigieneDeLaEtapaDeAssets` rechaza: no es un paquete de `dependencies`).
- **La autenticación es la misma**: una sola puerta de acceso, MFA, sesión acotada.

La consola es **sólo la capa de vistas de las superficies de operador** (mapa en vivo, despacho,
terreno). Dos frontends en un repo es el costo aceptado, no un accidente.

---

## 5. Seguridad

La política la declara `spatie/laravel-csp`. La consola **no tiene política propia**: hereda
`App\Support\Csp\PoliticaDelSitio` por el grupo `web` de `routes/consola.php` —
`script-src 'self' 'nonce-…'`, sin `'unsafe-inline'` ni `'unsafe-eval'`, `connect-src 'self'`. Si
algún día hace falta una directiva propia (Reverb), nace `PoliticaDeLaConsola` montada como
parámetro del middleware en ese archivo, igual que `/verificar-mfa` monta la del panel.

**Sin `'unsafe-inline'`, nunca.** Inertia 3 entrega el estado de la página en un bloque de
datos —`<script data-page="app" type="application/json">…</script>`, no JavaScript en línea—,
que el navegador no ejecuta y que `script-src` por lo tanto no bloquea. Comprobado en navegador
el 2026-09-05: sirviendo la consola con `script-src 'self' 'nonce-…'` a secas —sin
`'unsafe-inline'` y sin `'unsafe-eval'`—, React monta y no hay una sola violación de política,
en claro y en oscuro, escritorio y móvil. El nonce se pasa además a
`createInertiaApp({ nonce })` para los estilos que Inertia inyecta.

Ojo al escribir tests: hasta Inertia 2 el estado iba en un atributo `data-page="{json}"` del
div raíz. En la 3 ese atributo guarda el **ID del div**, y el JSON está dentro del `<script>`.
Un test copiado de una receta vieja falla con «null no es un array» sin decir por qué.

Reglas que el preset implementa y que sus tests vigilan:

- **Eloquent nunca a Inertia.** Cada prop de `Inertia::render` es una Data class de
  `app/Data/Consola`; `PropsDeLaConsolaSonDataTest` intercepta la fachada y falla si no.
- **Props compartidas sin PII.** `HandleInertiaRequests::share()` expone sólo `id`, `nombre` y
  los permisos del **usuario autenticado**, más `app.nombre`, `flash` y `ziggy`. Nunca datos de
  terceros, nunca secretos, nunca `email` por defecto. Todo lo que entra ahí viaja en el HTML
  de cada respuesta: es superficie pública para quien mire el DOM.
  Test: `PropsCompartidasSinPiiTest`.
- **La autorización sigue en el servidor, y cada pantalla exige lo suyo.** Los permisos
  compartidos sólo **esconden UI**: una UI que oculta un botón no autoriza nada, y quien escribe
  la dirección a mano no pasa por ella. Los dos controladores abren con `Gate::authorize()`
  antes de tocar la base:
  - **El escritorio** (`/consola`) exige `access_panel_admin`, el mismo permiso que gobierna la
    puerta de `/admin`. No es un permiso propio de la consola a propósito: quitarle a alguien el
    acceso al sistema tiene que cerrarle las dos puertas de una vez. Antes no exigía nada más
    que sesión, y contaba cuántas cuentas hay, cuántas quedaron sin rol y si esto es producción
    a cualquiera con sesión válida.
  - **La bitácora** (`/consola/eventos`) exige `view_any_activity`, resuelto por
    `App\Policies\ActivityPolicy` — el mismo permiso que el recurso equivalente del panel. La
    consola no inventa un criterio propio de acceso a la bitácora.

  El escritorio es el piso, no el techo: una pantalla con datos exige su permiso **además** del
  de entrada. Test: `ConsolaExigeAutorizacionTest`.
- **Ziggy con lista blanca** (`only`). Exponer todas las rutas equivale a publicar el mapa
  completo del panel a cualquiera que abra la consola.
  Test: `ZiggyListaBlancaTest`.
- **MFA y sesión acotada** en `routes/consola.php`, igual que el panel. Un frontend nuevo es
  una puerta nueva: si se olvida el middleware, es una puerta sin candado.
- **`Cache-Control: no-store` en todas las respuestas de la consola**
  (`SinCacheParaAutenticados`, en el grupo de `routes/consola.php`). Con Inertia el estado
  entero de la pantalla —permisos, recuentos, la bitácora— viaja dentro del HTML, en el bloque
  `<script type="application/json">`. Sin `no-store` ese HTML queda en el disco del equipo y en
  el historial: en el mostrador compartido de una oficina municipal, la persona siguiente pulsa
  «Atrás» después de que la anterior cerró sesión y vuelve a ver la pantalla completa, con sus
  datos, sin pasar por el servidor ni por ningún middleware. No es una fuga hipotética: es el
  navegador repintando lo que ya tenía guardado.
- **`auth.session` en el grupo de la consola** (`AuthenticateSession`). Rotar la contraseña de
  una cuenta comprometida tiene que cerrar **todas** sus sesiones, no sólo las del panel. Sin
  esta línea, el atacante perdía `/admin` y conservaba `/consola` abierta con la misma cookie,
  que es exactamente la mitad del sistema que nadie revisa después de un incidente.
- **CSRF**: Inertia usa el header `X-XSRF-TOKEN` tomado de la cookie. **No se desactiva
  `VerifyCsrfToken`** ni se excluye `/consola`.
- **Cada dependencia npm nueva se audita antes de integrarla** (licencia y mantenimiento), como
  cualquier código de terceros (Ley 17.336).

---

## 6. Accesibilidad

No es cosmética: **WCAG 2.2 AA** por el **Decreto N.º 1/2015 SEGPRES**, que obliga a los sitios
y sistemas del Estado, municipalidades incluidas.

Lo que el preset trae y hay que mantener:

- Componentes sobre primitivas accesibles: **foco visible**, `Esc` que cierra, **trampa de foco**
  en todo lo que se abre (modal, drawer, popover).
- **Área táctil mínima 24×24 px** (44×44 en interfaces de terreno).
- **`prefers-reduced-motion: reduce`** respetado.
- **Errores de formulario en texto**, no sólo en color; label asociado siempre.
- El color nunca es el único portador de información, gráficos incluidos.

**Cómo se verifica** (evidencia, no aserciones):

1. `e2e/tests/consola-accesibilidad.spec.ts` — axe con el conjunto `wcag2a`→`wcag22aa` sobre la
   consola, **en modo claro y en modo oscuro**, enganchado al mismo paso bloqueante de CI que ya
   existe para el panel.
2. **Recorrido completo por teclado**, a mano: entrar, navegar, abrir y cerrar lo que se abre,
   enviar un formulario y leer su error, sin tocar el ratón.
3. Contraste comprobado **por píxel en ambos modos** (4.5:1 texto, 3:1 UI), no a ojo ni desde
   la captura de pantalla.

axe encuentra alrededor de un tercio de los problemas. Los otros dos tercios —foco perdido tras
cerrar un diálogo, orden de tabulación incoherente, un `aria-live` que nunca anuncia— salen del
recorrido a mano.

**Estado del preset (2026-09-06).** No es una promesa: se corrió contra una consola servida de
verdad, con la base sembrada y entrando por la puerta real del panel con segundo factor. Los
siete casos pasan — WCAG 2.2 AA en escritorio y bitácora × claro y oscuro, el recorrido
completo por teclado, y la ventana de detalle que atrapa el foco y lo devuelve al disparador —,
y no hay ninguna violación de axe.

Dos cosas que ese ejercicio dejó aprendidas, y que están en el propio spec:

- **`bypassCSP` es obligatorio en el navegador de pruebas.** axe se inyecta como script en
  línea sin nonce, y la política de la consola —estricta de verdad, sin `'unsafe-inline'`— lo
  bloquea: sin esa bandera no corre ni un caso, y el error parece de Playwright cuando es de la
  política. La cabecera que sirve la aplicación no cambia; `PoliticaDeContenidoDeLaConsolaTest`
  la sigue vigilando del lado del servidor.
- **El usuario de las pruebas necesita los permisos de cada pantalla** (`access_panel_admin` y
  `view_any_activity`); si no, el paso falla con un 403 que se lee como un defecto de
  accesibilidad y apunta al sitio equivocado.

---

## 7. Tiempo real: qué falta y qué cuidar

**El scaffold NO trae Reverb ni Echo.** La consola se entrega sin websockets. Si un sistema los
necesita, hay que añadirlos a mano:

1. `laravel/reverb` en el backend, con su servicio y su configuración de broadcasting.
2. `laravel-echo` + `pusher-js` en el bundle de la consola, envueltos en un hook propio
   (`useChannel`) para que la suscripción se limpie al desmontar el componente. Sin eso quedan
   listeners huérfanos y memory leaks en una pantalla que un operador deja abierta ocho horas.
3. Autorización de canales en `routes/channels.php`: **un canal privado por recurso**, nunca un
   canal global con todo dentro.
4. Añadir el origen de Reverb a `connect-src` en la política CSP de la consola. Si no, el
   navegador bloquea la conexión **sin error visible en la página**.

### Gotcha conocido: la clave de Reverb horneada en el bundle

`REVERB_APP_KEY` se resuelve en **tiempo de build de Vite**, no en tiempo de ejecución. Si la
imagen se construye con la clave de un entorno y se despliega en otro, el bundle queda con la
clave equivocada y **la consola no conecta, sin decir por qué**. Ya pasó en el ecosistema. Dos
consecuencias:

- La clave del cliente es **pública por diseño** (va en el JS). Nunca poner ahí el secreto de la
  aplicación: `REVERB_APP_SECRET` es de servidor y no se expone jamás.
- O se construye la imagen por entorno, o la clave se inyecta en tiempo de ejecución desde el
  HTML (por ejemplo como prop compartida de Inertia) en vez de compilarla dentro del bundle.

Relacionado: si `echo.js` se importa sin clave configurada, el bundle puede romperse entero en
lugar de degradar. Verificar la consola con broadcasting apagado antes de dar nada por bueno.

---

## 8. Versiones verificadas (2026-09-11)

| Paquete | Constraint | Verificado |
|---|---|---|
| `inertiajs/inertia-laravel` | `^3.3` | v3.3.3 requiere `laravel/framework ^11.35\|^12.0\|^13.0`, `php ^8.2.0` (packagist) |
| `tightenco/ziggy` | `^2.6` | v2.6.4, `laravel/framework >=9.0`, `php >=8.1` (packagist) |
| `spatie/laravel-data` | `^4.23` | 4.23.0 (2026-05-08): laravel `^10\|^11\|^12\|^13`, php `^8.1` |
| `spatie/laravel-typescript-transformer` | `^3.3` | 3.3.0 (2026-06-19): `illuminate/contracts ^11\|^12\|^13`, `spatie/typescript-transformer ^3.0` |
| `@inertiajs/react` | `^3.7` | 3.7.0 es `latest` (npm) |
| `@inertiajs/vite` | `^3.7` | 3.7.0, peer `vite ^7.0.0 \|\| ^8.0.0` — el scaffold trae Vite `^8.2.2` (npm) |
| `react` / `react-dom` | `^19.2` | 19.2.8 es `latest` (npm) |
| `@vitejs/plugin-react` | `^6.1` | 6.1.1 es `latest` (npm) |
| `ziggy-js` | `^2.6` | 2.6.4 (npm) |
| `vitest` | `^4.1` | 4.1.11; declara `vite ^6 \|\| ^7 \|\| ^8`. La 5.0.0 rompe la resolución de npm 10.8.2 (`edgesOut` nulo en `#loadPeerSet`): el proyecto no llega a instalar |

Estas versiones **se declaran en una tabla al principio de
`tools/aplicar-frontend-inertia-react.php` y están cubiertas por un test de contrato**, igual
que `VersionDelPaqueteCompartidoTest` cubre el paquete compartido. Al subirlas hay que
verificarlas con context7 o contra el registro (packagist/npm) **en ese momento**, y actualizar
esta tabla en el mismo commit. Una tabla de versiones que nadie revalida es peor que no tenerla.

Nota: la configuración de `spatie/laravel-typescript-transformer` es por service provider (v3);
`--watch` vigila `app/Data`.

Nota: la doc oficial de Inertia hoy es la **v3** (root Blade con `<x-inertia::head />` /
`<x-inertia::app />`, cliente con `@inertiajs/vite` y `createInertiaApp`). La nota original
hablaba de «Inertia 2»; queda corregido acá.

---

## 9. Qué queda fuera a propósito

- **El paquete `muni-ui-react`** (o su equivalente KraftDo). El preset trae los componentes
  mínimos **dentro del proyecto generado**, no como paquete compartido. Se extraerá a paquete
  con el **primer consumidor real**, nunca antes: un paquete de componentes para necesidades
  imaginadas cuesta lo mismo de mantener que uno usado, y no sirve. Y nunca uno compartido entre
  entidades: muni y KraftDo no comparten paquetes.
- **dnd-kit** (arrastrar y soltar). Entra cuando exista un tablero de despacho real, y con
  alternativa por teclado o clic simple desde el primer commit (WCAG 2.2 AA).
- **TanStack Query**. Las props de Inertia con recarga parcial (`only`) cubren el caso normal;
  Query entra cuando haya caché, reintentos o polling que justifiquen la dependencia.
- **SSR de Inertia**. Añade un proceso Node en producción y una fuente nueva de fallos de
  despliegue. La consola es una superficie autenticada: no necesita SEO ni primer pintado
  servido.
- **Reverb y Echo**: ver §7.

**HMR.** `npm run dev` corre `vite build --watch` + el watcher de tipos. El compose no publica
el 5173 y la política no autoriza el origen de Vite; montarlo es publicar el puerto y declarar
ese origen sólo en local, no reabrir `'unsafe-eval'`. El watcher de tipos regenera al vuelo
—verificado el 2026-09-13: una propiedad nueva aparece en `generated.d.ts` en ~2 s—, pero lo
que se commitea sale de `npm run types:generate`: el watcher escribe las uniones en otro orden
que la corrida completa.

**Tipos de rutas y controladores** del transformer: Ziggy con lista blanca ya cubre las rutas.

---

## 10. Qué cubre el análisis estático de ESTE repositorio (y qué no)

Importa porque es fácil leer «PHPStan nivel 8, en verde» y suponer que eso avala también el PHP
de la consola. Hasta esta rama **no lo avalaba**: `phpstan.neon` analizaba sólo `paths: [app]`,
o sea el árbol Laravel base. Las ~700 líneas de PHP del preset y los aplicadores de `tools/`
nunca pasaban por el analizador en este repositorio.

Lo que se analiza ahora (`phpstan.neon`, nivel 8):

| Ruta | Estado |
|---|---|
| `app` | Analizado. Es lo que hereda cada proyecto generado |
| `tools` | Analizado. PHP suelto, sin framework: no necesita nada instalado. Es el código que reescribe archivos ajenos, así que un error ahí rompe la generación entera de un proyecto |
| `presets/inertia-react/archivos/app` | Analizado, con dos silencios acotados (abajo) |
| `presets/inertia-react/archivos/app/Http/Middleware/HandleInertiaRequests.php` | **Fuera** |
| `presets/inertia-react/archivos/{routes,config,database,tests}` | Fuera |
| `tests/` | Fuera |

**Por qué hay silencios.** El PHP del preset importa `inertiajs/inertia-laravel` y
`tightenco/ziggy`, que **este** repositorio no instala —ni debe: el scaffold base es Livewire, y
esos dos paquetes se añaden al `composer.json` del proyecto **destino** cuando se aplica el
preset—. PHPStan los ve como clases inexistentes. Instalarlos como dependencia de desarrollo del
scaffold sólo para que el analizador los viera es peor: mete en el `composer.lock` del árbol base
dos paquetes que ningún proyecto `--frontend=livewire` va a usar jamás.

La solución es un `ignoreErrors` limitado a `class.notFound` de **esas dos clases** y **sólo**
bajo la ruta del preset. El resto del nivel 8 —tipos, nulabilidad, firmas, `array` shapes,
Eloquent— sí corre sobre los controladores de la consola, que es de donde sale el valor.

**Por qué un archivo queda entero fuera.** `HandleInertiaRequests` extiende `Inertia\Middleware`,
y «extends unknown class» es un error que PHPStan declara **no ignorable** (obliga a
`excludePaths`). Sin la clase padre no sabe qué hereda, y todo lo que analizara del cuerpo sería
ruido. Es un archivo, no la carpeta: los dos controladores siguen analizados. Lo que ese archivo
tiene que garantizar —que `share()` no filtre PII— lo cobra `PropsCompartidasSinPiiTest`, no el
análisis estático.

**Lo honesto de decir en voz alta:** en el proyecto **generado** con `--frontend=inertia-react`
los dos paquetes sí están instalados, así que ahí el mismo código se analiza sin silencios. La
cobertura recortada es una limitación del repositorio del scaffold, no del preset.

---

## 11. Skills y convenciones

- Frontend de la consola (React) → skill **`inertia-react-design`**.
- Panel, vistas Blade, Livewire y Filament → skill **`blade-livewire-design`**. Sigue mandando
  en todo lo que no sea `/consola`.
