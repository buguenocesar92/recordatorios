import { test, Page } from '@playwright/test';

/**
 * Script automatizado para tomar capturas de pantalla de la aplicación
 * que luego serán embebidas en el MANUAL_USUARIO.md.
 */

const PASS = 'password';
const IMG = '../docs/manual/img';

async function login(page: Page, email: string) {
  // El único formulario de acceso del sistema. Los campos de Filament llevan un
  // punto en el id («form.email»), así que se seleccionan por atributo: con
  // «#form.email» el navegador buscaría un elemento «form» con clase «email».
  await page.goto('/admin/login');
  await page.locator('input[id="form.email"]').fill(email);
  await page.locator('input[id="form.password"]').fill(PASS);
  await page.locator('button[type="submit"]').click();
  await page.waitForLoadState('networkidle');
}

async function ir(page: Page, url: string) {
  await page.goto(url);
  await page.waitForLoadState('networkidle');
  await page.waitForTimeout(500);
}

async function shot(page: Page, name: string) {
  await page.screenshot({ path: `${IMG}/${name}.png`, fullPage: false });
}

test('Capturas de Pantalla para Manual de Usuario', async ({ page }) => {
  // Login como administrador
  await login(page, 'admin@example.com');

  // Tomar capturas de pantallas clave del panel administrativo
  await ir(page, '/admin');            await shot(page, 'pantalla-dashboard');
  await ir(page, '/admin/users');      await shot(page, 'pantalla-usuarios');
  await ir(page, '/admin/activities');  await shot(page, 'pantalla-auditoria');
});
