import { test, expect } from '@playwright/test';
import { demoLogin, passwordLogin, statusOf } from './helpers.mjs';

test.describe('The school landing page', () => {
  test('the site root shows the school photo, seal and sign-in form', async ({ page }) => {
    await page.goto('');
    await expect(page.getByRole('heading', { level: 1, name: 'Sign in to Sibol' })).toBeVisible();
    await expect(page.locator('.auth__school-name')).toHaveText('B.E.S.T. College of Polomolok');
    const photo = page.getByRole('img', { name: /Students in green and white uniforms/ });
    await expect(photo).toBeVisible();
    const loaded = await page.evaluate(() => [...document.images].map((img) => ({ src: img.currentSrc, ok: img.complete && img.naturalWidth > 0 })));
    expect(loaded.length).toBeGreaterThanOrEqual(2);
    for (const img of loaded) expect(img.ok, img.currentSrc).toBe(true);
    expect(loaded.some((img) => /campus-\d+\.(webp|jpg)/.test(img.src))).toBe(true);
    expect(loaded.some((img) => /seal-\d+\.(webp|png)/.test(img.src))).toBe(true);
    await expect(page.getByText('I am the Way, the Truth, the Life.')).toBeVisible();
  });
});

test.describe('Signing in and out', () => {
  test('a wrong password is refused with a clear message', async ({ page }) => {
    await passwordLogin(page, 'andrea.cruz', 'not-the-password-1');
    await expect(page.getByText('The username or password is not correct.')).toBeVisible();
    await expect(page).toHaveURL(/\/login$/);
  });

  test('the documented demo password signs a student in', async ({ page }) => {
    await passwordLogin(page, 'andrea.cruz');
    await expect(page).toHaveURL(/\/student$/);
    await expect(page.getByRole('heading', { level: 1 })).toContainText('Andrea');
  });

  for (const role of ['student', 'teacher', 'admin']) {
    test(`the ${role} demo account lands on its own home`, async ({ page }) => {
      await demoLogin(page, role);
      await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
      await expect(page.locator('.demo-band')).toContainText('fictional');
    });
  }

  test('signing out ends the session', async ({ page }) => {
    await demoLogin(page, 'student');
    await page.getByLabel('Account menu').click();
    await page.getByRole('menuitem', { name: 'Sign out' }).click();
    await expect(page).toHaveURL(/\/login$/);
    await page.goto('student');
    await expect(page).toHaveURL(/\/login$/);
  });

  test('guests are sent to sign in and private files are not served', async ({ page }) => {
    await page.goto('teacher/gradebook');
    await expect(page).toHaveURL(/\/login$/);
    // Apache refuses them (403); a server whose document root is public/ cannot reach them at all (404).
    expect([403, 404]).toContain(await statusOf(page, '../.env'));
    expect([403, 404]).toContain(await statusOf(page, '../storage/logs/sibol.log'));
  });
});
