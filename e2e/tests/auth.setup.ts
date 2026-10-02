import { test as setup } from '@playwright/test';
import fs from 'node:fs';

const authFile = '.auth/user.json';

setup('autenticación inicial del usuario de pruebas', async ({ page }) => {
  fs.mkdirSync('.auth', { recursive: true });

  // El formulario del panel, que es el único que queda: la pantalla propia de
  // «/login» se retiró por duplicada. Los campos de Filament se llaman
  // «form.email»/«form.password» —el punto obliga a seleccionarlos por
  // atributo, no con «#form.email», que CSS leería como una clase—.
  await page.goto('/admin/login');

  await page.locator('input[id="form.email"]').fill('admin@example.com');
  await page.locator('input[id="form.password"]').fill('password');
  await page.locator('button[type="submit"]').click();

  // Esperar a que la URL cambie y no termine en /admin/login
  await page.waitForURL((url) => !url.pathname.endsWith('/login'), { timeout: 15_000 });

  // Si redirige a verificar-mfa o mfa, resolver el OTP usando el simulador local
  if (page.url().includes('/mfa') || page.url().includes('/verificar-mfa')) {
    // El simulador solo se pinta con MFA_SHOW_CODE=true. Sin esa variable la
    // espera se agota con un «Timeout esperando .simulator» que no dice qué
    // falta, y es lo que pasa en una instalación de desarrollo corriente,
    // donde viene apagado. El CI la enciende en su propio job.
    const simulador = page.locator('.simulator');
    try {
      await simulador.waitFor({ timeout: 10_000 });
    } catch {
      throw new Error(
        'El segundo factor pide un código y el simulador no está en pantalla. ' +
          'Pon MFA_SHOW_CODE=true en el .env y reinicia el contenedor de la app ' +
          '(`docker compose up -d --force-recreate app`), o apaga el segundo ' +
          'factor con MFA_ENABLED=false. El CI lo enciende en su propio job; en ' +
          'desarrollo viene apagado a propósito.',
      );
    }
    const otpCode = await page.locator('.simulator strong').nth(1).innerText();
    await page.locator('#code').fill(otpCode.trim());
    await page.locator('button[type="submit"]').click();
  }

  // Esperar a llegar al panel, que es adónde entra todo el mundo («homePath()»).
  await page.waitForURL((url) => url.pathname.includes('/admin') && !url.pathname.endsWith('/login'), { timeout: 20_000 });

  // Guarda el estado de la sesión (cookies/localStorage) para reutilizarlo
  await page.context().storageState({ path: authFile });
});
