import { defineConfig, devices } from '@playwright/test';

const baseURL = process.env.BASE_URL ?? 'http://localhost:8211';
const port = new URL(baseURL).port || '80';

/**
 * Configuración E2E contra el stack en ejecución.
 */
export default defineConfig({
  testDir: './tests',
  fullyParallel: false,
  workers: 1,
  retries: 1,
  timeout: 60_000,
  expect: { timeout: 15_000 },
  reporter: [['list'], ['html', { open: 'never' }]],
  use: {
    baseURL,
    headless: true,
    actionTimeout: 15_000,
    navigationTimeout: 30_000,
    screenshot: 'only-on-failure',
    trace: 'retain-on-failure',
    locale: 'es-CL',
    permissions: [],
  },
  projects: [
    { name: 'setup', testMatch: /auth\.setup\.ts/ },
    {
      name: 'chromium',
      use: { ...devices['Desktop Chrome'], storageState: '.auth/user.json' },
      dependencies: ['setup'],
      testIgnore: /waitlist\.spec\.ts/,
    },
    // La lista de espera es pública y sin sesión: ni depende del ingreso al
    // panel ni arrastra su estado de sesión. Corre con
    // `npx playwright test --project=lista-de-espera`.
    {
      name: 'lista-de-espera',
      testMatch: /waitlist\.spec\.ts/,
      use: { ...devices['Desktop Chrome'] },
    },
    /*
     * Hubo un proyecto «webkit-acceso» que corría solo las pantallas de acceso
     * propias en Safari, porque su fondo WebGL2 era lo primero que se rompía
     * ahí. Esas pantallas se retiraron al unificar el ingreso y la recuperación
     * en el panel; si algún día vuelve a haber una pantalla pública con WebGL,
     * vuelve el proyecto acotado a ella.
     */
  ],
  webServer: {
    command: `php ../artisan serve --port=${port}`,
    url: `http://127.0.0.1:${port}`,
    reuseExistingServer: !process.env.CI,
  },
});

