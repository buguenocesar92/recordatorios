# Acabado visual — que nada parezca arbitrario

> Referencia de la skill `blade-livewire-design`. Leerla al maquetar,
> ajustar espaciado/tipografía/color o revisar por qué algo "se ve barato".
> La estructura y la animación están en `arquitectura-spa.md`.

Principio rector: una interfaz se ve premium cuando **nada parece arbitrario**.
Ningún efecto visual compensa una escala inconsistente. Antes de agregar
cualquier adorno, verificar que espaciado, tipografía, color y elevación salgan
todos de un sistema declarado.

## 1. Espaciado — la causa #1 de que algo se vea barato

- Escala única de 4px: `4 · 8 · 12 · 16 · 24 · 32 · 48 · 64`. Nada fuera de ella.
- Prohibido: 7px, 13px, 18px, 22px. Si un valor no está en la escala, está mal.
- Gap entre widgets del dashboard: 24px. Padding de card: 20px (24px en ≥lg).
- El espacio interno agrupa; el espacio externo separa. El espacio alrededor de
  un grupo siempre mayor que el espacio dentro de él.
- Alineación óptica sobre alineación matemática en iconos junto a texto.

## 2. Tipografía — debe desaparecer dentro del dato

- **Una** familia. Inter por defecto (IBM Plex Sans si se necesita máxima
  distinción de glifos en códigos, RUT e IDs). Segunda familia solo si hay
  razón de marca, nunca en dashboard operativo.
- Máximo 5 tamaños en toda la interfaz: `12 · 13 · 14 · 16 · 24`.
  Si necesitás un sexto, estás resolviendo mal la jerarquía.
- Máximo 3 pesos: 400 · 500 · 600. La jerarquía viene del **peso**, no del tamaño.
- `font-variant-numeric: tabular-nums` global en contenedores de dashboard.
  Sin esto los KPIs bailan al actualizarse y las columnas quedan dentadas.
- Celdas de tabla: 13px / line-height 1.4. Body: 14px / 1.5.
- Números grandes de KPI: 24px, peso 600, tabular. Nunca centrados si hay
  varios en fila: alineados a la izquierda o todos a la derecha.
- Line-height 1.5 en texto corrido, 1.2 en titulares, 1.4 en tablas.
- Etiquetas de eje y leyendas de gráfico: mismo tamaño en todos los widgets.
  Si un gráfico usa 11px y otro 12px, se nota aunque nadie sepa por qué.

## 3. Color — OKLCH como origen

- Definir todos los tokens en `oklch()`. En Tailwind 4, dentro de `@theme`; en
  3.4, como custom properties en `:root` referenciadas desde `theme.extend`.
- Ventaja concreta: dos colores con la misma L se ven igual de brillantes.
  Generar escalas bajando solo L, sin cambiar H, da resultados coherentes.
  En HSL eso produce colores lavados o demasiado vibrantes según el tono.
- **La paleta institucional manda en los municipales** (escudo + franja de 7
  colores). No inventar hex nuevos: los tokens ya existen.
- Un solo acento saturado. Todo lo demás en grises con una pizca del tono de
  marca (no gris neutro puro: un gris levemente teñido cohesiona la interfaz).
- Colores semánticos (éxito, alerta, error) recalibrados individualmente para
  cada modo. Nunca el mismo hex en claro y oscuro.
- El color nunca es el único portador de significado. En gráficos: patrón,
  forma o etiqueta directa además del color.
- Paletas de gráfico: máximo 6 series distinguibles. Si hay más, agrupar en
  "otros" o cambiar de tipo de visualización.

## 4. Modo oscuro — no es invertir

- Base **nunca** `#000`. Gris oscuro en rango `#0E`–`#1A`. El negro puro causa
  halación, fatiga en sesiones largas y destruye el sistema de elevación
  porque no deja espacio "por debajo" para mostrar profundidad.
- Texto **nunca** blanco puro. Off-white tipo `#E0E0E0`.
- **Elevación por luminosidad, no por sombra.** Las sombras son invisibles
  sobre fondo oscuro. Cuatro capas, cada una 3–6% más clara que su padre:
  `base → surface → elevated → overlay`.
- Acentos desaturados 10–20% respecto al modo claro. Un azul vibrante que
  funciona sobre blanco resulta agresivo sobre oscuro.
- Iconos con `fill="currentColor"`. Un `fill="#000000"` desaparece.
- Probar los cuatro estados interactivos (hover, active, focus, disabled) en
  ambos modos: en oscuro se ven muy distintos y es donde más se olvida.
- Bordes: en oscuro, un borde sutil de 1px a `oklch(... / 0.08)` suele leer
  mejor que una sombra.
- **Verificar el modo oscuro por `getComputedStyle`, nunca por captura**: los
  selectores "obvios" del theme de Filament v5 fallan en silencio.

## 5. Sombras y elevación (modo claro)

Mal uso de sombras = segunda causa más rápida de verse barato.

- Tres niveles, no más:
  - `card`: `0 1px 3px rgb(0 0 0 / 0.06), 0 1px 2px rgb(0 0 0 / 0.04)`
  - `raised`: `0 4px 12px rgb(0 0 0 / 0.08)` — hover de card, dropdowns
  - `overlay`: `0 16px 48px rgb(0 0 0 / 0.16)` — modales, drawers
- Sombras difusas y de baja opacidad. Una sombra dura y oscura se ve amateur.
- Nunca sombra + borde grueso + fondo distinto a la vez. Elegir un mecanismo
  de separación por elemento.
- Radios: `4` botones e inputs · `8` base · `12` cards y widgets · `16` modales.
  Un radio distinto por componente rompe la coherencia.

## 6. Micro-interacciones — feedback inmediato, cero jank

- Toda acción del usuario tiene respuesta visible en menos de 100ms, aunque el
  dato tarde. Optimistic UI antes que spinner.
- Hover, focus y active definidos en **todo** elemento interactivo. Un botón
  sin estado active se siente muerto.
- Estados de carga: skeleton con la altura final del contenido, nunca spinner
  suelto ni salto de layout.
- Animar solo `transform` y `opacity`. Nunca `width`, `height`, `top`, `left`:
  provocan reflow y matan el INP.
- Nada de animación en el handler del evento. Responder primero, animar después.
- Un solo foco de movimiento por zona de pantalla. Si el gráfico anima, la card
  no.
- Entradas escalonadas: máximo 8 elementos con stagger de 0.04s; el resto entra
  junto.
- Sutil gana a elaborado. Animaciones simples se leen más premium que complejas.

## 7. Restricción

- Glassmorphism y efectos de profundidad: selectivos, para separar capas o
  destacar un elemento. Nunca sobre texto crítico. La elegancia está en la
  restricción, no en el blur.
- Gradientes: como acento sobre un elemento, jamás como fondo general.
- Si un efecto no comunica jerarquía, estado o relación espacial, sobra.
- Municipal = sobrio e institucional. KraftDo = más cercano a marca comercial.

## 8. Verificación antes de dar por terminado

- [ ] Todo valor de espaciado sale de la escala. Buscar números sueltos en el diff.
- [ ] No más de 5 tamaños y 3 pesos de fuente en la pantalla.
- [ ] `tabular-nums` activo donde haya números.
- [ ] Screenshot en 1440px y 390px, modo claro y oscuro (4 capturas).
- [ ] Los cuatro estados interactivos probados en ambos modos.
- [ ] Contraste verificado: 4.5:1 texto, 3:1 UI, en **ambos** modos.
- [ ] Sin layout shift al cargar (revisar CLS en el trace).
- [ ] `prefers-reduced-motion` probado.
- [ ] Los cuatro estados del widget: cargando, vacío, error, con datos.
- [ ] Datos extremos: texto largo, número de 9 dígitos, 0 filas, 500 filas.

Si el pedido es un ajuste chico, no aplicar la lista completa: criterio
proporcional al tamaño del cambio.
