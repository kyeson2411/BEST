import { test, expect } from '@playwright/test';
import { demoLogin, ids } from './helpers.mjs';

// Item 14: Sibol installs as an app (manifest, icons made from the seal) and a service worker (public/sw.js)
// keeps the stylesheet, scripts and an offline page. A student can keep class files on the device and read
// them without a connection until they sign out; other pages, and quizzes, stay online-only.
test.describe.configure({ mode: 'serial' });

/** Waits until the service worker controls the page (app.js registers it once the page has loaded). */
const controlled = (page) => page.waitForFunction(() => !!navigator.serviceWorker && navigator.serviceWorker.controller !== null);

/** Addresses (paths) of the files kept on this device, or [] when nothing is kept. */
const kept = (page) => page.evaluate(async () => {
  if (!(await caches.has('sibol-offline-materials'))) return [];
  const cache = await caches.open('sibol-offline-materials');
  return (await cache.keys()).map((request) => new URL(request.url).pathname).filter((path) => path.includes('/materials/'));
});

/** The language of the offline page the service worker has stored. */
const storedOfflinePage = (page) => page.evaluate(async () => {
  const base = document.querySelector('meta[name="app-base"]').content;
  const stored = await caches.match(location.origin + base + '/offline', { cacheName: 'sibol-shell-v1' });
  return stored ? stored.headers.get('Content-Language') : null;
});

test('Sibol can be installed: a manifest with the seal icons and a service worker', async ({ page }) => {
  const response = await page.goto('login');
  expect(response.headers()['content-language']).toBe('en');
  await expect(page.locator('meta[name="theme-color"]')).toHaveAttribute('content', '#1E3D31');

  const manifest = await (await page.request.get(await page.locator('link[rel="manifest"]').getAttribute('href'))).json();
  expect([manifest.short_name, manifest.display]).toEqual(['Sibol', 'standalone']);
  expect(manifest.icons.map((icon) => icon.purpose)).toEqual(['any', 'any', 'maskable', 'maskable']);
  for (const icon of manifest.icons) {
    const file = await page.request.get(icon.src);
    expect(file.status(), icon.src).toBe(200);
    expect(file.headers()['content-type']).toBe('image/png');
  }

  await controlled(page);
  expect(await page.evaluate(() => navigator.serviceWorker.controller.scriptURL)).toMatch(/\/sw\.js$/);
  expect(await storedOfflinePage(page)).toBe('en');
});

test('a kept file opens without a connection; other pages and quizzes show the offline page; signing out removes it', async ({ page, context }) => {
  await demoLogin(page, 'student');
  await page.goto(`student/classes/${ids().classes.sciNarra}?tab=materials`);
  await controlled(page);
  await expect(page.getByText('Files you keep offline open on this device without a connection, until you sign out.')).toBeVisible();

  const notes = page.getByRole('button', { name: 'Keep offline: Breathing and circulation notes' });
  const heredity = page.getByRole('button', { name: 'Keep offline: Heredity notes' });
  await expect(notes).toHaveAttribute('aria-pressed', 'false');
  await notes.click();
  await expect(notes).toHaveAttribute('aria-pressed', 'true');
  await expect(page.locator('.toast').last()).toContainText('“Breathing and circulation notes” is kept on this device.');
  // Keeping and then releasing a second file leaves only the first.
  await heredity.click();
  await expect(heredity).toHaveAttribute('aria-pressed', 'true');
  await heredity.click();
  await expect(heredity).toHaveAttribute('aria-pressed', 'false');
  await expect(page.locator('.toast').last()).toContainText('“Heredity notes” is no longer kept on this device.');
  const keptFile = await notes.getAttribute('data-offline-save');
  const otherFile = await heredity.getAttribute('data-offline-save');
  expect(keptFile).toMatch(new RegExp(`/materials/${ids().releasedMaterial}/file$`));
  expect(await kept(page)).toEqual([keptFile]);

  // The choice is remembered on the device.
  await page.reload();
  await expect(notes).toHaveAttribute('aria-pressed', 'true');
  await expect(heredity).toHaveAttribute('aria-pressed', 'false');

  const online = await (await page.request.get(keptFile)).text();
  const firstLine = online.trim().split('\n')[0];
  await context.setOffline(true);
  try {
    // The kept file opens from the device.
    const opened = await page.goto(keptFile);
    expect(opened.status()).toBe(200);
    expect(await page.locator('body').innerText()).toContain(firstLine);

    // Other pages, a quiz and a file that was not kept show the offline page, which lists the kept file.
    for (const path of ['student/assignments', `student/assignments/${ids().sciPastQuiz}`, otherFile]) {
      await page.goto(path);
      await expect(page.getByRole('heading', { level: 1 }), path).toHaveText('You are offline');
    }
    // The download link finds the kept copy too.
    expect(await page.evaluate((u) => fetch(u + '?download=1').then((r) => r.text()), keptFile)).toBe(online);
    const list = page.locator('[data-offline-list] li');
    await expect(list).toHaveCount(1);
    await expect(list).toContainText('Breathing and circulation notes');
    await expect(list).toContainText('Science 9 – Narra');
    await list.getByRole('link').click();
    await expect(page.locator('body')).toContainText(firstLine);
  } finally {
    await context.setOffline(false);
  }

  await page.goto('student');
  await page.locator('summary[aria-label="Account menu"]').click();
  await page.getByRole('menuitem', { name: 'Sign out' }).click();
  await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible();
  await expect.poll(() => kept(page)).toEqual([]);

  await context.setOffline(true);
  try {
    await page.goto('student');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('You are offline');
    await expect(page.getByText('No files are kept on this device.')).toBeVisible();
    await expect(page.locator('[data-offline-list]')).toBeHidden();
  } finally {
    await context.setOffline(false);
  }
});

test('the offline page is the same for everyone: no form token, name or message', async ({ page }) => {
  await demoLogin(page, 'student');
  const name = (await page.locator('.menu__name').textContent()).trim();
  await page.goto('settings');
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  // A message waiting for this person's next page...
  const sent = await page.request.post('settings/sessions/revoke', { form: { _token: token }, maxRedirects: 0 });
  expect(sent.status()).toBe(303);
  // ...is not put into the offline page, which the device keeps for whoever uses it next.
  const html = await (await page.request.get('offline')).text();
  expect(html).toContain('You are offline');
  for (const personal of ['csrf-token', name, 'signed out everywhere else']) expect(html).not.toContain(personal);
  await page.goto('settings');
  await expect(page.locator('.toast').first()).toContainText('You have been signed out everywhere else.');
});

test('the offline page follows the language of the pages the person sees', async ({ page, context }) => {
  await page.goto('login?lang=fil');
  await controlled(page);
  await expect.poll(() => storedOfflinePage(page)).toBe('fil');
  await context.setOffline(true);
  try {
    await page.goto('help');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('Offline ka');
  } finally {
    await context.setOffline(false);
  }

  await page.goto('login?lang=en');
  await expect.poll(() => storedOfflinePage(page)).toBe('en');
  await context.setOffline(true);
  try {
    await page.goto('help');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('You are offline');
  } finally {
    await context.setOffline(false);
  }
});
