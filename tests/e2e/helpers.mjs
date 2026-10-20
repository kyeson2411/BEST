import { readFileSync } from 'node:fs';
import { expect } from '@playwright/test';

let cached = null;
/** Ids from the freshly seeded demo school (written by global-setup; read lazily inside tests). */
export function ids() {
  cached = cached || JSON.parse(readFileSync('tests/e2e/.auth/fixtures.json', 'utf8'));
  return cached;
}

/** The fictional demo accounts' shared password, as documented in README.md. */
export const DEMO_PASSWORD = 'Sibol-demo-2026';

const roleButtons = { student: 'Student', teacher: 'Teacher', admin: 'School administrator' };

/** Signs in with the local-only demo shortcut on the sign-in page. */
export async function demoLogin(page, role) {
  await page.goto('login');
  await page.getByRole('button', { name: roleButtons[role], exact: true }).click();
  await expect(page).toHaveURL(new RegExp(`/${role}$`));
}

/** Signs in with a username (students) or an email address (staff can use either) and a password. */
export async function passwordLogin(page, login, password = DEMO_PASSWORD) {
  await page.goto('login');
  await page.getByLabel('Username or email').fill(login);
  await page.getByLabel('Password').fill(password);
  await page.getByRole('button', { name: 'Sign in', exact: true }).click();
}

/** Clicks a button guarded by Sibol's confirm dialog and accepts it. */
export async function confirmClick(page, locator) {
  await locator.click();
  const dialog = page.locator('#confirm-dialog');
  await expect(dialog).toBeVisible();
  await dialog.locator('[data-confirm-ok]').click();
}

/** Status code of a GET made with the page's session. */
export async function statusOf(page, path) {
  const response = await page.request.get(path, { maxRedirects: 0 });
  return response.status();
}

/** A local datetime-local value N days from now at a fixed hour. */
export function inDays(days, hour = 17) {
  const d = new Date();
  d.setDate(d.getDate() + days);
  d.setHours(hour, 0, 0, 0);
  const pad = (n) => String(n).padStart(2, '0');
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}T${pad(d.getHours())}:00`;
}

/** Accepts the privacy notice and the rules on the "Before you start" page a student sees first. */
export async function acceptNotices(page) {
  await expect(page).toHaveURL(/\/welcome$/);
  await page.getByLabel('I have read the privacy notice').check();
  await page.getByLabel('I will follow the rules for using Sibol').check();
  await page.getByRole('button', { name: 'Accept and continue' }).click();
}
