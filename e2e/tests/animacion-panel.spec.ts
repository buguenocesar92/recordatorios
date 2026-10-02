import { expect, test } from '@playwright/test';

/**
 * Movimiento del panel.
 *
 * Estas pruebas necesitan sesión, así que corren solo en el proyecto que la
 * tiene. Es la única animación propia que queda: las pantallas de acceso del
 * andamio, con su fondo WebGL, se retiraron al unificar el ingreso y la
 * recuperación en el panel.
 */

test.describe('panel', () => {
    test('las tarjetas del escritorio quedan visibles tras animarse', async ({ page }) => {
        await page.goto('/admin');

        const tarjetas = page.locator('.fi-wi-widget');

        // Los widgets los monta Livewire después de la primera respuesta:
        // contarlos de inmediato da cero, y un `skip` sobre ese cero convertía
        // esta prueba en verde sin haber mirado nada.
        await expect(tarjetas.first()).toBeVisible({ timeout: 20_000 });
        const total = await tarjetas.count();
        expect(total, 'el escritorio no montó ninguna tarjeta').toBeGreaterThan(0);

        // Ninguna puede quedar transparente ni encogida.
        for (let i = 0; i < Math.min(total, 8); i++) {
            const t = tarjetas.nth(i);
            await expect(t).toBeVisible();
            await expect.poll(async () =>
                Number(await t.evaluate((el) => getComputedStyle(el).opacity))
            ).toBeGreaterThan(0.95);
            const caja = await t.boundingBox();
            expect(caja!.width, `la tarjeta ${i} quedó sin ancho`).toBeGreaterThan(80);
        }
    });

    test('el conteo de indicadores termina exactamente en el valor del servidor', async ({ page }) => {
        await page.goto('/admin');
        await expect(page.locator('.fi-wi-widget').first()).toBeVisible({ timeout: 20_000 });

        /*
         * Ningún widget del escritorio base es de tipo estadística, así que el
         * conteo no se ejercita solo. Se inyecta el marcado real de Filament
         * para probarlo: lo que importa es que el número FINAL sea idéntico al
         * que mandó el servidor. Un contador que redondea o que se queda a mitad
         * miente sobre un dato oficial, que es mucho peor que no animar.
         */
        const final = await page.evaluate(async () => {
            const caja = document.createElement('div');
            caja.innerHTML = '<span class="fi-wi-stats-overview-stat-value">1.234.567</span>';
            document.body.append(caja);

            const nodo = caja.firstElementChild as HTMLElement;
            document.dispatchEvent(new Event('livewire:navigated'));

            await new Promise((r) => setTimeout(r, 1_200)); // el conteo dura 600 ms
            return { texto: nodo.textContent, marcado: nodo.dataset.contado ?? '(sin marcar)' };
        });

        expect(final.marcado, 'el conteo no llegó a tomar el indicador').toBe('si');
        expect(final.texto, 'el número quedó distinto al que envió el servidor').toBe('1.234.567');
    });

    test('las tarjetas responden al puntero: se elevan y la sombra las acompaña', async ({ page }) => {
        await page.goto('/admin');

        const seccion = page.locator('.fi-wi-widget .fi-section').first();
        await expect(seccion).toBeVisible({ timeout: 20_000 });

        const leer = () => seccion.evaluate((el) => ({
            posicion: getComputedStyle(el).transform,
            sombra: getComputedStyle(el).boxShadow,
        }));

        const reposo = await leer();
        await seccion.hover();
        await expect.poll(async () => (await leer()).posicion).not.toBe(reposo.posicion);

        const encima = await leer();

        /*
         * Las dos cosas, y por separado. El color, el radio y la sombra en
         * reposo los define el tema compartido del panel y los marca «!important»:
         * la primera versión de esta hoja los repetía y no se aplicaba ninguna,
         * pero la tarjeta igual se movía — o sea que comprobar solo el
         * desplazamiento daba verde con la sombra rota.
         */
        expect(encima.posicion, 'la tarjeta no se eleva al pasar el puntero').not.toBe(reposo.posicion);
        expect(encima.sombra, 'la sombra no acompaña a la tarjeta al elevarse').not.toBe(reposo.sombra);
    });

    /**
     * Observa la opacidad del contenido durante una navegación y devuelve si
     * hubo fundido y cómo quedó al final.
     */
    async function navegarObservando(page: any) {
        await page.evaluate(() => {
            (window as any).__op = [];
            const leer = () => {
                const m = document.querySelector('.fi-main');
                if (m) { (window as any).__op.push(Number(getComputedStyle(m).opacity)); }
                requestAnimationFrame(leer);
            };
            leer();
        });

        await page.locator('.fi-sidebar-item a').nth(1).click();
        await page.waitForLoadState('networkidle');
        await page.waitForTimeout(700);

        return page.evaluate(() => {
            const contenido = document.querySelector('.fi-main')!;
            return {
                huboFundido: ((window as any).__op as number[]).some((v) => v < 0.9),
                opacidadFinal: Number(getComputedStyle(contenido).opacity),
                transformFinal: getComputedStyle(contenido).transform,
                ruta: location.pathname,
            };
        });
    }

    test('al cambiar de página el contenido entra, y termina visible', async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'no-preference' });
        await page.goto('/admin');
        await expect(page.locator('.fi-wi-widget').first()).toBeVisible({ timeout: 20_000 });
        await page.waitForTimeout(1_000);

        const r = await navegarObservando(page);

        expect(r.ruta, 'no llegó a navegar').not.toBe('/admin');
        expect(r.huboFundido, 'el contenido se reemplazó de golpe, sin transición').toBeTruthy();

        /*
         * Lo que de verdad importa: cómo QUEDA. Una animación interrumpida a
         * mitad —o un error en el camino— dejaría el panel invisible y sin nada
         * que explicara por qué. Y el transform tiene que volver a «none»: uno
         * residual crea un contenedor de posicionamiento y ancla los elementos
         * fijos, que es como se rompen los modales.
         */
        expect(r.opacidadFinal, 'el contenido quedó a medio aparecer').toBe(1);
        expect(r.transformFinal, 'quedó un transform residual: ancla los elementos fijos').toBe('none');
    });

    test('con menos movimiento el cambio de página no se anima', async ({ page }) => {
        await page.emulateMedia({ reducedMotion: 'reduce' });
        await page.goto('/admin');
        await expect(page.locator('.fi-wi-widget').first()).toBeVisible({ timeout: 20_000 });
        await page.waitForTimeout(1_000);

        const r = await navegarObservando(page);

        expect(r.ruta, 'no llegó a navegar').not.toBe('/admin');
        expect(r.huboFundido, 'se animó pese a que se pidió menos movimiento').toBeFalsy();
        expect(r.opacidadFinal).toBe(1);
    });
});
