import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { demoLogin, passwordLogin, confirmClick } from './helpers.mjs';
import { php } from './global-setup.mjs';

// Quiz attempts that survive bad connections (Playwright's offline mode): answers typed offline stay in
// this tab and are sent on reconnect, a reload restores answers the server never confirmed, submitting
// waits for the connection, and when time runs out offline the receipt shows which answers counted and
// the teacher can reopen the attempt.
test.describe.configure({ mode: 'serial' });

const QUIZ = 'Heredity basics';
const kept = (page) => page.evaluate(() => {
  const out = [];
  for (let i = 0; i < sessionStorage.length; i++) {
    const k = sessionStorage.key(i);
    if (k.startsWith('sibol.quiz.')) out.push(sessionStorage.getItem(k));
  }
  return out;
});
const moveDeadline = (username, seconds) => execFileSync(php, ['tests/e2e/quiz-deadline.php', username, QUIZ, String(seconds)], { encoding: 'utf8' });

async function openQuiz(page) {
  await page.goto('student/assignments?filter=all');
  await page.getByRole('link', { name: QUIZ }).click();
}

test('answers given offline stay on the device and are sent on reconnect', async ({ page, context }) => {
  await demoLogin(page, 'student');
  await openQuiz(page);
  await confirmClick(page, page.getByRole('button', { name: 'Start quiz' }));
  await expect(page).toHaveURL(/\/attempt$/);
  const status = page.locator('[data-save-status]');
  const questions = page.locator('.question');

  await questions.nth(0).getByLabel(/1 in 4/).check();
  await expect(status).toContainText('saved');

  await context.setOffline(true);
  await questions.nth(1).getByLabel('True', { exact: true }).check();
  await expect(page.locator('[data-connection]')).toContainText('You are offline');
  await expect(status).toContainText('kept on this device');
  expect((await kept(page)).join('')).toContain('"true"');

  await context.setOffline(false);
  await expect(status).toContainText('saved', { timeout: 20_000 });
  await expect(page.locator('[data-connection]')).toBeHidden();
  expect(await kept(page)).toEqual([]);

  await page.reload();
  await expect(questions.nth(1).getByLabel('True', { exact: true })).toBeChecked();
});

test('answers the server never confirmed are restored after a reload', async ({ page }) => {
  await demoLogin(page, 'student');
  await openQuiz(page);
  await page.getByRole('link', { name: 'Resume quiz' }).click();
  // Saving fails (as on a connection that keeps dropping) while the page itself still loads.
  await page.route('**/attempt', (route) => (route.request().method() === 'POST' ? route.abort() : route.continue()));
  const term = page.locator('.question').nth(3).locator('input[type="text"]');
  await term.fill('alleles');
  await expect(page.locator('[data-save-status]')).toContainText('kept on this device', { timeout: 10_000 });

  await page.unroute('**/attempt');
  page.once('dialog', (dialog) => dialog.accept());
  await page.reload();
  await expect(page.locator('[data-connection]')).toContainText('restored');
  await expect(term).toHaveValue('alleles');
  await expect(page.locator('[data-save-status]')).toContainText('saved', { timeout: 20_000 });
  expect(await kept(page)).toEqual([]);
});

test('submitting while offline waits until the connection is back', async ({ page, context }) => {
  await demoLogin(page, 'student');
  await openQuiz(page);
  await page.getByRole('link', { name: 'Resume quiz' }).click();

  await context.setOffline(true);
  await confirmClick(page, page.getByRole('button', { name: 'Submit quiz' }));
  await expect(page.locator('[data-connection]')).toContainText('not submitted yet');
  await expect(page).toHaveURL(/\/attempt$/);

  await context.setOffline(false);
  await confirmClick(page, page.getByRole('button', { name: 'Submit quiz' }));
  await expect(page).toHaveURL(/\/student\/submissions\/\d+$/);
  const saved = page.locator('section', { has: page.locator('#saved-title') }).locator('li');
  await expect(saved).toHaveCount(5);
  await expect(saved.nth(0)).toContainText('Saved');
  await expect(saved.nth(1)).toContainText('Saved');
  await expect(saved.nth(2)).toContainText('No answer saved');
  await expect(saved.nth(3)).toContainText('Saved');
});

test('signing out removes answers kept on the device', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.evaluate(() => sessionStorage.setItem('sibol.quiz.999', '{"answers":{"answers[1]":"B"}}'));
  await page.locator('summary[aria-label="Account menu"]').click();
  await page.getByRole('menuitem', { name: 'Sign out' }).click();
  await expect(page.getByRole('button', { name: 'Sign in', exact: true })).toBeVisible();
  expect(await kept(page)).toEqual([]);
});

test('when time runs out offline, the receipt shows what counted and the teacher can reopen it', async ({ page, browser, context }) => {
  const username = 'gabriel.torres';
  await passwordLogin(page, username);
  await expect(page).toHaveURL(/\/student$/);
  await openQuiz(page);
  await confirmClick(page, page.getByRole('button', { name: 'Start quiz' }));
  const questions = page.locator('.question');
  await questions.nth(0).getByLabel(/1 in 4/).check();
  await expect(page.locator('[data-save-status]')).toContainText('saved');

  // A few seconds left, then the connection drops and another answer is given offline.
  moveDeadline(username, 8);
  await page.reload();
  await context.setOffline(true);
  await questions.nth(1).getByLabel('True', { exact: true }).check();
  await expect(page.locator('[data-connection]')).toContainText('Time is up', { timeout: 20_000 });

  // The connection comes back long after the deadline: only the answer saved in time counts.
  moveDeadline(username, -120);
  await context.setOffline(false);
  await expect(page).toHaveURL(/\/student\/submissions\/\d+$/, { timeout: 20_000 });
  const submission = page.url().match(/submissions\/(\d+)$/)[1];
  const saved = page.locator('section', { has: page.locator('#saved-title') }).locator('li');
  await expect(saved.nth(0)).toContainText('Saved');
  await expect(saved.nth(1)).toContainText('No answer saved');
  await expect(page.getByText('they can reopen the attempt')).toBeVisible();
  expect(await kept(page)).toEqual([]);

  // The teacher reopens it with ten minutes, which start when Gabriel opens it.
  const teacher = await browser.newPage();
  await demoLogin(teacher, 'teacher');
  await teacher.goto(`teacher/submissions/${submission}`);
  await teacher.getByLabel('Minutes to finish').fill('10');
  await confirmClick(teacher, teacher.getByRole('button', { name: 'Reopen for the student' }));
  await expect(teacher.locator('.toast').first()).toContainText('Reopened for Gabriel Torres');
  await teacher.close();

  await openQuiz(page);
  await page.getByRole('link', { name: 'Resume quiz' }).click();
  await expect(page).toHaveURL(/\/attempt$/);
  await expect(page.locator('[data-countdown-label]')).toHaveText(/^(10|9):\d\d$/);
});
