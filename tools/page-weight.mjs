// Measures what pages download on a throttled mobile connection (Lighthouse's "Slow 4G": 1.6 Mbps down,
// 750 kbps up, 150 ms round trip): bytes transferred on a first visit (empty cache) and on a return visit,
// and how long the first visit takes to load. Signs in with the demo shortcuts, like tools/screens.mjs.
//
//   node tools/page-weight.mjs student:/student student:/student/assignments
//
// Env: SIBOL_URL (default http://localhost/BEST/public).
import { chromium } from '@playwright/test';

const base = process.env.SIBOL_URL || 'http://localhost/BEST/public';
const labels = { student: 'Student', teacher: 'Teacher', admin: 'School administrator' };
const kb = (bytes) => (bytes / 1024).toFixed(0);

const browser = await chromium.launch({ channel: 'msedge' });
try {
  console.log('page'.padEnd(40) + 'first KB'.padStart(10) + 'return KB'.padStart(11) + 'first load s'.padStart(14) + '  largest');
  let role = null;
  let context = null;
  for (const target of process.argv.slice(2)) {
    const [who, path] = target.split(':');
    if (who !== role) {
      if (context) await context.close();
      context = await browser.newContext({ viewport: { width: 390, height: 844 }, isMobile: false, hasTouch: true });
      const login = await context.newPage();
      await login.goto(base + '/login');
      await login.getByRole('button', { name: labels[who], exact: true }).click();
      await login.waitForLoadState('networkidle');
      await login.close();
      role = who;
    }
    const page = await context.newPage();
    const cdp = await context.newCDPSession(page);
    await cdp.send('Network.enable');
    await cdp.send('Network.emulateNetworkConditions', { offline: false, latency: 150, downloadThroughput: (1.6 * 1024 * 1024) / 8, uploadThroughput: (750 * 1024) / 8 });
    const visit = async (emptyCache) => {
      await cdp.send('Network.setCacheDisabled', { cacheDisabled: emptyCache });
      if (emptyCache) await cdp.send('Network.clearBrowserCache');
      const urls = new Map();
      const sizes = new Map();
      const onResponse = (e) => urls.set(e.requestId, e.response.url);
      const onFinished = (e) => sizes.set(e.requestId, e.encodedDataLength);
      cdp.on('Network.responseReceived', onResponse);
      cdp.on('Network.loadingFinished', onFinished);
      const started = Date.now();
      await page.goto(base + path, { waitUntil: 'load' });
      const seconds = (Date.now() - started) / 1000;
      await page.waitForTimeout(300);
      cdp.off('Network.responseReceived', onResponse);
      cdp.off('Network.loadingFinished', onFinished);
      const items = [...sizes].map(([id, size]) => ({ url: (urls.get(id) || '').replace(base, ''), size }));
      return { bytes: items.reduce((sum, i) => sum + i.size, 0), seconds, items };
    };
    const first = await visit(true);
    const again = await visit(false);
    const largest = first.items.sort((a, b) => b.size - a.size).slice(0, 3).map((i) => `${i.url.split('?')[0]} ${kb(i.size)}`).join(', ');
    console.log(`${who}:${path}`.padEnd(40) + kb(first.bytes).padStart(10) + kb(again.bytes).padStart(11) + first.seconds.toFixed(1).padStart(14) + '  ' + largest);
    await page.close();
  }
  if (context) await context.close();
} finally {
  await browser.close();
}
