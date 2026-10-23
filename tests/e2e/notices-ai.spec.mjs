import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { acceptNotices, confirmClick, demoLogin, ids, passwordLogin, statusOf } from './helpers.mjs';
import { php } from './global-setup.mjs';

// Item 21: students read and accept the privacy notice and the rules before using Sibol, and again when the
// school asks; an adviser or administrator switches the AI companion off for one student, for example
// when a guardian says no.
test.describe.configure({ mode: 'serial' });

test('a new student reads and accepts the privacy notice and the rules first', async ({ page, browser }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/users/new');
  await page.getByRole('radio', { name: /^Student/ }).check();
  await page.getByLabel('Given name').fill('Kristine');
  await page.getByLabel('Family name').fill('Mallari');
  await page.getByRole('button', { name: 'Create invitation' }).click();
  const link = await page.locator('#reveal-url').inputValue();

  const student = await browser.newPage();
  await student.goto(link);
  await expect(student.getByText('Next, you will read the privacy notice and the rules for using B.E.S.T. Learning and Interactive Study System and accept them.')).toBeVisible();
  await student.getByLabel('Create a password').fill('sunny carabao field');
  await student.getByLabel('Type the password again').fill('sunny carabao field');
  await student.getByRole('button', { name: 'Set up account' }).click();
  await expect(student).toHaveURL(/\/welcome$/);
  await expect(student.getByRole('heading', { name: 'Before you start' })).toBeVisible();
  await expect(student.getByRole('region', { name: /Privacy notice/ })).toContainText('Who is responsible');
  await expect(student.getByRole('region', { name: /Rules for using B.E.S.T. Learning and Interactive Study System/ })).toContainText('Hand in work that is your own.');

  // Nothing else opens until both are accepted.
  await student.goto('student/calendar');
  await expect(student).toHaveURL(/\/welcome$/);
  const json = await student.request.get('notifications/count', { headers: { Accept: 'application/json' }, maxRedirects: 0 });
  expect(json.status()).toBe(403);
  await student.getByRole('button', { name: 'Accept and continue' }).click();
  await expect(student.getByText('Tick this to confirm you have read the privacy notice.')).toBeVisible();
  await expect(student.getByText('Tick this to agree to the rules for using B.E.S.T. Learning and Interactive Study System.')).toBeVisible();

  // Reading them in Filipino first changes the student's language.
  await student.getByRole('button', { name: 'Read in Filipino' }).click();
  await expect(student.locator('html')).toHaveAttribute('lang', 'fil');
  await student.getByRole('button', { name: 'Basahin sa English' }).click();
  await expect(student.locator('html')).toHaveAttribute('lang', 'en');

  // Accepting opens the page the student asked for.
  await acceptNotices(student);
  await expect(student).toHaveURL(/\/student\/calendar$/);
  await expect(student.locator('.toast').first()).toContainText('Thank you. You can use B.E.S.T. Learning and Interactive Study System now.');
  await student.context().clearCookies();
  await passwordLogin(student, 'kristine.mallari', 'sunny carabao field');
  await expect(student).toHaveURL(/\/student$/);
  await student.close();

  // Staff are not asked by default; the administrator sees counts only.
  await page.goto('admin/policies');
  await expect(page.locator('#notices')).toContainText(/\d+ of \d+ active accounts that must accept/);
});

test('after the administrator asks again, students accept the new edition', async ({ page, browser }) => {
  try {
    const andrea = await browser.newPage();
    await demoLogin(andrea, 'student'); // signed in before the change
    await demoLogin(page, 'admin');
    await page.goto('admin/policies');
    await expect(page.locator('#notices')).toContainText('Privacy notice 2026.10.2-1, rules 2026.10-1');
    await confirmClick(page, page.getByRole('button', { name: 'Ask everyone again' }));
    await expect(page.locator('.toast').first()).toContainText('edition 2');
    await expect(page.locator('#notices')).toContainText('Privacy notice 2026.10.2-2, rules 2026.10-2');
    await expect(page.locator('#notices dd').filter({ hasText: 'active accounts' })).toHaveText(/^0 of \d+ active accounts that must accept$/);

    // Work under way is not interrupted (background requests still answer); the next page asks again.
    const poll = await andrea.request.get('notifications/count', { headers: { Accept: 'application/json' }, maxRedirects: 0 });
    expect(poll.status()).toBe(200);
    await andrea.goto('student/assignments');
    await expect(andrea).toHaveURL(/\/welcome$/);
    await expect(andrea.getByText(/has updated how it uses B.E.S.T. Learning and Interactive Study System/)).toBeVisible();
    await acceptNotices(andrea);
    await expect(andrea).toHaveURL(/\/student\/assignments$/);
    await andrea.close();

    const teacher = await browser.newPage();
    await demoLogin(teacher, 'teacher');
    await teacher.close();

    await page.goto('admin/audit');
    await expect(page.getByText('Asked everyone to accept the privacy notice and the rules again').first()).toBeVisible();
    await expect(page.getByText('Accepted the privacy notice and the rules').first()).toBeVisible();
  } finally {
    execFileSync(php, ['tests/e2e/notices-edition.php']);
  }
});

test("an adviser records a guardian's no and the companion disappears for that student", async ({ page, browser }) => {
  // Ramon Dizon advises Grade 9 Narra, where Carlo Reyes is.
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/advisory/${ids().sections.narra}`);
  const row = page.getByRole('row').filter({ hasText: 'Carlo Reyes' });
  await row.getByRole('link', { name: /AI companion and guardian's answer for Carlo Reyes/ }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Carlo Reyes');
  await page.getByLabel('No, they do not agree').check();
  await page.getByRole('button', { name: 'Record answer' }).click();
  await expect(page.locator('.toast').first()).toContainText('Recorded. The guardian did not agree, so the companion is off for Carlo Reyes.');
  await expect(page.getByRole('list', { name: 'Recorded answers' })).toContainText('Entered by Ramon Dizon (teacher)');
  await expect(page.getByLabel(/Off for this student/)).toBeChecked();

  const carlo = await browser.newPage();
  await passwordLogin(carlo, 'carlo.reyes');
  await expect(carlo).toHaveURL(/\/student$/);
  await expect(carlo.locator('a[href*="/companion"], a[href*="/student/learning"]')).toHaveCount(0);
  expect(await statusOf(carlo, 'companion')).toBe(403);
  expect(await statusOf(carlo, 'student/learning')).toBe(403);
  for (const path of ['student/classes', 'student/assignments', 'student/progress', 'student/calendar', 'settings', 'help', 'notifications']) {
    expect(await statusOf(carlo, path), path).toBe(200);
  }
  await carlo.goto('student/progress');
  await expect(carlo.getByRole('heading', { name: 'Recent practice' })).toHaveCount(0);
  await carlo.goto('settings');
  await expect(carlo.locator('main')).toContainText('Switched off for your account by your school');
  await carlo.goto('companion/new');
  await expect(carlo.getByText(/switched off for your account/).first()).toBeVisible();
  await carlo.goto(`student/classes/${ids().classes.sciNarra}`);
  await expect(carlo.getByRole('heading', { level: 1 })).toHaveText('Science 9 – Narra');
  await expect(carlo.getByRole('link', { name: 'Ask about this class' })).toHaveCount(0);

  // Joy Santiago teaches Carlo but does not advise his section.
  const joy = await browser.newPage();
  await passwordLogin(joy, 'joy.santiago');
  expect(await statusOf(joy, `teacher/advisory/${ids().sections.narra}/students/${ids().users.carlo}`)).toBe(404);
  const token = await joy.locator('meta[name="csrf-token"]').getAttribute('content');
  const post = await joy.request.post(`teacher/advisory/${ids().sections.narra}/students/${ids().users.carlo}/ai`, { form: { _token: token, setting: 'on' }, maxRedirects: 0 });
  expect(post.status()).toBe(404);
  await joy.close();

  // Back to the school's setting: the companion returns.
  await page.getByLabel(/Follow the school/).check();
  await page.getByRole('button', { name: 'Save setting' }).click();
  await expect(page.locator('.toast').first()).toContainText('The companion and AI practice are on for Carlo Reyes.');
  await expect(page.getByText('The latest answer recorded from the guardian is no, but the companion is on for this student.')).toBeVisible();
  await carlo.goto('student');
  await expect(carlo.getByRole('link', { name: 'Companion' }).first()).toBeVisible();
  await carlo.close();
});

test("an administrator sees a student's AI setting and their guardian's answers", async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto(`admin/users/${ids().users.luis}`);
  const panel = page.locator('#ai-companion');
  await expect(panel).toContainText('The companion and AI practice are hidden for this student');
  await expect(panel.getByRole('list', { name: 'Recorded answers' })).toContainText('Entered by Liza Mercado (teacher)');
  await expect(panel.getByLabel(/Off for this student/)).toBeChecked();
  await page.goto(`admin/users/${ids().users.ramon}`);
  await expect(page.locator('#ai-companion')).toHaveCount(0);
});
