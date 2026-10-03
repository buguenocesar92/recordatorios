import { expect, test } from '@playwright/test';
import { existsSync, readFileSync, statSync } from 'node:fs';
import { join } from 'node:path';

/**
 * Lista de espera de punta a punta contra el stack local (plan, tarea 0.8):
 * inscribir → confirmar (leyendo el correo del «log mailer») → encuesta → baja.
 *
 * - Turnstile: las claves de PRUEBA documentadas por Cloudflare
 *   (sitekey 1x00000000000000000000AA, secreto 1x0000000000000000000000000000000AA,
 *   siempre pasan). Son las que la app usa fuera de producción cuando
 *   TURNSTILE_SITEKEY / TURNSTILE_SECRET están vacías; en producción el arranque
 *   se niega a usarlas. Necesita salida a challenges.cloudflare.com (widget en
 *   el navegador, siteverify en el servidor).
 * - Correo: `MAIL_MAILER=log` escribe el mensaje en storage/logs/laravel.log
 *   (el repo se monta en el contenedor). La cola la procesa el contenedor
 *   «worker»: tiene que estar arriba.
 * - Cada corrida usa un correo único; la baja del final no deja datos.
 */
const LOG = join(__dirname, '..', '..', 'storage', 'logs', 'laravel.log');

function tamanoDelLog(): number {
  return existsSync(LOG) ? statSync(LOG).size : 0;
}

/**
 * El cuerpo del correo del log mailer sale en quoted-printable: una línea larga
 * se corta con «=» + salto. Solo se deshacen esos cortes. NO se decodifican los
 * «=XX»: las cabeceras (List-Unsubscribe con `signature=bc13…`) no son QP y
 * «=bc» se leería como un carácter.
 */
function decodificarQP(texto: string): string {
  return texto.replace(/=\r?\n/g, '');
}

/** Espera a que el correo de `destinatario` aparezca en el log y devuelve la ruta del enlace de confirmación. */
async function enlaceDeConfirmacion(destinatario: string, desde: number): Promise<string> {
  let ruta: string | null = null;

  await expect
    .poll(
      () => {
        if (!existsSync(LOG)) return null;
        const nuevo = readFileSync(LOG).subarray(desde).toString('utf8');
        const texto = decodificarQP(nuevo);
        if (!texto.includes(destinatario)) return null;
        const hallado = texto.match(/\/lista-de-espera\/confirmar\/([A-Za-z0-9_-]{43})/);
        ruta = hallado ? hallado[0] : null;
        return ruta;
      },
      { message: 'el correo de confirmación no llegó al log (¿está arriba el worker?)', timeout: 45_000, intervals: [500, 1_000, 2_000] },
    )
    .not.toBeNull();

  return ruta as unknown as string;
}

test('inscribirse, confirmar con el enlace del correo, responder la encuesta y darse de baja', async ({ page }) => {
  const correo = `e2e-${Date.now()}-${Math.floor(Math.random() * 1e6)}@example.com`;
  const logAntes = tamanoDelLog();

  // 1. Formulario de la portada.
  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(correo);
  await page.getByLabel('Comuna').selectOption({ index: 1 });
  await page.getByLabel(/Acepto|acepto/).check();

  // El widget de Turnstile (clave de prueba) se resuelve solo y deja su token
  // en un campo oculto: sin él, el servidor responde 422.
  await page.waitForFunction(
    () => {
      const campo = document.querySelector<HTMLInputElement>('input[name="cf-turnstile-response"]');
      return Boolean(campo && campo.value.length > 0);
    },
    null,
    { timeout: 30_000 },
  );

  await page.getByRole('button', { name: /Inscribirme/ }).click();

  // 2. Respuesta genérica: no revela si el correo ya existía.
  await expect(page.getByRole('status')).toContainText('Si tu correo es válido, te enviamos un enlace para confirmar.');

  // 3. El correo sale por la cola: el token se genera en el trabajo, así que
  // solo se puede leer de la bandeja (el log).
  const enlace = await enlaceDeConfirmacion(correo, logAntes);

  // Abrir el enlace NO confirma (un antivirus que lo abre no inscribe a nadie).
  await page.goto(enlace);
  await expect(page.getByRole('heading', { name: 'Confirma tu inscripción' })).toBeVisible();
  await page.getByRole('button', { name: 'Confirmar inscripción' }).click();

  // 4. Confirmada, con la encuesta opcional.
  await expect(page.getByRole('status')).toContainText('Tu inscripción quedó confirmada');
  await page.getByLabel('Sí', { exact: true }).check();
  await page.getByRole('button', { name: 'Enviar respuesta' }).click();
  await expect(page.getByRole('status')).toContainText('Guardamos tu respuesta');

  // El enlace es de un solo uso.
  const reuso = await page.goto(enlace);
  expect(reuso?.status(), 'el enlace de confirmación se pudo usar dos veces').toBe(410);

  // 5. Baja: el enlace firmado sale en la cabecera List-Unsubscribe del correo.
  const baja = await enlaceDeBaja(correo, logAntes);
  await page.goto(baja);
  await page.getByRole('button', { name: 'Darme de baja' }).click();
  await expect(page.getByRole('status')).toContainText('tu inscripción quedó borrada');

  // Ya no hay inscripción: el enlace de confirmación es un 404.
  const despues = await page.goto(enlace);
  expect(despues?.status()).toBe(404);
});

test('sin el token de Turnstile el servidor rechaza el alta y no manda correo', async ({ page }) => {
  const correo = `e2e-sin-token-${Date.now()}@example.com`;
  const logAntes = tamanoDelLog();

  await page.goto('/');
  await page.getByLabel('Correo electrónico').fill(correo);
  await page.getByLabel(/Acepto|acepto/).check();

  // Espera a que el widget termine de resolver y ponga su token: con `load` el
  // script de Turnstile aún puede estar pintándose, y si se quita el campo
  // antes, el widget lo vuelve a crear con un token válido tras el borrado.
  await expect(page.locator('[name="cf-turnstile-response"]')).toHaveValue(/.+/);

  // Quita el campo del widget antes de enviar: simula un bot que no resuelve el desafío.
  await page.evaluate(() => document.querySelectorAll('[name="cf-turnstile-response"]').forEach((e) => e.remove()));
  await page.getByRole('button', { name: /Inscribirme/ }).click();

  await expect(page.locator('#error-turnstile')).toBeVisible();

  await page.waitForTimeout(4_000);
  const nuevo = existsSync(LOG) ? decodificarQP(readFileSync(LOG).subarray(logAntes).toString('utf8')) : '';
  expect(nuevo.includes(correo), 'salió un correo para un alta rechazada').toBe(false);
});

/** La baja firmada, de la cabecera List-Unsubscribe del correo de confirmación. */
async function enlaceDeBaja(destinatario: string, desde: number): Promise<string> {
  const texto = decodificarQP(readFileSync(LOG).subarray(desde).toString('utf8'));
  expect(texto.includes(destinatario)).toBe(true);
  const hallado = texto.match(/List-Unsubscribe: <[^>]*(\/lista-de-espera\/baja\/[0-9A-Za-z]{26}\?signature=[0-9a-f]{64})>/);
  expect(hallado, 'el correo no trae la cabecera List-Unsubscribe con el enlace de baja').not.toBeNull();
  return (hallado as RegExpMatchArray)[1];
}
