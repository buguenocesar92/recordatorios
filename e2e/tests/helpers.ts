import { Page } from '@playwright/test';

/** Localiza un campo Livewire por su atributo wire:model. */
export function field(page: Page, model: string) {
  return page.locator(`[wire\\:model="${model}"], [wire\\:model\\.live="${model}"], [wire\\:model\\.live\\.debounce\\.500ms="${model}"]`);
}

/** Espera a que haya al menos un componente Livewire montado en la página. */
export async function esperarLivewireMontado(page: Page) {
  await page.waitForFunction(() => {
    const lw = (window as any).Livewire;
    return Boolean(lw) && typeof lw.all === 'function' && lw.all().length > 0;
  }, null, { timeout: 15_000 });
}
