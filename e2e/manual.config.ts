import { defineConfig, devices } from '@playwright/test';

/**
 * Configuración dedicada a generar capturas de pantalla para el manual.
 */
export default defineConfig({
  testDir: './manual',
  fullyParallel: false,
  workers: 1,
  retries: 0,
  timeout: 90_000,
  reporter: [['list']],
  use: {
    baseURL: process.env.BASE_URL ?? 'http://localhost:8211',
    headless: true,
    viewport: { width: 1366, height: 900 },
    deviceScaleFactor: 2, // Capturas nítidas estilo retina
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
    locale: 'es-CL',
    screenshot: 'off',
  },
  projects: [
    { name: 'manual', use: { ...devices['Desktop Chrome'] } },
  ],
});
