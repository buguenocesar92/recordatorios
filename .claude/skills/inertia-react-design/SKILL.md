---
name: inertia-react-design
description: Convenciones de diseño, accesibilidad y seguridad para la consola de operador Inertia 3 + React 19 + TypeScript + Tailwind, SÓLO en proyectos generados con `./scaffold new … --frontend=inertia-react`. Usar al crear o modificar páginas y componentes bajo `/consola` (`resources/js/consola/**`), al elegir una librería React (tablas, formularios, drag & drop, mapas, gráficos), al portar un componente de muni-ui a React, o al revisar foco, teclado, contraste, modo oscuro o animación de la consola. Para vistas Blade, componentes Livewire, layouts y paneles Filament manda la skill `blade-livewire-design`, no esta.
---

# Consola Inertia + React — convenciones

## Antes de nada: ¿esto aplica?

Sólo si el proyecto se generó con `--frontend=inertia-react` **y** estás tocando
`/consola`. Comprobalo: `ls resources/js/consola` y `grep -n "consola" routes/web.php
bootstrap/app.php`. Si no existe, el frontend es Livewire y esta skill no manda.

**Todo lo que no sea `/consola` sigue siendo Blade + Livewire 4 + Alpine + Filament 5**, con la
skill `blade-livewire-design`. El panel administrativo, Shield, ARCOP y muni-ui no se tocan
desde acá. Vue, Svelte y jQuery siguen vetados sin excepción.

Antes de proponer React en un sistema que hoy no lo tiene: leé
`docs/FRONTEND-INERTIA-REACT.md` §1. El criterio son cuatro señales **medidas**; si ninguna se
cumple, la respuesta es quedarse en Livewire.

## Stack y versiones

Inertia **3** (no 2: root Blade con `<x-inertia::head />` / `<x-inertia::app />`, cliente con
`@inertiajs/vite` y `createInertiaApp`) · React 19 · TypeScript · Tailwind · Vitest.

Las versiones exactas están en la tabla al principio de
`tools/aplicar-frontend-inertia-react.php`, cubiertas por un test de contrato. **No las
adivines ni las subas de memoria**: verificá con context7 o el registro y actualizá la tabla de
`docs/FRONTEND-INERTIA-REACT.md` en el mismo commit.

## Imports: alias `@/`

```ts
import { Boton } from '@/consola/components/ui/Boton';   // ✓
import { Boton } from '../components/ui/Boton';          // ✗ (se rompe al mover el archivo)
```

`@/` → `resources/js`, declarado en `tsconfig.json` (tsc) y `vitest.config.ts` (Vitest);
en Vite lo pone `laravel-vite-plugin` por defecto, sin declararlo en `vite.config.js`
(`TsconfigYViteCoincidenTest` lo exige así: declararlo a mano ahí obliga a
`import ... from 'node:url'`, que el candado del ecosistema rechaza). Dentro de la misma
carpeta, `./` está bien.

## Tipos: se generan desde PHP

Los datos que viajan a React son Data classes (`app/Data/Consola/`). `npm run types:generate`
escribe `resources/js/consola/types/generated.d.ts` (namespaces globales: `App.Data.Consola.EventoData`,
sin import). **Nunca lo edites**: cambiá la Data y regenerá. `npm run types:check` antes de
mergear; `TiposGeneradosAlDiaTest` lo repite en la suite. Lo que no sale de PHP (props
compartidas, Ziggy) vive en `types/inertia.d.ts`. Lo que se commitea es el archivo de
`npm run types:generate`, no el que deja el watcher de `npm run dev`: el orden de las
uniones difiere entre uno y otro, y el candado lo detecta.

## Contratos del preset (no inventar otros)

| Cosa | Valor |
|---|---|
| Prefijo y nombres de ruta | `/consola`, `consola.escritorio`, `consola.eventos` |
| Vista raíz | `consola` → `resources/views/consola.blade.php` |
| Entradas de Vite | `resources/css/consola.css`, `resources/js/consola/app.tsx` |
| Páginas | `./pages/{name}.tsx`; el controlador hace `Inertia::render('Escritorio')` |
| Tipos | `types/generated.d.ts` (generado) y `types/inertia.d.ts` (a mano) |
| Data classes | `app/Data/Consola/` — lo único que se pasa a `Inertia::render` |
| Props compartidas | `app.nombre`, `auth.usuario` (`id`, `nombre`, `permisos[]`), `flash.exito`, `flash.error`, `ziggy` |
| Ziggy | `only: ['consola.escritorio', 'consola.eventos', 'salir']` |

## Seguridad al escribir componentes

- **Nada de PII ni secretos en props compartidas.** Lo que entra en
  `HandleInertiaRequests::share()` viaja en el HTML de **cada** respuesta. Datos de terceros van
  en la prop de la página que los necesita, y sólo si la policy ya autorizó.
- **`permisos[]` esconde UI; no autoriza.** Cada acción vuelve al servidor y pasa por
  policy/Gate. Un botón oculto no es un control de acceso.
- **CSP sin `'unsafe-inline'`.** La consola no lo necesita —Inertia 3 entrega el estado en un
  bloque `<script type="application/json">` que el navegador no ejecuta— y no lo tiene;
  verificado en navegador. No metas `<script>` inline, ni `style="…"` generado por JS que
  dependa de estilos inline permitidos, ni librerías que inyecten `<script>` en runtime.
  `'unsafe-eval'` existe **sólo en local** por React Refresh.
- **`dangerouslySetInnerHTML` no se usa.** Si hace falta HTML del servidor, sanitizá en el
  servidor y justificá el caso por escrito.
- **Toda dependencia npm nueva se audita** (licencia, mantenimiento, peso) antes de entrar.

## Accesibilidad — WCAG 2.2 AA, no negociable

Decreto N.º 1/2015 SEGPRES: obligatorio para sistemas del Estado, municipalidades incluidas.

- **Foco visible siempre.** No borres el outline sin poner un ring equivalente; usá
  `:focus-visible`. El foco no puede quedar tapado por headers fijos.
- **Todo lo que se abre atrapa el foco y cierra con `Esc`**, y **devuelve el foco al elemento
  que lo abrió** al cerrarse. Es el fallo que axe no ve y el recorrido por teclado sí.
- **Área táctil mínima 24×24 px** (44×44 en interfaces de terreno).
- **Contraste 4.5:1 en texto y 3:1 en UI, verificado en los dos modos**, por píxel, no a ojo.
- **`prefers-reduced-motion: reduce`**: sin movimiento ni transformaciones; opacidad ≤100 ms.
- **Errores de formulario en texto**, asociados al campo (`aria-describedby`), no sólo en rojo.
  Los errores de validación llegan por Inertia (`errors`): píntalos, no los tragues.
- **El color nunca es el único portador de información**, gráficos incluidos.
- **Drag & drop siempre con alternativa por teclado o clic simple.**
- Estados de carga con **skeleton que respeta la altura final**, no spinner: cero layout shift.

Verificación antes de dar algo por terminado: axe (`wcag2a`→`wcag22aa`) en **claro y oscuro** vía
`e2e/tests/consola-accesibilidad.spec.ts`, **más** un recorrido completo por teclado a mano.
Captura en 1440 px y 390 px. Modo oscuro comprobado por `getComputedStyle`, nunca por captura.

## Tokens: se consumen, no se inventan

La paleta institucional vive como variables CSS en `laravel-muni-ui` (`--muni-*` / `--mg-*`);
KraftDo usa su equivalente. `resources/css/consola.css` las consume desde Tailwind para que las
dos mitades del sistema —panel Filament y consola React— se vean idénticas en claro y oscuro.

**No hardcodees hex en TSX.** Si falta un tono, se agrega **en el paquete**, no en la consola.
Duraciones, radios, sombras y espaciado: los mismos de `blade-livewire-design`
(`references/acabado-premium.md`).

## Equivalencias muni-ui → React

Nombres para no partir de cero. **Versiones sin fijar a propósito**: verificar cada una con
context7 al momento de usarla.

| Necesidad | En muni-ui (Blade) | En la consola (React) | Nota |
|---|---|---|---|
| Primitivas accesibles (modal, drawer, dropdown, popover, tabs, accordion, segmented, otp) | `modal`, `drawer`, `dropdown`, `accordion`, `segmented`, `otp-input` | **shadcn/ui** sobre **Radix UI** | Accesible de base; igual hay que auditar foco y `Esc`. |
| Paleta de comandos | `command-palette` | **cmdk** (el `Command` de shadcn) | |
| Tablas grandes, orden, filtros | `data-table`, `filter-bar`, `pagination` | **TanStack Table** (+ **TanStack Virtual**) | Virtualizar sobre ~200 filas. |
| Datos del servidor | Livewire | Props de Inertia con recarga parcial (`only`) | TanStack Query sólo si hay caché/reintentos/polling que lo justifiquen. |
| Formularios y validación | `field`, `input`, Filament Forms | `useForm` de Inertia; **react-hook-form** + **zod** si se complica | Los errores del servidor se pintan en texto. |
| Arrastrar y soltar | — | **dnd-kit** | Siempre con alternativa por teclado o clic simple. |
| Mapa en vivo | `mapa.js` con **maplibre-gl** | **maplibre-gl** + envoltorio **react-map-gl** | El motor no cambia; cambia quién gestiona el estado de los marcadores. |
| Tiempo real | `echo.js` + Reverb | **laravel-echo** con un hook `useChannel` propio | **No viene en el scaffold.** Ver §7 de `docs/FRONTEND-INERTIA-REACT.md`: la clave de Reverb se hornea en el bundle. |
| Rutas con nombre | `route()` en Blade | **Ziggy** (`ziggy-js`) | Con lista blanca. Nunca publicar el mapa de rutas del panel. |
| Gráficos | `chart-bar`, `chart-donut` | **Recharts** o **ECharts** | Altura o `aspect-ratio` desde el primer render. Respetar la decisión de licencias del ecosistema. |
| Iconos | Heroicons vía Blade | **@heroicons/react** o **lucide-react** | Elegir uno y no mezclar. |
| Animación | **motion** (vanilla) | **motion/react** (mismo paquete) | Ver trampas abajo. |
| Tests de componentes | Pest + `Livewire::test` | **Vitest** + **Testing Library** | Los E2E siguen en Playwright, con axe. |
| Tipos back↔front | — | **spatie/laravel-data** + **spatie/typescript-transformer** | Genera los tipos TS desde los DTO de PHP. |
| Shell y marca institucional (`gob-bar`, `gob-footer`, `gob-escudo`, `gob-stripe`, `app-shell`) | muni-ui | Portar a mano | No existe en ningún catálogo: es el grueso del trabajo propio. |

Se portan **los 10–12 componentes que la consola use**, dentro del proyecto. El paquete
`muni-ui-react` se extrae con el primer consumidor real, y **nunca compartido entre entidades**.

## Trampas de animación ya medidas (valen igual en React)

1. **Nunca animar una cadena `transform` hacia `'none'`.** Motion la interpola como
   `matrix(0,0,0,0,0,0)`: el elemento queda de 0×0 y desaparece **sin un error en consola**.
   Van propiedades sueltas: `y`, `x`, `scale`.
2. **No animar `opacity` desde 0 en el elemento más grande de la pantalla.** Lo que empieza
   invisible no cuenta como pintado y el LCP se va con la animación (medido: +536 ms). Los
   elementos chicos sí pueden desvanecerse.
3. Un solo foco de movimiento por pantalla. Nada por encima de 600 ms en UI de trabajo diario.

## Antes de dar por terminado

- [ ] `npm run types:check` y `npm run test:js` en verde
- [ ] `npm run build` en verde (es donde aparecen los imports con alias)
- [ ] Captura en 1440 px y 390 px, claro y oscuro
- [ ] Estados cubiertos: carga, vacío, error, con datos y datos extremos
- [ ] Recorrido completo por teclado, incluido cerrar con `Esc` y recuperar el foco
- [ ] axe sin violaciones en los dos modos
- [ ] Sin layout shift al cargar

Criterio proporcional: para un ajuste chico no se aplica la lista entera.
