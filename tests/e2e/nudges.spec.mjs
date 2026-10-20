import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { php } from './global-setup.mjs';
import { demoLogin, ids } from './helpers.mjs';

// Item 11: a teacher sees, by name, who has two or more pieces of work missing or late in the last 14 days, and one
// click sends that student a neutral reminder. The fixture adds two tasks nobody handed in.
test.describe.configure({ mode: 'serial' });
test.beforeAll(() => { execFileSync(php, ['tests/e2e/nudge-fixture.php'], { encoding: 'utf8' }); });
test.afterAll(() => { execFileSync(php, ['tests/e2e/nudge-fixture.php', '--remove'], { encoding: 'utf8' }); });

test('a teacher sends a student a neutral reminder about missing work', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/classes/${ids().classes.sciNarra}`);
  const nudges = page.locator('#nudges');
  const names = await nudges.locator('.row__title').allTextContents();
  expect(names.length).toBeGreaterThan(1);
  const families = names.map((n) => n.split(' ').slice(-1)[0]);
  expect(families).toEqual([...families].sort((a, b) => a.localeCompare(b)));
  await expect(nudges).not.toContainText('%');

  const andrea = nudges.locator('li', { hasText: 'Andrea Cruz' });
  await expect(andrea).toContainText('Missing: Exit ticket: the heart (nudge test), Worksheet: gas exchange (nudge test)');
  await andrea.getByRole('button', { name: /^Send a reminder/ }).click();
  await expect(page.locator('.toast').first()).toContainText('Reminder sent to Andrea Cruz.');
  await expect(page.locator('#nudges li', { hasText: 'Andrea Cruz' })).toContainText('Reminded today');

  // A second reminder the same day is not sent.
  const response = await page.request.post(`teacher/classes/${ids().classes.sciNarra}/nudge/${ids().users.andrea}`, {
    form: { _token: await page.locator('meta[name="csrf-token"]').getAttribute('content') },
  });
  expect(response.ok()).toBeTruthy();
  expect(await response.text()).toContain('already had a reminder about this class today');

  await page.context().clearCookies();
  await demoLogin(page, 'student');
  await page.goto('notifications');
  await expect(page.locator('main')).toContainText('A reminder about Science 9 – Narra');
});

test('only the class\'s teachers can send reminders', async ({ page }) => {
  await demoLogin(page, 'student');
  const response = await page.request.post(`teacher/classes/${ids().classes.sciNarra}/nudge/${ids().users.andrea}`, {
    form: { _token: await page.locator('meta[name="csrf-token"]').getAttribute('content') },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(403);
});
