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

const PANTALLAS = [
  { nombre: 'ingreso', ruta: '/admin/login', conSesion: false },
  { nombre: 'escritorio', ruta: '/admin', conSesion: true },
  { nombre: 'listado de usuarios', ruta: '/admin/usuarios', conSesion: true },
  { nombre: 'crear usuario', ruta: '/admin/usuarios/create', conSesion: true },
  { nombre: 'salud del sistema', ruta: '/admin/salud-del-sistema', conSesion: true },
  // El muestrario solo existe en desarrollo y en «testing», que es el entorno
  // con el que el CI levanta la aplicación para esta batería. Entra igual: si
  // un componente del paquete no cumple contraste, se ve acá antes de que
  // alguien lo ponga en una pantalla que sí usa un vecino.
  { nombre: 'muestrario de componentes', ruta: '/admin/componentes', conSesion: true },
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
 * animación, y medir a mitad de un fundido era justo lo que hacía que el
 * resultado cambiara entre corridas (ver «esperarRecorrido»). Antes se
 * escribía acá una clave «admin_tour_seen» para «apagar el recorrido»; nadie
 * la leía —lo completado se guarda en el servidor— y el recorrido salía igual.
 */
async function prepararModo(page: Page, modo: 'light' | 'dark') {
  await page.emulateMedia({ colorScheme: modo, reducedMotion: 'reduce' });

  await page.addInitScript((m) => {
    localStorage.setItem('theme', m);
  }, modo);
}

/**
 * Espera a que el recorrido guiado esté en pantalla y quieto antes de medir.
 *
 * El recorrido arranca 800 ms después de que la página pide «/api/onboarding»,
 * es decir, DESPUÉS de «networkidle»: axe empezaba con el globo ausente y
 * terminaba con el globo puesto, y lo que atrapaba a medio camino era el
 * fundido de entrada de driver.js. axe mezcla el color del texto con la
 * opacidad del elemento, así que «Siguiente» al 2 % de opacidad daba #fbfbfb
 * sobre blanco: el 1,04:1 que aparecía y desaparecía entre corridas sin que
 * nadie lo viera en pantalla. Así el globo se mide SIEMPRE, en los dos modos,
 * y nunca a medias.
 *
 * driver.js pone el foco en la «×» de cerrar al dibujar el globo, y con foco la
 * pinta oscura: se quita para medir el estado de reposo, que es el que ve quien
 * usa el ratón. Se quita ANTES de esperar a las animaciones, porque al soltar
 * el foco el color viaja 200 ms («transition: color .2s» de driver.css) y una
 * medición inmediata cae a medio camino: la «×» pasaba por buena con 1,51:1.
 *
 * Se espera solo a las animaciones y transiciones del globo y del velo, no a
 * «todas las de la página»: el muestrario tiene un «muni-shimmer» infinito y
 * esperar a que termine agota el tiempo de la prueba.
 */
async function esperarRecorrido(page: Page) {
  const popover = page.locator('.driver-popover');
  await popover.waitFor({ state: 'visible', timeout: 20_000 });

  await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());

  await page.waitForFunction(() => {
    // Forzar el recálculo de estilos: una transición recién disparada por el
    // «blur» tiene que figurar ya en «getAnimations()», no en el cuadro siguiente.
    const globo = document.querySelector('.driver-popover');
    if (globo) {
      getComputedStyle(globo).color;
    }

    return document.getAnimations().every((animacion) => {
      const objetivo = (animacion.effect as KeyframeEffect | null)?.target as Element | null;

      return !objetivo
        || !objetivo.closest('.driver-popover, .driver-overlay')
        || animacion.playState === 'finished';
    });
  });
}

/**
 * Contraste WCAG (luminancia relativa sRGB) entre el color de un elemento y el
 * fondo de otro, con los colores YA computados por el navegador: mide lo que se
 * pintó de verdad, con los tokens del tema que haya cargado.
 */
function contraste(page: Page, selectorTexto: string, propiedad: 'color' | 'borderTopColor', selectorFondo: string) {
  return page.evaluate(([st, prop, sf]) => {
    const rgb = (s: string) => s.match(/\d+(\.\d+)?/g)!.slice(0, 3).map(Number);
    const lineal = (c: number) => {
      c /= 255;

      return c <= 0.04045 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    };
    const luminancia = ([r, g, b]: number[]) => 0.2126 * lineal(r) + 0.7152 * lineal(g) + 0.0722 * lineal(b);

    const estiloTexto = getComputedStyle(document.querySelector(st)!);
    const texto = rgb(prop === 'color' ? estiloTexto.color : estiloTexto.borderTopColor);
    const fondo = rgb(getComputedStyle(document.querySelector(sf)!).backgroundColor);
    const [a, b] = [luminancia(texto), luminancia(fondo)];

    return Number(((Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05)).toFixed(2));
  }, [selectorTexto, propiedad, selectorFondo] as const);
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
      await page.waitForLoadState('networkidle');

      // Primero, que la pantalla exista: un 403 o un 500 devuelven la página de
      // error de Laravel, que no lleva la clase «dark» y casi no tiene texto.
      // En claro pasaba la comprobación de modo Y la de axe sin que nadie
      // mirara el muestrario; en oscuro fallaba con un «no quedó en modo dark»
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

      // Con sesión, el recorrido guiado sale solo en cada pantalla del panel:
      // se espera a que esté puesto y quieto para que el globo entre en la
      // medición, en los dos modos, y no a medio fundido (ver «esperarRecorrido»).
      if (pantalla.conSesion) {
        await esperarRecorrido(page);
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

/*
 * Lo que axe deja pasar en el recorrido guiado, medido a mano.
 *
 * driver.js trae un globo blanco fijo con la «×» de cerrar en #d2d2d2 (1,51:1)
 * y botones delimitados solo por un borde #ccc (1,61:1). axe no lo reporta: la
 * «×» queda en «incomplete» por contenido demasiado corto, y el borde de un
 * control no es texto (WCAG 1.4.11 pide 3:1 y axe no lo mide). Es lo primero
 * que ve quien llega nuevo al sistema, así que se mide acá, en los dos modos.
 */
for (const modo of ['light', 'dark'] as const) {
  test(`el recorrido guiado cumple contraste en sus controles en modo ${modo === 'light' ? 'claro' : 'oscuro'}`, async ({ page }) => {
    await prepararModo(page, modo);
    await page.goto('/admin');
    await esperarRecorrido(page);

    const cerrar = await contraste(page, '.driver-popover-close-btn', 'color', '.driver-popover');
    const bordeSiguiente = await contraste(page, '.driver-popover-next-btn', 'borderTopColor', '.driver-popover');
    const textoSiguiente = await contraste(page, '.driver-popover-next-btn', 'color', '.driver-popover-next-btn');
    const progreso = await contraste(page, '.driver-popover-progress-text', 'color', '.driver-popover');

    // «soft»: que un fallo liste los cuatro pares, no solo el primero que cae.
    expect.soft(textoSiguiente, `texto del botón «Siguiente» sobre su fondo: ${textoSiguiente}:1 (AA pide 4,5:1)`).toBeGreaterThanOrEqual(4.5);
    expect.soft(progreso, `texto de progreso «1 de 7» sobre el globo: ${progreso}:1 (AA pide 4,5:1)`).toBeGreaterThanOrEqual(4.5);
    expect.soft(cerrar, `la «×» de cerrar en reposo sobre el globo: ${cerrar}:1 (AA pide 4,5:1)`).toBeGreaterThanOrEqual(4.5);
    expect.soft(bordeSiguiente, `borde del botón «Siguiente» sobre el globo: ${bordeSiguiente}:1 (WCAG 1.4.11 pide 3:1)`).toBeGreaterThanOrEqual(3);
  });
}

test('el recorrido guiado respeta prefers-reduced-motion', async ({ page }) => {
  // «prepararModo» ya pide menos movimiento. driver.js marca el cuerpo con
  // «driver-fade» cuando anima y con «driver-simple» cuando no: con la
  // preferencia activa tiene que ser lo segundo (WCAG 2.3.3). Además, sin
  // fundido no queda ventana en la que axe pueda medir el globo a medias.
  await prepararModo(page, 'light');
  await page.goto('/admin');
  await page.locator('.driver-popover').waitFor({ state: 'visible', timeout: 20_000 });

  await expect(page.locator('body')).toHaveClass(/driver-simple/);
});

test('el panel se recorre entero con el teclado', async ({ page }) => {
  // Quien no puede usar el ratón tiene que poder llegar a todo. Y el foco tiene
  // que VERSE: un anillo invisible es lo mismo que no tenerlo.
  await page.goto('/admin');
  await page.waitForLoadState('networkidle');

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
