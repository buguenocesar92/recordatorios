/**
 * Guión de ejemplo: recorrido corto del panel.
 *
 * Abre una portada, entra al panel y hace clic en algo con el cursor visible,
 * cierra. Sirve como PLANTILLA para escribir guiones propios — no muestra
 * todas las funciones del motor (para eso está el README de demo-engine).
 *
 * Uso: demo grabar recorrido
 *
 * Partes de un guion:
 * - id: clave única, sin espacios
 * - titulo: se muestra en portadas y logs
 * - escenas: lista de escenas (cada una es una sección del video)
 *   - id/titulo: identifican la escena
 *   - pasos: lista de acciones
 *     - actor: quién actúa (debe existir en config.actores)
 *     - narrar: texto para síntesis de voz (opcional; sin esto, no hay locución)
 *     - hacer(page, { config }): función async que ejecuta el paso. `config` es
 *       opcional (podés declarar `hacer(page)` a secas) pero sirve para pintar
 *       portada/cierre con la marca real del sistema en vez del azul por defecto.
 *
 * Otras funciones del motor (ver README de demo-engine para el resto):
 * - acercarA(page, selector) / alejar(page)      : zoom sobre un elemento
 * - abrirFiltrado(page, url, {...})              : abre una pantalla con datos
 *   sensibles tapada hasta que un buscador la filtra a una sola fila
 * - abrirVerificado(page, url, comprobar, {...}) : igual, pero con un predicado
 *   propio en vez de un buscador (para pantallas sin filtro)
 */

import { portada, cierre, pulsar } from 'demo-engine';

export default {
  id: 'recorrido',
  titulo: 'Recorrido del panel administrativo',

  escenas: [
    {
      id: 'portada',
      titulo: 'Portada',
      pasos: [
        {
          actor: 'administrador',
          narrar: 'Bienvenido al panel de administración.',
          hacer: async (page, { config }) => {
            await portada(page, {
              titulo: 'Panel de administración',
              subtitulo: 'Recorrido rápido',
              marca: config.marca,
            });
          },
        },
      ],
    },

    {
      id: 'panel',
      titulo: 'Entrar y navegar',
      pasos: [
        {
          actor: 'administrador',
          narrar: 'Ya estamos autenticados. Este es el panel principal.',
          hacer: async (page) => {
            // El motor ya logueó al actor (sesión guardada por `demo preparar`).
            await page.goto('/admin');
            await page.waitForSelector('main', { timeout: 5000 }).catch(() => {});
          },
        },
        {
          actor: 'administrador',
          narrar: 'Lo primero que se ve es qué requiere atención hoy, si el sistema está sano y qué se tocó últimamente.',
          hacer: async (page) => {
            // El escritorio es lo que ve alguien al entrar, así que el video
            // empieza por ahí y no por el menú: es la pantalla que responde
            // «¿por dónde empiezo?».
            await page.waitForSelector('.fi-wi-widget', { timeout: 5000 }).catch(() => {});
            await page.waitForTimeout(1200);
          },
        },
        {
          actor: 'administrador',
          narrar: 'Para llegar a cualquier parte sin buscar en el menú, se abre el buscador con Control y K.',
          hacer: async (page) => {
            // Es funcionalidad que nadie descubre solo: si el video no la
            // muestra, no existe para quien usa el sistema.
            await page.keyboard.press('Control+k');
            await page.waitForTimeout(1500);
            await page.keyboard.press('Escape');
            await page.waitForTimeout(500);
          },
        },
        {
          actor: 'administrador',
          narrar: 'Y desde el menú lateral se llega a cada sección.',
          hacer: async (page) => {
            // `pulsar` mueve el cursor con easing, dibuja un halo y recién ahí hace
            // clic — así el video muestra un cursor real en vez de un salto
            // invisible. Ajusta el selector al enlace real de tu panel.
            await pulsar(page, '.fi-sidebar-nav a');
            await page.waitForTimeout(800);
          },
        },
      ],
    },

    {
      id: 'cierre',
      titulo: 'Cierre',
      pasos: [
        {
          actor: 'administrador',
          narrar: 'Esto fue un recorrido rápido. Para más detalle, revisa el manual.',
          hacer: async (page, { config }) => {
            await cierre(page, {
              mensaje: 'Fin del recorrido',
              marca: config.marca,
            });
          },
        },
      ],
    },
  ],
};
