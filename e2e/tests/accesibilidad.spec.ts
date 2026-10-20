import AxeBuilder from '@axe-core/playwright';
import { expect, test, type Page } from '@playwright/test';

/**
 * Accesibilidad comprobada por máquina, en cada cambio.
 *
 * En los sistemas del Estado chileno esto no es un detalle de acabado: el
 * Decreto N.º 1/2015 de SEGPRES obliga a cumplir los estándares W3C, y este
 * repositorio es la plantilla de la que nacen todos. Revisarlo a mano funciona
 * el día que alguien se acuerda; esto lo revisa siempre.
 *
 * No reemplaza la revisión humana —una herramienta automática detecta cerca de
 * un tercio de los problemas reales— pero el tercio que detecta es justo el que
 * se cuela por descuido: contraste insuficiente, un campo sin etiqueta, un
 * botón que es un div, un encabezado que se salta niveles.
 *
 * Se comprueba en los DOS modos. El contraste en oscuro es donde más se rompe,
 * y es invisible para quien desarrolla en claro.
 */

/*
 * Lo que este archivo midió antes y ya no mide.
 *
 * Antes medía también el recorrido guiado —el globo de driver.js— y el
 * muestrario de componentes. Las dos cosas las quitó el commit a123a47, «Quita
 * los módulos municipales y los paquetes compartidos sin perder garantías»:
 * con los paquetes compartidos se fueron la página «/admin/componentes», el
 * recurso de recorridos, sus rutas «/api/onboarding», el globo y las pruebas
 * de PHP que los cubrían. Este archivo se quedó atrás, y como el job de
 * accesibilidad BLOQUEA el merge, el repositorio quedó sin poder fusionar nada.
 *
 * La corrección es alinear la medición con la aplicación que existe, no
 * inventar una pantalla que ya no está. Si alguna vez vuelven el recorrido o
 * el muestrario, sus pruebas se recuperan tal cual del commit d5d3bda.
 */

const PANTALLAS = [
  { nombre: 'ingreso', ruta: '/admin/login', conSesion: false },
  { nombre: 'escritorio', ruta: '/admin', conSesion: true },
  { nombre: 'listado de usuarios', ruta: '/admin/usuarios', conSesion: true },
  { nombre: 'crear usuario', ruta: '/admin/usuarios/create', conSesion: true },
  { nombre: 'salud del sistema', ruta: '/admin/salud-del-sistema', conSesion: true },
];

/** Las reglas que se exigen: WCAG 2.2 hasta nivel AA, que es el objetivo. */
const NORMAS = ['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'];

/**
 * Deja la página lista en el modo que se va a medir, ANTES de cargarla.
 *
 * El orden importa y costó un rato descubrirlo: «addInitScript» solo afecta a
 * navegaciones posteriores, así que llamándolo después de «goto» la página ya
 * se dibujó con el tema que hubiera guardado el navegador. El resultado fue un
 * falso positivo perfecto: texto con el color del modo oscuro sobre el fondo
 * claro, que efectivamente no contrasta —pero eso nunca le pasa a una persona,
 * solo a la prueba mal escrita.
 *
 * Se pide además «menos movimiento». El contraste no depende de ninguna
 * animación, y medir a mitad de un fundido hacía que el resultado cambiara
 * entre corridas: en el panel las tarjetas entran escalonadas, y medio
 * fundido es medio fundido, mide lo que se pintó y no lo que hay.
 */
async function prepararModo(page: Page, modo: 'light' | 'dark') {
  await page.emulateMedia({ colorScheme: modo, reducedMotion: 'reduce' });

  await page.addInitScript((m) => {
    localStorage.setItem('theme', m);
  }, modo);
}

/**
 * Espera a que el panel esté montado antes de medirlo.
 *
 * Los widgets los monta Livewire después de la primera respuesta: medirlos
 * antes deja huecos en la medición, que es peor que no medir, porque el verde
 * no dice nada. Antes esto lo tapaba el recorrido guiado —que tardaba 800 ms
 * en salir—; sin recorrido hay que esperarlo a mano.
 *
 * La espera es TOLERANTE a propósito: que una pantalla no tenga Livewire
 * (el ingreso, por ejemplo) no es un fallo de accesibilidad, es que no hay
 * nada que esperar. Lo que no se tolera es medir una página a medio cargar.
 */
async function esperarPanelListo(page: Page) {
  await page
    .waitForFunction(
      () => {
        const lw = (window as any).Livewire;

        return Boolean(lw) && typeof lw.all === 'function' && lw.all().length > 0;
      },
      null,
      { timeout: 10_000 },
    )
    .catch(() => undefined);
}

async function analizar(page: Page) {
  return new AxeBuilder({ page }).withTags(NORMAS).analyze();
}

for (const pantalla of PANTALLAS) {
  for (const modo of ['light', 'dark'] as const) {
    test(`${pantalla.nombre} cumple WCAG 2.2 AA en modo ${modo === 'light' ? 'claro' : 'oscuro'}`, async ({ page }) => {
      // El tema se fija antes de navegar: ver «prepararModo».
      await prepararModo(page, modo);

      const respuesta = await page.goto(pantalla.ruta);
      await page.waitForLoadState('load');

      // Primero, que la pantalla exista: un 403 o un 500 devuelven la página de
      // error de Laravel, que no lleva la clase «dark» y casi no tiene texto.
      // En claro pasaba la comprobación de modo Y la de axe sin que nadie
      // mirara la pantalla; en oscuro fallaba con un «no quedó en modo dark»
      // que apuntaba al tema cuando el problema era el acceso.
      expect(
        respuesta?.status(),
        `«${pantalla.nombre}» (${pantalla.ruta}) no respondió 200: la prueba mediría la página de error, no la pantalla`,
      ).toBe(200);

      // Y se comprueba que la página está de verdad en el modo que se pidió,
      // porque si no, lo que se mide no es lo que se cree estar midiendo.
      const modoReal = await page.evaluate(() =>
        document.documentElement.classList.contains('dark') ? 'dark' : 'light',
      );
      expect(modoReal, `La página no quedó en modo ${modo}`).toBe(modo);

      // Con sesión, el panel se monta después de la primera respuesta: se
      // espera a que esté para que la medición cubra la pantalla completa.
      if (pantalla.conSesion) {
        await esperarPanelListo(page);
      }

      const resultado = await analizar(page);

      // Se listan con su descripción y el elemento culpable: un fallo que solo
      // dice «hay 3 violaciones» obliga a reproducirlo a mano para saber dónde.
      const graves = resultado.violations.filter((v) =>
        v.impact === 'serious' || v.impact === 'critical',
      );

      const detalle = graves
        .map((v) => `• [${v.impact}] ${v.id}: ${v.help}\n  → ${v.nodes[0]?.target.join(' ')}`)
        .join('\n');

      expect(graves, `Violaciones de accesibilidad en «${pantalla.nombre}» (${modo}):\n${detalle}`).toEqual([]);
    });
  }
}

test('el panel se recorre entero con el teclado', async ({ page }) => {
  // Quien no puede usar el ratón tiene que poder llegar a todo. Y el foco tiene
  // que VERSE: un anillo invisible es lo mismo que no tenerlo.
  await page.goto('/admin');
  await page.waitForLoadState('load');
  await esperarPanelListo(page);

  const paradas: string[] = [];

  for (let i = 0; i < 25; i++) {
    await page.keyboard.press('Tab');

    const foco = await page.evaluate(() => {
      const el = document.activeElement as HTMLElement | null;
      if (!el || el === document.body) {
        return null;
      }

      const estilo = getComputedStyle(el);

      return {
        etiqueta: el.tagName.toLowerCase(),
        // Lo que hace visible el foco puede ser el contorno o un anillo por
        // sombra: cualquiera de los dos vale, ninguno no.
        seVe: estilo.outlineStyle !== 'none' || estilo.boxShadow !== 'none',
      };
    });

    if (foco) {
      paradas.push(foco.etiqueta);
      expect(foco.seVe, `El elemento «${foco.etiqueta}» recibe el foco sin que se vea`).toBeTruthy();
    }
  }

  expect(paradas.length, 'El teclado no llega a ninguna parte del panel').toBeGreaterThan(5);
});
