import { expect, test } from '@playwright/test';

/**
 * Latencia de interacción (INP) del panel.
 *
 * Es la métrica que más se falla, y con Livewire es el riesgo real: cada clic
 * puede terminar en una ida al servidor y en un trozo de página reemplazado. El
 * presupuesto del ecosistema son 200 ms, apuntando a 150.
 *
 * Se mide el PEOR evento de una tanda de interacciones representativas, que en
 * laboratorio es más severo que el percentil 98 que reporta el campo. Si el peor
 * caso pasa, el usuario real no lo nota.
 */

const PRESUPUESTO_MS = 200;

/**
 * Arranca el observador ANTES de interactuar: los eventos no se guardan solos.
 *
 * `buffered: false` a propósito. Con el búfer encendido entran los eventos
 * ocurridos durante el arranque, cuando Livewire y Alpine todavía se están
 * montando, y la medición sale disparada: la primera versión de esta prueba
 * acusaba 488 ms y fallaba, mientras que cada interacción aislada costaba entre
 * 40 y 72 ms. No era el panel: era la medición.
 */
async function observarInteracciones(page: any) {
    await page.evaluate(() => {
        (window as any).__eventos = [];
        new PerformanceObserver((lista) => {
            for (const e of lista.getEntries() as any[]) {
                (window as any).__eventos.push({ nombre: e.name, duracion: e.duration });
            }
        }).observe({ type: 'event', durationThreshold: 0, buffered: false } as any);
    });
}

async function peorInteraccion(page: any) {
    return page.evaluate(() => {
        const evs = (window as any).__eventos as { nombre: string; duracion: number }[];
        if (! evs?.length) {
            return null;
        }
        return evs.reduce((peor, e) => (e.duracion > peor.duracion ? e : peor), evs[0]);
    });
}

test('el panel responde dentro del presupuesto de interacción', async ({ page }) => {
    await page.goto('/admin');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('.fi-wi-widget').first()).toBeVisible({ timeout: 20_000 });

    // Un margen corto para que el panel termine de montarse. Nadie hace clic en
    // el milisegundo cero, y medir ahí acusa el arranque en vez de la respuesta.
    // Es corto a propósito: alargarlo escondería una lentitud real.
    await page.waitForTimeout(1_000);

    await observarInteracciones(page);

    // Interacciones representativas de un día de trabajo en el panel.
    const menu = page.locator('.fi-dropdown-trigger, .fi-user-menu').first();
    if (await menu.count()) {
        await menu.click();
        await page.waitForTimeout(350);
        await page.keyboard.press('Escape');
        await page.waitForTimeout(200);
    }

    const buscador = page.locator('input[type="search"], .fi-global-search-field input').first();
    if (await buscador.count()) {
        await buscador.click();
        await buscador.type('usuario', { delay: 60 });
        await page.waitForTimeout(500);
    }

    // Un enlace de navegación: es lo que más trabajo dispara en Livewire.
    const enlace = page.locator('.fi-sidebar-item a').nth(1);
    if (await enlace.count()) {
        await enlace.click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(400);
    }

    const peor = await peorInteraccion(page);

    // Sin eventos no hay medición: eso es un fallo del test, no un panel veloz.
    expect(peor, 'no se registró ninguna interacción: la medición no probó nada').not.toBeNull();

    console.log(`[interacción] peor evento: ${peor!.nombre} = ${Math.round(peor!.duracion)} ms`);

    expect(
        peor!.duracion,
        `la interacción «${peor!.nombre}» tardó ${Math.round(peor!.duracion)} ms`
    ).toBeLessThan(PRESUPUESTO_MS);
});

test('pasar el puntero por las tarjetas no cuesta trabajo de disposición', async ({ page }) => {
    await page.goto('/admin');
    await expect(page.locator('.fi-wi-widget').first()).toBeVisible({ timeout: 20_000 });

    /*
     * La elevación al pasar el puntero anima «transform» y «box-shadow», que el
     * navegador resuelve sin rehacer la disposición de la página. Si alguien la
     * cambiara por «top», «height» o «margin», cada hover recalcularía el
     * layout y el panel se sentiría pastoso justo al recorrerlo con el mouse.
     */
    const propiedades = await page.locator('.fi-wi-widget .fi-section').first()
        .evaluate((el) => getComputedStyle(el).transitionProperty);

    const prohibidas = ['width', 'height', 'top', 'left', 'margin', 'padding'];
    const culpables = prohibidas.filter((p) => propiedades.includes(p));

    expect(culpables, `se animan propiedades que rehacen la disposición: ${culpables.join(', ')}`)
        .toEqual([]);
    expect(propiedades, 'la tarjeta no anima nada al pasar el puntero').toContain('transform');
});
