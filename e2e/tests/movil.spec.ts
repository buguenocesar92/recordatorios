import { expect, test, devices } from '@playwright/test';

/**
 * El panel en un teléfono.
 *
 * Hay funcionarios en terreno y vecinos que entran desde el celular, así que el
 * panel angosto no es un caso raro: es el caso de mucha gente. Lo que se
 * comprueba acá es lo que se rompe de verdad al angostar —desbordes
 * horizontales y botones imposibles de acertar—, no que «se vea bien».
 */

test.use({ ...devices['Pixel 7'] });

const PANTALLAS = ['/', '/admin', '/admin/usuarios', '/admin/usuarios/create'];

for (const ruta of PANTALLAS) {
  test(`${ruta}: no se desborda a lo ancho`, async ({ page }) => {
    await page.goto(ruta);
    await page.waitForLoadState('networkidle');

    const desborde = await page.evaluate(() => ({
      documento: document.documentElement.scrollWidth,
      ventana: window.innerWidth,
      // Quién se sale, si alguien se sale: sin esto el fallo dice «hay
      // desborde» y toca buscarlo a mano.
      culpables: Array.from(document.querySelectorAll('*'))
        .filter((el) => el.getBoundingClientRect().right > window.innerWidth + 1)
        .slice(0, 3)
        .map((el) => `${el.tagName.toLowerCase()}.${(el.className || '').toString().slice(0, 40)}`),
    }));

    expect(
      desborde.documento,
      `La página mide ${desborde.documento}px en una ventana de ${desborde.ventana}px. Se salen: ${desborde.culpables.join(' | ')}`,
    ).toBeLessThanOrEqual(desborde.ventana + 1);
  });
}

for (const ruta of ['/', '/admin']) {
  test(`${ruta}: los botones se pueden acertar con el dedo`, async ({ page }) => {
    // WCAG 2.2 pide 24×24 px como mínimo. En terreno, con guantes o al sol, un
    // botón de 20px se falla más veces de las que se acierta.
    await page.goto(ruta);
    await page.waitForLoadState('networkidle');

    const pequenos = await page.evaluate(() => {
      const MINIMO = 24;

      return Array.from(document.querySelectorAll('button, a[href], [role="button"]'))
        .filter((el) => {
          const r = el.getBoundingClientRect();

          // Lo que está oculto para la vista no se toca con el dedo. El enlace de
          // «saltar al contenido» mide 1×1 a propósito hasta que recibe el foco:
          // exigirle 24px sería exigirle que se vea siempre, que es lo contrario
          // de lo que hace. Se mide cuando aparece, no mientras está guardado.
          const estilo = getComputedStyle(el);
          const oculto = el.className.toString().includes('sr-only')
            || estilo.clip === 'rect(0px, 0px, 0px, 0px)'
            || estilo.visibility === 'hidden';

          if (oculto) {
            return false;
          }

          return r.width > 0 && r.height > 0 && (r.width < MINIMO || r.height < MINIMO);
        })
        .slice(0, 5)
        .map((el) => {
          const r = el.getBoundingClientRect();
          const texto = (el.textContent || '').trim().slice(0, 25);

          return `${el.tagName.toLowerCase()}.${(el.className || '').toString().slice(0, 30)} «${texto}» (${Math.round(r.width)}×${Math.round(r.height)})`;
        });
    });

    expect(pequenos, `Área táctil por debajo de 24px: ${pequenos.join(' | ')}`).toEqual([]);
  });
}
