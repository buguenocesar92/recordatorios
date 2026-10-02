---
paths:
  - 'package.json'
  - 'vite.config.js'
  - 'resources/js/**'
  - 'app/Providers/Filament/**'
---

# Frontend, gráficos y assets

## ApexCharts está PROHIBIDO

ApexCharts 7 pasó a licencia dual: el uso gratuito solo cubre organizaciones por debajo
de USD 2 M anuales, umbral que una municipalidad supera. No lo instales ni lo propongas,
tampoco vía un wrapper de Filament que lo arrastre.

Si hace falta un gráfico: **Chart.js**, que Filament ya trae (`ChartWidget`), o
**ECharts** si Chart.js se queda corto.

## El worker de una librería se importa con `?worker&url`, no con `?url`

MapLibre calcula la ruta de su worker con `new URL('./…-worker.mjs', import.meta.url)`,
relativa al bundle; tras empaquetar ese archivo no existe → 404. El arreglo obvio
(`?url` + `setWorkerUrl()`) **cambia un 404 por otro**: `?url` copia el archivo tal cual
sin resolver sus propios imports, y el segundo 404 ocurre dentro del worker, donde no se
ve. `?worker&url` lo hace pasar por el empaquetador como punto de entrada propio.

Síntoma engañoso: **el mapa funciona igual**, porque la librería se cae a hacerlo todo
en el hilo principal. Se detecta con `page.workers()` en Playwright, no mirando la
pantalla.

## Tras `npm run build`, Octane sigue sirviendo el manifest viejo

Laravel memoiza el manifest de Vite en una estática que sobrevive entre peticiones: la
página pide un asset con el hash anterior y da 404. `docker compose restart app` (o
`octane:reload`, ver `.ai/rules/octane.md`) **antes** de concluir que el fallo es del
código recién escrito.

## Con `->spa()` activado, tus `<script>` se re-evalúan

Livewire re-evalúa todos los `<script>` del `<body>` en cada navegación y los listeners
sobre `document` no se limpian solos: se duplican visita tras visita (degradación lenta
que nadie asocia al cambio). `data-navigate-once` lo evita — y **no basta**: al navegar,
Livewire reemplaza el cuerpo, así que un `MutationObserver` registrado sobre
`document.body` queda vigilando un nodo desechado y deja de funcionar desde la segunda
pantalla. Van las dos cosas: `data-navigate-once` **y** re-observar en
`document.addEventListener('livewire:navigated', …)`, desconectando el observador previo.

Los tests PHP no ven nada de esto: pasan en verde con el panel roto.

## `->slideOver()` no abre modal si el recurso tiene página crear/editar

`Filament\Resources\Pages\Page::getDefaultActionUrl()` le asigna URL a la acción cuando
`hasPage('create')` es verdadero, y el botón **navega**. `->url(null)` no lo evita. Para
tener modal real, deja el recurso «simple»: `getPages()` solo con `'index'` y sin las
clases `Create*`/`Edit*`.
