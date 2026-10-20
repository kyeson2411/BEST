// Times pages for each demo role: signs in with the demo shortcuts, opens each page several times and prints
// the median time the server took to answer (time to first byte) and to finish loading. Use it on the
// pilot-size school (bin/sibol.php db:reset --size=pilot) to find slow pages.
//
//   node tools/timings.mjs student:/student teacher:/teacher/gradebook admin:/admin
//
// Env: SIBOL_URL (default http://localhost/BEST/public), SIBOL_RUNS (default 5).
import { chromium } from '@playwright/test';

const base = process.env.SIBOL_URL || 'http://localhost/BEST/public';
const runs = Number(process.env.SIBOL_RUNS || 5);
const labels = { student: 'Student', teacher: 'Teacher', admin: 'School administrator' };
const median = (list) => [...list].sort((a, b) => a - b)[Math.floor(list.length / 2)];

const browser = await chromium.launch({ channel: 'msedge' });
try {
  // Without the service worker, so the times are the server's and the network's alone.
  const context = await browser.newContext({ viewport: { width: 1440, height: 900 }, serviceWorkers: 'block' });
  const page = await context.newPage();
  let role = null;
  console.log('page'.padEnd(52) + 'server ms'.padStart(10) + 'load ms'.padStart(10));
  for (const target of process.argv.slice(2)) {
    const [who, path] = target.split(':');
    if (who !== role) {
      await context.clearCookies();
      await page.goto(base + '/login');
      await page.getByRole('button', { name: labels[who], exact: true }).click();
      await page.waitForLoadState('networkidle');
      role = who;
    }
    const server = [];
    const load = [];
    for (let i = 0; i <= runs; i++) {
      const response = await page.goto(base + path, { waitUntil: 'load' });
      if (!response || response.status() !== 200) {
        throw new Error(`${path} answered ${response ? response.status() : 'nothing'}`);
      }
      const t = await page.evaluate(() => {
        const n = performance.getEntriesByType('navigation')[0];
        return { server: n.responseStart - n.requestStart, load: n.loadEventEnd - n.startTime };
      });
      if (i > 0) { // the first load warms caches
        server.push(t.server);
        load.push(t.load);
      }
    }
    console.log(`${who}:${path}`.padEnd(52) + String(Math.round(median(server))).padStart(10) + String(Math.round(median(load))).padStart(10));
  }
} finally {
  await browser.close();
}
