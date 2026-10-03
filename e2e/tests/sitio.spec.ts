import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

/**
 * La portada pública: accesible en los dos modos, recorrible con teclado y sin
 * JavaScript. No entra en «accesibilidad.spec.ts» porque aquella comprueba el
 * modo por la clase «dark» que pone el panel; la landing sigue
 * `prefers-color-scheme` y no tiene clase que mirar.
 */
const NORMAS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

async function abrirEnModo(page: Page, modo: 'light' | 'dark') {
  await page.emulateMedia({ colorScheme: modo });
  const respuesta = await page.goto('/');
  await page.waitForLoadState('networkidle');
  expect(respuesta?.status(), 'la portada no respondió 200').toBe(200);
}

for (const modo of ['light', 'dark'] as const) {
  test(`la portada cumple WCAG 2.2 AA en modo ${modo === 'light' ? 'claro' : 'oscuro'}`, async ({ page }) => {
    await abrirEnModo(page, modo);

    const resultado = await new AxeBuilder({ page }).withTags(NORMAS).analyze();
    const graves = resultado.violations.filter((v) => v.impact === 'serious' || v.impact === 'critical');
    const detalle = graves.map((v) => `• [${v.impact}] ${v.id}: ${v.help}\n  → ${v.nodes[0]?.target.join(' ')}`).join('\n');

    expect(graves, `Violaciones en la portada (${modo}):\n${detalle}`).toEqual([]);
  });

  test(`captura de la portada en escritorio y móvil, modo ${modo}`, async ({ page }) => {
    await abrirEnModo(page, modo);
    await page.setViewportSize({ width: 1440, height: 900 });
    await page.screenshot({ path: `capturas/sitio/portada-escritorio-${modo}.png`, fullPage: true });
    await page.setViewportSize({ width: 390, height: 844 });
    await page.screenshot({ path: `capturas/sitio/portada-movil-${modo}.png`, fullPage: true });
  });
}

test('la portada no carga ningún script', async ({ page }) => {
  const scripts: string[] = [];
  page.on('request', (r) => {
    if (r.resourceType() === 'script') scripts.push(r.url());
  });
  await abrirEnModo(page, 'light');
  expect(scripts, `La landing pidió scripts: ${scripts.join(', ')}`).toEqual([]);
});

test('se recorre con el teclado: el primer foco es el salto al contenido y se llega a la acción', async ({ page }) => {
  await abrirEnModo(page, 'light');

  await page.keyboard.press('Tab');
  await expect(page.locator(':focus')).toHaveText(/Saltar al contenido/);

  // El anillo de foco se VE: outline de 2px.
  const outline = await page.locator(':focus').evaluate((el) => getComputedStyle(el).outlineWidth);
  expect(outline).toBe('2px');

  // Cuántas paradas más hay: la portada es el boilerplate mínimo (7 enlaces en
  // total, salto incluido) y un número de Tabs fijo mayor que eso saca el foco
  // del documento —«:focus» deja de matchear nada y la prueba cuelga hasta el
  // timeout—. Se cuenta lo que hay de verdad para que la prueba no dependa de
  // cuánto contenido tenga la portada en cada instalación.
  const totalEnlaces = await page.locator('a[href], button, [tabindex]:not([tabindex="-1"])').count();

  const paradas: string[] = [];
  for (let i = 1; i < totalEnlaces; i++) {
    await page.keyboard.press('Tab');
    paradas.push((await page.locator(':focus').textContent())?.trim() ?? '');
  }

  expect(paradas).toContain('Ingresar');
  expect(paradas.some((p) => p.length > 0)).toBe(true);
});

for (const ancho of [320, 360]) {
  test(`la portada no se desborda a ${ancho}px con el widget de Turnstile cargado (WCAG 1.4.10)`, async ({ page }) => {
    await page.setViewportSize({ width: ancho, height: 640 });
    await page.goto('/');
    // Sin `networkidle`: el widget de Turnstile consulta a Cloudflare sin parar.
    await page.waitForSelector('.cf-turnstile iframe', { timeout: 15_000 }).catch(() => {});

    const medidas = await page.evaluate(() => ({
      documento: document.documentElement.scrollWidth,
      ventana: document.documentElement.clientWidth,
    }));
    expect(medidas.documento, `scrollWidth ${medidas.documento} en ${medidas.ventana}px`).toBeLessThanOrEqual(medidas.ventana);
  });
}

test('el skip link enfocado tiene un anillo de ≥ 3:1 contra la barra en los dos modos', async ({ page }) => {
  for (const modo of ['light', 'dark'] as const) {
    await page.emulateMedia({ colorScheme: modo });
    await page.goto('/');
    await page.keyboard.press('Tab');
    const colores = await page.evaluate(() => {
      const enlace = document.activeElement as HTMLElement;
      return {
        anillo: getComputedStyle(enlace).outlineColor,
        barra: getComputedStyle(document.querySelector('header')!).backgroundColor,
      };
    });
    const canales = (c: string) => (c.match(/[\d.]+/g) ?? []).slice(0, 3).map(Number);
    const lum = ([r, g, b]: number[]) => {
      const f = (v: number) => ((v /= 255) <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
      return 0.2126 * f(r) + 0.7152 * f(g) + 0.0722 * f(b);
    };
    const [a, b] = [lum(canales(colores.anillo)), lum(canales(colores.barra))];
    const razon = (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);
    expect(razon, `${modo}: anillo ${colores.anillo} sobre ${colores.barra}`).toBeGreaterThanOrEqual(3);
  }
});
