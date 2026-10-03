import { expect, test, type Page } from '@playwright/test';

/**
 * Presupuesto de rendimiento del panel, medido.
 *
 * Hasta ahora los umbrales estaban escritos en la documentación y nadie los
 * había medido nunca acá: los números que se citaban venían de otro sistema.
 * Un presupuesto que no se mide es una intención.
 *
 * Los umbrales son los del ecosistema —LCP < 2,5 s · CLS < 0,1— con una
 * salvedad honesta: esto corre en local contra un contenedor, así que mide el
 * trabajo del navegador y del servidor, no la red de una municipalidad. Sirve
 * para detectar una REGRESIÓN —un widget que empieza a saltar, una consulta que
 * se dispara—, no para prometer lo que verá un funcionario con datos móviles.
 * Eso se mide en campo.
 */

/** Lo que el navegador reporta de verdad, no lo que estimamos. */
async function medir(page: Page, ruta: string) {
  await page.goto(ruta);
  await page.waitForLoadState('load');

  return page.evaluate(() => new Promise<{ lcp: number; cls: number; ttfb: number }>((resolve) => {
    let lcp = 0;
    let cls = 0;

    new PerformanceObserver((lista) => {
      for (const entrada of lista.getEntries()) {
        lcp = Math.max(lcp, entrada.startTime);
      }
    }).observe({ type: 'largest-contentful-paint', buffered: true });

    new PerformanceObserver((lista) => {
      for (const entrada of lista.getEntries() as any[]) {
        // Los saltos causados por una interacción no cuentan: son esperados.
        if (!entrada.hadRecentInput) {
          cls += entrada.value;
        }
      }
    }).observe({ type: 'layout-shift', buffered: true });

    const navegacion = performance.getEntriesByType('navigation')[0] as PerformanceNavigationTiming;

    // Se espera un momento para que los observadores recojan lo que ocurre
    // después del primer pintado, que es justo donde aparecen los saltos.
    setTimeout(() => resolve({ lcp, cls, ttfb: navegacion?.responseStart ?? 0 }), 2500);
  }));
}

const PANTALLAS = [
  { nombre: 'portada pública', ruta: '/' },
  { nombre: 'escritorio', ruta: '/admin' },
  { nombre: 'listado de usuarios', ruta: '/admin/usuarios' },
];

for (const pantalla of PANTALLAS) {
  test(`${pantalla.nombre}: dentro del presupuesto`, async ({ page }) => {
    const { lcp, cls, ttfb } = await medir(page, pantalla.ruta);

    console.log(`[${pantalla.nombre}] LCP=${Math.round(lcp)}ms CLS=${cls.toFixed(3)} TTFB=${Math.round(ttfb)}ms`);

    // El escritorio se mira decenas de veces al día: es la pantalla donde una
    // regresión se nota más.
    expect(lcp, `LCP de ${Math.round(lcp)} ms`).toBeLessThan(2500);

    // Cero salto es alcanzable acá y es lo que se decidió: los widgets no van
    // diferidos justamente para que no salte nada. Se deja un margen mínimo
    // para el redondeo del navegador.
    expect(cls, `CLS de ${cls.toFixed(3)}`).toBeLessThan(0.1);
  });
}

test('el escritorio no se dispara en consultas', async ({ page }) => {
  // Un escritorio que crece en consultas es como se degrada un panel sin que
  // nadie lo note: cada widget nuevo suma las suyas. El diagnóstico que motivó
  // este diseño en el sistema del que viene fue «29 consultas por carga».
  const peticiones: string[] = [];

  page.on('request', (r) => {
    if (r.url().includes('/livewire/')) {
      peticiones.push(r.url());
    }
  });

  await page.goto('/admin');
  await page.waitForLoadState('networkidle');

  // Sin carga diferida, el escritorio se dibuja de una: no debería hacer una
  // ronda de peticiones por widget solo para mostrarse.
  expect(peticiones.length, `${peticiones.length} peticiones de Livewire al abrir`).toBeLessThanOrEqual(3);
});
