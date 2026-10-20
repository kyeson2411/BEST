// Visual review helper: signs in with the demo shortcuts and captures full-page
// screenshots at desktop, tablet and phone widths using the installed Microsoft Edge.
//
//   node tools/screens.mjs student:/student teacher:/teacher/classes guest:/login
//
// Env: SIBOL_URL (default http://localhost/BEST/public), SIBOL_WIDTHS (default 1440,1024,390),
//      SIBOL_SHOTS output folder (default storage/cache/screens).
import { chromium } from '@playwright/test';
import { mkdirSync } from 'node:fs';

const base = process.env.SIBOL_URL || 'http://localhost/BEST/public';
const out = process.env.SIBOL_SHOTS || 'storage/cache/screens';
const widths = (process.env.SIBOL_WIDTHS || '1440,1024,390').split(',').map(Number);
const targets = process.argv.slice(2);
const labels = { student: 'Student', teacher: 'Teacher', admin: 'School administrator' };
mkdirSync(out, { recursive: true });

const browser = await chromium.launch({ channel: 'msedge' });
try {
  for (const width of widths) {
    const context = await browser.newContext({ viewport: { width, height: width < 600 ? 844 : 900 } });
    const page = await context.newPage();
    page.on('console', (m) => { if (m.type() === 'error') console.log(`[console ${width}] ${m.text()}`); });
    page.on('pageerror', (e) => console.log(`[pageerror ${width}] ${e.message}`));
    let role = null;
    for (const target of targets) {
      const [who, path, name] = target.split(':');
      if (who !== role) {
        await context.clearCookies();
        if (who !== 'guest') {
          await page.goto(base + '/login');
          await page.getByRole('button', { name: labels[who], exact: true }).click();
          await page.waitForLoadState('networkidle');
        }
        role = who;
      }
      const response = await page.goto(base + path, { waitUntil: 'networkidle' });
      const file = `${out}/${name || who + path.replace(/[^a-z0-9]+/gi, '_')}-${width}.png`;
      await page.screenshot({ path: file, fullPage: true });
      console.log(response ? response.status() : '---', file);
    }
    await context.close();
  }
} finally {
  await browser.close();
}
