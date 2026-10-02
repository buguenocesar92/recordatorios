<?php

/**
 * Primitivos de color por entidad. Los nombres son de ROL, no de color: es lo
 * que permite que la misma hoja sirva a las entidades sin que ninguna herede
 * la identidad de la otra («nunca mover código, datos ni configuración entre
 * entidades», CLAUDE.md del ecosistema).
 *
 * ÚNICA fuente de las paletas del scaffold. La consumen:
 *   - tools/usar-paquete-compartido.php → resources/css/sitio.css (landing)
 *   - tools/aplicar-frontend-inertia-react.php → consola/portal Inertia (rama aparte)
 *
 * Contrato: 20 tokens por paleta, todos obligatorios, en este orden. Las
 * relaciones de contraste WCAG 2.2 AA de las tres las recalcula
 * `PaletaDelSitioSigueLaEntidadTest` en cada corrida: cambiar un valor «porque
 * se ve mejor» se detecta ahí.
 *
 * `neutra` es lo que el scaffold base trae escrito: grises sin hue, que no son
 * de nadie. Un proyecto generado sin `--shared` (no existe ese caso hoy) se
 * vería así.
 */
return [
    // Paleta oficial de la Municipalidad de Graneros, verbatim de
    // `muni-ui-filament.css`: la landing y el panel comparten literalmente los
    // mismos colores. Si el paquete cambia la paleta, se actualiza acá.
    'muni' => [
        '--marca-acento' => '#355a63',
        '--marca-acento-profundo' => '#0d3a44',
        '--marca-acento-claro' => '#7ccbe1',
        '--marca-realce' => '#adcd60',
        '--marca-realce-claro' => '#c3e07a',
        '--marca-aviso' => '#f5c542',
        '--marca-progreso' => '#c76421',
        '--marca-tinta' => '#20302f',
        '--marca-tinta-profunda' => '#08252b',
        '--marca-atenuado' => '#5c6f6d',
        '--marca-fondo' => '#f8f5ec',
        '--marca-papel' => '#f6f3ea',
        '--marca-papel-2' => '#efece1',
        '--marca-borde' => '#e2ddce',
        '--marca-borde-2' => '#cfc7b3',
        '--marca-barra-texto' => '#cfe3e1',
        '--marca-fondo-oscuro' => '#081418',
        '--marca-superficie-oscura' => '#0f2025',
        '--marca-superficie-oscura-2' => '#0d1c20',
        '--marca-superficie-oscura-3' => '#142a30',
    ],
    // PENDIENTE DE LA IDENTIDAD DE KRAFTDO. Esto NO es la marca de KraftDo:
    // es una paleta provisional de grises azulados que cumple los mínimos WCAG
    // del contrato y no toma prestado nada de nadie. Se reemplaza cuando
    // KraftDo tenga su manual de identidad.
    'kraftdo' => [
        '--marca-acento' => '#3c4d5c',
        '--marca-acento-profundo' => '#1b2429',
        '--marca-acento-claro' => '#a3c2d6',
        '--marca-realce' => '#9dc37c',
        '--marca-realce-claro' => '#c2dfa6',
        '--marca-aviso' => '#f0c674',
        '--marca-progreso' => '#c2662f',
        '--marca-tinta' => '#1f2529',
        '--marca-tinta-profunda' => '#101619',
        '--marca-atenuado' => '#5b666e',
        '--marca-fondo' => '#f4f5f6',
        '--marca-papel' => '#eff1f3',
        '--marca-papel-2' => '#e7eaed',
        '--marca-borde' => '#d8dde1',
        '--marca-borde-2' => '#bcc4ca',
        '--marca-barra-texto' => '#d5dde2',
        '--marca-fondo-oscuro' => '#0c1013',
        '--marca-superficie-oscura' => '#161c20',
        '--marca-superficie-oscura-2' => '#11161a',
        '--marca-superficie-oscura-3' => '#1e262b',
    ],
    // Grises sin tono. Es lo que trae el scaffold base y lo que se vería si
    // ninguna entidad reemplazara el bloque: reconocible como «sin pintar».
    'neutra' => [
        '--marca-acento' => '#4b5563',
        '--marca-acento-profundo' => '#1f2937',
        '--marca-acento-claro' => '#9ca3af',
        '--marca-realce' => '#9dc37c',
        '--marca-realce-claro' => '#c2dfa6',
        '--marca-aviso' => '#f0c674',
        '--marca-progreso' => '#b45309',
        '--marca-tinta' => '#1f2937',
        '--marca-tinta-profunda' => '#111827',
        '--marca-atenuado' => '#4b5563',
        '--marca-fondo' => '#f5f6f7',
        '--marca-papel' => '#eef0f2',
        '--marca-papel-2' => '#e5e7eb',
        '--marca-borde' => '#d1d5db',
        '--marca-borde-2' => '#9ca3af',
        '--marca-barra-texto' => '#d1d5db',
        '--marca-fondo-oscuro' => '#0b0f14',
        '--marca-superficie-oscura' => '#151a20',
        '--marca-superficie-oscura-2' => '#10151a',
        '--marca-superficie-oscura-3' => '#1b222a',
    ],
];
