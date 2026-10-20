// Drives one companion exchange in Microsoft Edge and captures screenshots (visual check helper).
import { chromium } from '@playwright/test';

const base = process.env.SIBOL_URL || 'http://localhost/BEST/public';
const width = Number(process.env.SIBOL_WIDTH || 1440);
const browser = await chromium.launch({ channel: 'msedge' });
const page = await browser.newPage({ viewport: { width, height: width < 600 ? 844 : 900 } });
page.on('console', (m) => { if (m.type() === 'error') console.log('[console]', m.text()); });
page.on('pageerror', (e) => console.log('[pageerror]', e.message));
try {
  await page.goto(base + '/login');
  await page.getByRole('button', { name: 'Student', exact: true }).click();
  await page.waitForURL(/\/student$/);
  await page.goto(base + '/companion/new?mode=ask_materials');
  await page.screenshot({ path: `storage/cache/screens/companion-new-${width}.png`, fullPage: true });
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.getByLabel('Your first message').fill('How does gas exchange happen in the alveoli?');
  await page.getByRole('button', { name: 'Start session' }).click();
  await page.waitForSelector('.source-tag', { timeout: 20000 });
  await page.screenshot({ path: `storage/cache/screens/companion-answer-${width}.png`, fullPage: false });
  // Follow-up question in socratic style through the composer.
  await page.getByLabel('Message').fill('Why are the walls of the alveoli so thin?');
  await page.keyboard.press('Enter');
  await page.waitForFunction(() => document.querySelectorAll('.msg--assistant:not(.is-streaming)').length >= 2, null, { timeout: 20000 });
  await page.locator('a.cite').first().click();
  await page.screenshot({ path: `storage/cache/screens/companion-followup-${width}.png`, fullPage: false });
  console.log('assistant messages:', await page.locator('.msg--assistant').count(), 'source tags:', await page.locator('.source-tag').count());
} catch (e) {
  console.log('FAILED:', e.message);
  await page.screenshot({ path: `storage/cache/screens/companion-failure-${width}.png`, fullPage: true });
} finally {
  await browser.close();
}
