/**
 * Movimiento del panel.
 *
 * Un panel municipal se usa muchas horas al día: el movimiento acá no está para
 * lucirse, está para que se entienda qué cambió. Por eso son solo dos cosas —
 * las tarjetas entran escalonadas, y los números cuentan hasta su valor— y nada
 * de eso pasa de 600 ms.
 *
 * Todo lo que anima está en pantalla ANTES de animarse. El estado inicial se
 * aplica desde JavaScript, nunca desde la hoja de estilos, así un error de JS
 * deja el panel feo pero completo, jamás en blanco.
 */

import { animate, stagger } from 'motion';

const menosMovimiento = () =>
    window.matchMedia?.('(prefers-reduced-motion: reduce)').matches ?? false;

/** Duraciones del ecosistema, en segundos (ver la skill blade-livewire-design). */
const RAPIDO = 0.4;
const DELIBERADO = 0.6;
const ENTRADA = [0.16, 1, 0.3, 1];

/**
 * Entrada escalonada de las tarjetas del escritorio.
 *
 * El escalonamiento se corta en ocho: más que eso y la última tarjeta aparece
 * medio segundo tarde, que en un panel de uso diario se siente lento, no fino.
 */
function animarTarjetas () {
    const tarjetas = Array.from(document.querySelectorAll('.fi-wi-widget:not([data-animado])'));

    if (! tarjetas.length) {
        return;
    }

    tarjetas.forEach((t) => { t.dataset.animado = 'si'; });

    /*
     * Se anima el desplazamiento y NO la opacidad. No es un capricho: partir de
     * opacity 0 significa que el navegador no cuenta ese contenido como pintado,
     * y el LCP del escritorio se va con la animación.
     *
     * Medido en este repo (escritorio, tres tarjetas):
     *   - con opacidad:        1424 ms
     *   - solo desplazamiento: 1088 ms
     *   - sin animar:           848 ms
     *
     * O sea: el desvanecido costaba 536 ms de LCP y el desplazamiento cuesta
     * 240 ms, con el mismo efecto a la vista. Los 240 ms que quedan son el
     * precio del movimiento, aceptado a sabiendas: el presupuesto de la prueba
     * de rendimiento son 2500 ms. Si algún día hay que recortarlos, lo que se
     * saca es la animación, no el presupuesto.
     *
     * Además, y/scale sueltos y nunca una cadena `transform`: hacia `none`
     * Motion interpola a matrix(0,0,0,0,0,0) y el elemento desaparece.
     */
    animate(tarjetas.slice(0, 8),
        { y: [10, 0] },
        { duration: RAPIDO, delay: stagger(0.04), ease: ENTRADA });
}

/**
 * Los números de los indicadores cuentan hasta su valor.
 *
 * Respeta el formato original (miles, símbolos, sufijos): se anima solo el
 * número y se vuelven a poner los separadores en español. Si el texto no es un
 * número —«Sin datos», un guion, un porcentaje raro— no se toca.
 */
function animarNumeros () {
    const nodos = document.querySelectorAll('.fi-wi-stats-overview-stat-value:not([data-contado])');

    nodos.forEach((nodo) => {
        nodo.dataset.contado = 'si';

        const original = nodo.textContent.trim();
        const crudo = original.replace(/[.\s]/g, '').replace(',', '.');
        const destino = Number.parseFloat(crudo.replace(/[^\d.-]/g, ''));

        // Sin número, o demasiado chico para que contar signifique algo.
        if (! Number.isFinite(destino) || Math.abs(destino) < 2) {
            return;
        }

        const decimales = (crudo.split('.')[1] ?? '').length;
        const prefijo = original.match(/^[^\d-]*/)?.[0] ?? '';
        const sufijo = original.match(/[^\d]*$/)?.[0] ?? '';
        const formato = new Intl.NumberFormat('es-CL', {
            minimumFractionDigits: decimales,
            maximumFractionDigits: decimales,
        });

        animate(0, destino, {
            duration: DELIBERADO,
            ease: ENTRADA,
            onUpdate: (valor) => {
                nodo.textContent = prefijo + formato.format(valor) + sufijo;
            },
            // Pase lo que pase, el texto final es el que vino del servidor.
            onComplete: () => { nodo.textContent = original; },
        });
    });
}

/**
 * Entrada del contenido al cambiar de página.
 *
 * El panel navega sin recargar, así que sin esto el contenido se reemplaza de
 * golpe y cuesta notar qué cambió — sobre todo entre dos listados parecidos.
 *
 * No se usan View Transitions: se intentó y no se disparan. `wire:transition`
 * es del morph de Livewire, no de la navegación, y `document.startViewTransition`
 * no llega a llamarse (comprobado interceptándolo durante una navegación real).
 *
 * Solo la zona de contenido: la barra lateral no se toca, porque no cambió, y
 * animar el menú entero en cada clic cansa en una herramienta de uso diario.
 */
function animarCambioDePagina () {
    const contenido = document.querySelector('.fi-main');

    if (! contenido) {
        return;
    }

    animate(contenido,
        { opacity: [0, 1], y: [6, 0] },
        { duration: 0.22, ease: ENTRADA });
}

function animarPanel () {
    if (menosMovimiento()) {
        return;
    }

    animarTarjetas();
    animarNumeros();
}

/*
 * Con ->spa() activado no hay recarga entre páginas: el script se evalúa una
 * sola vez y hay que volver a animar en cada navegación. Las marcas
 * data-animado / data-contado son lo que impide animar dos veces lo mismo
 * cuando Livewire re-renderiza un trozo de la página.
 */
document.addEventListener('DOMContentLoaded', animarPanel);
document.addEventListener('livewire:navigated', () => {
    // La entrada del contenido va solo al NAVEGAR. En la primera carga el
    // contenido ya está pintado, y desvanecerlo ahí retrasaría el LCP —que es
    // exactamente el error que costó 536 ms en las tarjetas.
    if (! menosMovimiento()) {
        animarCambioDePagina();
    }
    animarPanel();
});
