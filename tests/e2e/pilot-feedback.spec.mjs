import { test, expect } from '@playwright/test';
import { demoLogin, ids, passwordLogin, statusOf } from './helpers.mjs';

// Item 22: students and teachers send feedback about Sibol from the page they are on; administrators give it a
// status and one short reply, which arrives as a notification (not a chat); the weekly pilot report shows
// school-wide counts per week, as a page and as CSV, with small groups hidden.
test.describe.configure({ mode: 'serial' });

test('a student sends feedback from the page they are on and reads the reply', async ({ page, browser }) => {
  const classPath = `/student/classes/${ids().classes.sciNarra}`;
  await demoLogin(page, 'student');
  await page.goto(`${classPath.slice(1)}?tab=materials`);
  await page.getByRole('link', { name: 'Send feedback' }).click();
  await expect(page).toHaveURL(/\/feedback\?from=%2Fstudent%2Fclasses%2F\d+$/);
  await expect(page.getByRole('heading', { name: 'Send feedback', level: 1 })).toBeVisible();
  await expect(page.getByLabel(/Include the page you were on/)).toBeChecked();
  await expect(page.locator('main')).toContainText(classPath);
  await expect(page.locator('main')).not.toContainText('tab=materials');

  await page.getByRole('button', { name: 'Send feedback' }).click();
  await expect(page.getByText('What it is about is required.')).toBeVisible();
  await page.getByLabel(/Something is confusing/).check();
  await page.getByLabel('Your feedback').fill('I cannot tell which materials are new this week.');
  await page.getByRole('button', { name: 'Send feedback' }).click();
  await expect(page.locator('.toast').first()).toContainText("Thank you. Your school's administrators will read it.");
  await expect(page.locator('.entry').filter({ hasText: 'which materials are new' })).toContainText('New');

  // The administrator sees it with who sent it and the page, sets a status and replies once.
  const admin = await browser.newPage();
  await demoLogin(admin, 'admin');
  const nav = admin.getByRole('link', { name: /Feedback and pilot report/ });
  await expect(nav.locator('.nav-link__count')).toBeVisible();
  await nav.click();
  const entry = admin.locator('.entry').filter({ hasText: 'which materials are new' });
  await expect(entry).toContainText('Andrea Cruz, student');
  await expect(entry).toContainText(classPath);
  await entry.getByRole('link', { name: 'Something is confusing' }).click();
  await admin.getByRole('radio', { name: /^Fixed/ }).check();
  await admin.getByRole('textbox', { name: /^Reply/ }).fill('Thanks, Andrea. New materials now carry a "New" label on the class page.');
  await admin.getByRole('button', { name: 'Save', exact: true }).click();
  await expect(admin.locator('.toast').first()).toContainText('Saved. Your reply was sent to them as a notification.');
  await admin.goto('admin/audit');
  await expect(admin.getByText('Replied to feedback about Sibol').first()).toBeVisible();
  await expect(admin.locator('main')).not.toContainText('which materials are new');
  await admin.close();

  // The reply arrives as a notification and appears under the student's feedback.
  await page.goto('notifications');
  // Opened by its address: Playwright aims at the hidden "Unread:" prefix of the link instead of its text.
  await page.goto(await page.getByRole('link', { name: /Reply to your feedback/ }).first().getAttribute('href'));
  await expect(page).toHaveURL(/\/feedback#feedback-\d+$/);
  const mine = page.locator('.entry').filter({ hasText: 'which materials are new' });
  await expect(mine).toContainText('Fixed');
  await expect(mine).toContainText('Reply from your school');
  await expect(mine).toContainText('New materials now carry');
});

test('teachers send feedback too, and each role keeps to its own side', async ({ page, browser }) => {
  await passwordLogin(page, 'joy.santiago');
  await page.goto('teacher/gradebook');
  await page.getByRole('link', { name: 'Send feedback' }).click();
  await page.getByLabel(/An idea/).check();
  await page.getByLabel('Your feedback').fill('A way to message a whole section would help.');
  await page.getByLabel(/Include the page you were on/).uncheck();
  await page.getByRole('button', { name: 'Send feedback' }).click();
  await expect(page.locator('.toast').first()).toContainText('Thank you');
  expect(await statusOf(page, 'admin/feedback')).toBe(403);
  expect(await statusOf(page, 'admin/pilot')).toBe(403);

  const admin = await browser.newPage();
  await demoLogin(admin, 'admin');
  await expect(admin.getByRole('link', { name: 'Send feedback' })).toHaveCount(0);
  expect(await statusOf(admin, 'feedback')).toBe(403);
  expect(await statusOf(admin, 'admin/feedback/999999')).toBe(404);
  await admin.goto('admin/feedback?category=idea');
  const entry = admin.locator('.entry').filter({ hasText: 'message a whole section' });
  await expect(entry).toContainText('Joy Santiago, teacher');
  await entry.getByRole('link', { name: 'An idea' }).click();
  await expect(admin.locator('main')).toContainText('Not included');
  await admin.close();
});

test('the weekly pilot report shows counts per week and downloads as CSV', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.getByRole('link', { name: 'Weekly pilot report' }).click();
  await expect(page.getByRole('heading', { name: 'Weekly pilot report', level: 1 })).toBeVisible();
  const table = page.locator('.pilot-report table');
  await expect(table.locator('thead th')).toHaveCount(9);
  await expect(table.getByRole('row', { name: /Active students/ })).toBeVisible();
  await expect(table).toContainText('hidden');
  await expect(table).toContainText('so far');

  await page.getByLabel('Weeks').selectOption('4');
  await expect(page).toHaveURL(/weeks=4/);
  await expect(table.locator('thead th')).toHaveCount(5);

  const response = await page.request.get('admin/pilot/export?weeks=4');
  expect(response.headers()['content-type']).toContain('text/csv');
  const lines = (await response.text()).trim().split('\n');
  expect(lines[0]).toContain('Week starting');
  expect(lines[0]).toContain('Feedback: confusing');
  expect(lines).toHaveLength(5);
  expect(lines.join('\n')).not.toMatch(/Andrea|Cruz|andrea\.cruz/);
  await page.goto('admin/audit');
  await expect(page.getByText('Downloaded the weekly pilot report').first()).toBeVisible();
});
