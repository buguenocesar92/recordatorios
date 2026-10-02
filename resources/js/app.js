import './bootstrap';

/*
 * La animación NO vive acá. Cada pantalla carga la suya:
 *   - resources/js/panel.js   → el panel Filament (entrada de tarjetas, conteo)
 *
 * Hubo un «acceso.js» (fondo WebGL de las pantallas de acceso propias); se fue
 * con esas pantallas al unificar el ingreso y la recuperación en el panel.
 *
 * Antes este archivo importaba Motion One y lo dejaba colgado de `window` sin
 * que nadie lo usara, en un paquete que además ninguna vista cargaba: era peso
 * muerto disfrazado de infraestructura.
 */
