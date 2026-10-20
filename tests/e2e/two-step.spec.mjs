import { createHmac } from 'node:crypto';
import { test, expect } from '@playwright/test';
import { demoLogin, passwordLogin, confirmClick, ids } from './helpers.mjs';

// Item 15: optional two-step sign-in for staff. Joy (a teacher) turns it on with a code computed here the way
// an authenticator app does (RFC 6238), signs in with an app code and then with a recovery code (which works
// once), and an administrator switches it off after a "lost phone". Joy is left without it at the end.
test.describe.configure({ mode: 'serial' });

/** The 6-digit code an authenticator app shows for a base32 key, `steps` 30-second steps from now. */
function appCode(key, steps = 0) {
  const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
  const bits = [...key.replace(/\s+/g, '')].map((c) => alphabet.indexOf(c).toString(2).padStart(5, '0')).join('');
  const secret = Buffer.from(bits.match(/.{8}/g).map((byte) => parseInt(byte, 2)));
  const counter = Buffer.alloc(8);
  counter.writeBigUInt64BE(BigInt(Math.floor(Date.now() / 30000) + steps));
  const hash = createHmac('sha1', secret).update(counter).digest();
  const offset = hash[19] & 0x0f;
  const value = ((hash[offset] & 0x7f) << 24) | (hash[offset + 1] << 16) | (hash[offset + 2] << 8) | hash[offset + 3];
  return String(value % 1_000_000).padStart(6, '0');
}

async function signOut(page) {
  await page.locator('summary[aria-label="Account menu"]').click();
  await page.getByRole('menuitem', { name: 'Sign out' }).click();
  await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible();
}

let key = '';
let codes = [];

test('a teacher turns on two-step sign-in with a code from the app and gets recovery codes once', async ({ page }) => {
  await passwordLogin(page, 'joy.santiago');
  await expect(page).toHaveURL(/\/teacher$/);
  await page.goto('settings#two-step');
  await page.getByRole('link', { name: 'Set up two-step sign-in' }).click();
  await expect(page.getByRole('img', { name: 'QR code for your authenticator app' })).toBeVisible();
  key = (await page.locator('#two-step-key').textContent()).trim();
  expect(key.replace(/\s+/g, '')).toMatch(/^[A-Z2-7]{32}$/);

  await page.getByLabel('Code from the app').fill('000000');
  await page.getByLabel('Current password').fill('Sibol-demo-2026');
  await page.getByRole('button', { name: 'Turn on two-step sign-in' }).click();
  await expect(page.locator('.field__error')).toContainText('That code does not match');
  // The same key is still shown, so the app need not scan again.
  expect((await page.locator('#two-step-key').textContent()).trim()).toBe(key);

  await page.getByLabel('Code from the app').fill(appCode(key));
  await page.getByLabel('Current password').fill('Sibol-demo-2026');
  await page.getByRole('button', { name: 'Turn on two-step sign-in' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Your recovery codes');
  codes = (await page.locator('#recovery-codes').textContent()).trim().split('\n');
  expect(codes).toHaveLength(10);
  // Shown once only.
  await page.reload();
  await expect(page).toHaveURL(/\/settings/);
  await expect(page.locator('#two-step')).toContainText('10 recovery codes left');
});

test('signing in then asks for a code; each code works once; a recovery code works without the phone', async ({ page }) => {
  await passwordLogin(page, 'joy.santiago');
  await expect(page).toHaveURL(/\/login\/code$/);
  // Not signed in yet.
  expect((await page.request.get('teacher', { maxRedirects: 0 })).status()).toBe(302);

  await page.getByLabel('Code').fill('123456');
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.locator('.field__error')).toContainText('That code does not match');
  const code = appCode(key, 1);
  await page.getByLabel('Code').fill(code);
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page).toHaveURL(/\/teacher$/);
  await signOut(page);

  // The same app code cannot be used again.
  await passwordLogin(page, 'joy.santiago');
  await page.getByLabel('Code').fill(code);
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.locator('.field__error')).toContainText('That code does not match');

  await page.getByLabel('Code').fill(codes[0].toLowerCase());
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page).toHaveURL(/\/teacher$/);
  await expect(page.locator('.toast').first()).toContainText('You used a recovery code. 9 are left');
  await signOut(page);

  await passwordLogin(page, 'joy.santiago');
  await page.getByLabel('Code').fill(codes[0]);
  await page.getByRole('button', { name: 'Continue' }).click();
  await expect(page.locator('.field__error')).toContainText('That code does not match');
});

test('a password reset link still leads to the code step', async ({ page, browser }) => {
  await demoLogin(page, 'admin');
  await page.goto(`admin/users/${ids().users.joy}`);
  await page.getByRole('button', { name: 'Create password reset link' }).click();
  const link = await page.locator('#reveal-url').inputValue();

  const joy = await browser.newPage();
  await joy.goto(link);
  await joy.getByLabel('New password').fill('Sibol-demo-2026');
  await joy.getByLabel('Type the password again').fill('Sibol-demo-2026');
  await joy.getByRole('button', { name: 'Save password' }).click();
  await expect(joy).toHaveURL(/\/login\/code$/);
  await expect(joy.locator('.toast').first()).toContainText('Type the code from your authenticator app');
  expect((await joy.request.get('teacher', { maxRedirects: 0 })).status()).toBe(302);
  await joy.getByLabel('Code').fill(codes[1]);
  await joy.getByRole('button', { name: 'Continue' }).click();
  await expect(joy).toHaveURL(/\/teacher$/);
  await joy.close();
});

test('an administrator switches it off after a lost phone, and Joy is told', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto(`admin/users/${ids().users.joy}`);
  const access = page.locator('section', { has: page.locator('#access-title') });
  await expect(access.locator('dt', { hasText: 'Two-step sign-in' }).locator('+ dd')).toHaveText(/^On since /);
  await confirmClick(page, page.getByRole('button', { name: 'Switch off two-step sign-in' }));
  await expect(page.locator('.toast').first()).toContainText('Two-step sign-in is off for Joy Santiago');
  await expect(access.locator('dt', { hasText: 'Two-step sign-in' }).locator('+ dd')).toHaveText('Off');
  await expect(page.locator('.activity, section').getByText('Switched off two-step sign-in for someone').first()).toBeVisible();
  await page.context().clearCookies();

  await passwordLogin(page, 'joy.santiago');
  await expect(page).toHaveURL(/\/teacher$/);
  await page.goto('notifications');
  await expect(page.getByText('Two-step sign-in was switched off for your account')).toBeVisible();
});
