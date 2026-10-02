// Genera el manual en HTML y PDF a partir de docs/manual/MANUAL_USUARIO.md.
// Uso:  cd e2e && node build-manual.mjs
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, resolve } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { marked } from 'marked';
import { chromium } from '@playwright/test';

const here = dirname(fileURLToPath(import.meta.url));
const manualDir = resolve(here, '../docs/manual');

// Definir manuales a construir
const MANUALES = [
  { base: 'MANUAL_USUARIO', title: 'Manual de Usuario — Sistema Municipal' }
];

const css = `
  :root { --brand-color:#38bdf8; --text-color:#1e293b; --borde:#e2e8f0; }
  * { box-sizing: border-box; }
  body { font-family: system-ui, -apple-system, sans-serif; color: var(--text-color); line-height:1.55;
         max-width: 820px; margin: 0 auto; padding: 24px; }
  h1 { color: #0f172a; font-size: 28px; margin: 0 0 8px; border-bottom: 2px solid var(--borde); padding-bottom: 8px;}
  h2 { color: #1e3a8a; border-top: 1px dashed var(--borde); padding-top: 18px; margin-top: 34px; font-size: 22px;
       page-break-after: avoid; }
  h3 { color:#2563eb; font-size: 16px; margin-top: 20px; page-break-after: avoid; }
  p, li { font-size: 14px; }
  table { border-collapse: collapse; width: 100%; margin: 12px 0; }
  th, td { border: 1px solid var(--borde); padding: 8px 12px; font-size: 13.5px; text-align: left; }
  th { background:#f8fafc; }
  blockquote { background:#f0f7ff; border-left: 4px solid var(--brand-color); margin: 12px 0; padding: 10px 16px;
               color: #475569; font-size: 13.5px; border-radius: 4px; }
  img { max-width: 100%; height: auto; border: 1px solid var(--borde); border-radius: 8px; margin: 12px 0;
        box-shadow: 0 4px 12px rgba(0,0,0,.08); page-break-inside: avoid; }
  code { background:#f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12.5px; }
  hr { border: none; border-top: 1px solid var(--borde); margin: 24px 0; }
  h3, h2 { break-after: avoid; }
`;

const browser = await chromium.launch();

for (const { base, title } of MANUALES) {
  const mdPath = resolve(manualDir, `${base}.md`);
  const htmlPath = resolve(manualDir, `${base}.html`);
  const pdfPath = resolve(manualDir, `${base}.pdf`);

  try {
    const body = marked.parse(readFileSync(mdPath, 'utf8'));
    const html = `<!doctype html>
<html lang="es"><head>
<meta charset="utf-8">
<title>${title}</title>
<style>${css}</style>
</head><body><main>${body}</main></body></html>`;

    writeFileSync(htmlPath, html);
    console.log('✓ HTML:', htmlPath);

    const page = await browser.newPage();
    await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'networkidle' });
    await page.pdf({
      path: pdfPath,
      format: 'A4',
      printBackground: true,
      margin: { top: '15mm', bottom: '15mm', left: '15mm', right: '15mm' },
    });
    await page.close();
    console.log('✓ PDF :', pdfPath);
  } catch (error) {
    console.error(`Error generando manual ${base}:`, error);
  }
}

await browser.close();
