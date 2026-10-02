# Arquitectura de componentes, SPA y animación

> Referencia de la skill `blade-livewire-design`. Leerla al diseñar la
> estructura de una vista, activar navegación SPA o animar algo.
> El acabado visual está en `acabado-premium.md`.

## 0. Verificar antes de aplicar

Este documento describe capacidades de **Livewire 4 / Filament 5**. La tabla de
versiones reales por repo está en `SKILL.md`: confirmarla antes de escribir
(`grep -A1 '"name": "livewire/livewire"' composer.lock`).

`personas-graneros` sigue en Livewire 3 / Filament 3: ahí NO existen islands,
`wire:show`, `wire:sort` ni `#[Transition]`.

Tailwind: **8 repos en 3.4, 2 en 4.x**. `@theme` y las utilidades generadas
desde custom properties son de Tailwind 4. En 3.4 el equivalente es declarar
las variables CSS en `:root` y referenciarlas desde `theme.extend`. Nunca
escribir sintaxis de una versión que el repo no tiene instalada.

## 1. Arquitectura de componentes

Orden de preferencia para aislar complejidad. Bajar un nivel solo si el
anterior no alcanza:

1. **Componente Blade** — presentacional, sin estado propio.
2. **Island de Livewire** (`@island()`) — región que se actualiza sola, con
   aislamiento de propiedades computadas: refrescarla ejecuta solo sus queries.
   Es la herramienta correcta para widgets caros dentro de una página grande.
   Funciona sobre componentes de clase, sin refactorizar nada.
3. **Componente Livewire lazy** — cuando el bloque completo puede diferirse.
   Cada componente lazy es una petición HTTP extra: agrupar datos relacionados
   en un solo componente en vez de tener diez lazy disparando en paralelo.
4. **Web Component / custom element** — solo para widgets genuinamente
   complejos y autocontenidos (mapas, editores, visores, canvas). Shadow DOM
   para aislar estilos. Se comunica con Livewire por eventos del DOM, nunca
   accediendo al estado interno del componente padre.
5. **Paquete Composer** — la frontera modular real del proyecto, del lado del
   servidor (`muni-shared`, `muni-ui`, `laravel-kraftdo-ui`).

**NO usar micro-frontends.** Ni module federation, ni bundles independientes,
ni orquestadores de shell. Añaden complejidad de despliegue sin resolver nada
en un monolito Blade con un equipo pequeño. La frontera modular ya está en los
paquetes Composer.

## 2. Modo SPA — `wire:navigate`

- Aplicar `wire:navigate` a **todos** los enlaces internos. El default ya
  prefetchea al presionar el mouse, lo que suele bastar.
- `.hover` solo en navegación principal de baja cardinalidad (sidebar, menú).
  Prefetchea tras 60ms de hover y consume servidor por páginas que quizá nadie
  abra. Nunca en tablas ni listados largos.
- `@persist` para lo que debe sobrevivir la navegación: sidebar, estado de
  filtros, reproductores. Debe vivir en el layout, **fuera** de cualquier
  componente Livewire.
- Redirecciones desde el servidor: `redirect()->to(..., navigate: true)`.
- `data-current` se agrega solo a los enlaces que coinciden con la URL actual:
  estilar el estado activo con eso, sin directivas extra.
- Scroll: se preserva por defecto; usar `wire:navigate:scroll` para
  contenedores propios con scroll interno.
- **JS de página siempre en `livewire:navigated`, nunca en `DOMContentLoaded`.**
  El segundo no vuelve a dispararse tras la primera carga. Es la causa #1 de
  "funciona al recargar pero no al navegar".
- Limpiar listeners y timers en `livewire:navigating` para evitar fugas.
- Transiciones entre páginas con la View Transitions API nativa
  (`#[Transition]` en Livewire 4), no con JS propio.
- **Gotcha de Filament:** con `->spa()` los scripts del cuerpo se duplican y los
  observadores quedan huérfanos (ver `SKILL.md`).

## 3. Reactividad — orden de preferencia

1. **CSS puro** si alcanza (`:has()`, `:checked`, anchor positioning,
   `animation-timeline`). Cero JS es siempre la mejor opción.
2. **Alpine** para estado puramente de UI: abrir/cerrar, tabs, tooltips.
   Alpine viene dentro de Livewire; **nunca instalarlo por separado**, una
   segunda instancia rompe Filament.
3. **Optimistic UI de Livewire** (`wire:show`, `wire:text`, `wire:bind`,
   `$dirty`) para feedback inmediato sin ida al servidor.
4. **Petición Livewire** solo cuando hace falta el servidor de verdad.

Reglas:
- `wire:model.live` solo en búsqueda con debounce. Por defecto, diferido.
- `wire:poll` únicamente en lo que cambia de verdad, con el intervalo más largo
  que el caso tolere. Nunca poll en toda la página.
- Nada de `x-data` con lógica de negocio. Si el objeto Alpine pasa de ~15
  líneas, la lógica va al servidor.

## 4. Tokens en un solo origen

- Colores en `oklch()`. Ver `acabado-premium.md` sección 3.
- **Filament, los gráficos y el JS de animación leen las mismas variables.**
  Ningún valor de color o duración hardcodeado en JS: `getComputedStyle` sobre
  el token, o pasarlo por atributo `data-` desde Blade.
- Modo oscuro con tokens semánticos que resuelven a valores distintos por modo.
  Nunca duplicar componentes por tema.
- Nada de `@apply` en componentes nuevos. Utilidades en el markup, o un
  componente Blade si se repite.

## 5. Animación — por contexto

- **Sistemas municipales** → **Motion One** (`motion@13.1.0`, instalado en los
  9 Laravel). Es lo correcto para dashboards operativos: la animación debe ser
  invisible.
- **KraftDo** → **GSAP**, para timelines encadenadas, SplitText y efectos de
  marca. **Hoy no está en ningún `package.json`**: instalarlo solo en el repo
  de KraftDo que lo necesite, nunca en los municipales. Consultar las skills
  `gsap-skills` para la API; ignorar `gsap-react` y `gsap-frameworks`.
- **Nunca las dos librerías en el mismo bundle.**

Qué usar para cada caso, en orden:

| Caso | Herramienta correcta |
|---|---|
| Mostrar/ocultar modal, dropdown, panel | `x-transition` de Alpine |
| Entrada al hacer scroll | `animation-timeline: scroll()`/`view()` nativo dentro de `@supports`; `inView()` de Motion solo si no alcanza |
| Reordenar / arrastrar widgets | `wire:sort` (core de Livewire 4) |
| Transición entre páginas | View Transitions API nativa / `#[Transition]` |
| Secuencias encadenadas (KPI + gráfico) | `animate()` + `stagger()` de Motion; Timeline de GSAP en KraftDo |
| Texto animado de marca | GSAP SplitText, solo en KraftDo, nunca en dashboard operativo |

Reglas duras:
- Toda animación respeta `prefers-reduced-motion: reduce`. Envolver en guard.
- Animar solo `transform` y `opacity`. Nunca `width`, `height`, `top`, `left`.
- **Nunca animar dentro del handler del evento.** Responder primero, animar
  después. Es la causa directa de INP malo.
- Matar las animaciones al destruir el componente Livewire y en
  `livewire:navigating`. Una animación viva sobre un nodo que Livewire ya
  reemplazó es una fuga silenciosa.
- Las duraciones salen de los tokens CSS, no de números sueltos en el JS.
- Gotcha de Motion: la opción es `ease`, **no** `easing`.

## 6. Iconos

- **Un solo set.** En proyectos Filament el default es Heroicons v2 vía Blade
  Icons: no montar un segundo set encima. (Gotcha conocido: `x-kd-icon` de
  kraftdo-nfc-v2 choca con blade-icons.)
- Escala fija: 16px inline en texto · 20px en botones y campos · 24px en
  navegación y headers de card.
- Siempre `currentColor`. Nunca hardcodear el fill (desaparece en modo oscuro).
- Decorativo → `aria-hidden="true"`.
  Icono que **es** la acción → `aria-label` que describe la acción, no el
  dibujo: "Eliminar factura", no "icono de basurero".
- Nunca un icono como único indicador de estado: siempre con texto o tooltip
  accesible por teclado.
- Iconos animados solo como feedback de acción (guardar, copiar, éxito), jamás
  decorativos en bucle.

## 7. Gráficos

- **Chart.js** para el caso normal: es el que Filament ya publica en
  `public/js/filament/`, así que no agrega dependencia ni peso nuevo.
  **ECharts** cuando hay volumen, tiempo real o tipos avanzados.
- **ApexCharts: NO.** Desde la 7.0.0 (2026-08-25) tiene licencia dual y solo es
  gratis bajo USD 2M anuales de facturación — una municipalidad supera ese
  umbral. Si aparece en un repo heredado, fijar `apexcharts@6.10.0` (la última
  MIT) y planificar la salida a Chart.js o ECharts.
- Altura o `aspect-ratio` declarados desde el primer render. Sin esto el CLS
  se dispara.
- Colores desde los tokens, verificados en contraste y con patrón o etiqueta
  además del color.
- Máximo 6 series distinguibles.
- Destruir la instancia del gráfico en el cleanup del componente y en
  `livewire:navigating`, o se acumulan canvas huérfanos al navegar.

## 8. Rendimiento — cuellos reales

- LCP < 2.5s · INP < 200ms (apuntar a 150) · CLS < 0.1, medidos en campo.
- INP es el que más se falla y en Livewire es el riesgo principal: partir
  tareas largas, ceder al hilo principal, no colgar trabajo pesado del handler.
- Eager loading por defecto. Revisar N+1 antes de dar algo por listo.
- Agregaciones pesadas cacheadas en Redis, no calculadas al vuelo.
- Reservar altura en todo contenedor de gráfico, tabla e imagen.
