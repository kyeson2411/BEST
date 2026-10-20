import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { php } from './global-setup.mjs';
import { demoLogin, statusOf } from './helpers.mjs';

// Item 9: questions a student missed come back in a private review queue, checked by Sibol, on a schedule.
// Other tests take the demo's own upcoming quiz, so this file adds one of its own for "before a quiz".
test.describe.configure({ mode: 'serial' });
let quizId = 0;
test.beforeAll(() => { quizId = Number(execFileSync(php, ['tests/e2e/review-fixture.php'], { encoding: 'utf8' }).trim()); });
test.afterAll(() => { execFileSync(php, ['tests/e2e/review-fixture.php', '--remove'], { encoding: 'utf8' }); });

const left = async (page) => {
  const text = await page.locator('.review-item__left').textContent({ timeout: 2000 }).catch(() => null);
  return text ? Number(text.match(/\d+/)[0]) : 0;
};

/** Answers whatever item is showing: the first choice, a typed answer, or "I knew it" for a card. */
async function answerShownItem(page) {
  const item = page.locator('.review-item');
  if (await item.locator('input[type=radio]').count()) {
    await item.locator('input[type=radio]').first().check();
    await item.getByRole('button', { name: 'Check answer' }).click();
  } else if (await item.locator('#review-text').count()) {
    await item.locator('#review-text').fill('capillaries');
    await item.getByRole('button', { name: 'Check answer' }).click();
  } else {
    await item.getByRole('button', { name: 'I knew it' }).click();
  }
}

test('a student reviews quiz questions they missed, and Sibol checks the answer', async ({ page }) => {
  await demoLogin(page, 'student');
  const card = page.locator('section', { has: page.getByRole('heading', { name: /^Review today/ }) });
  await expect(card).toContainText(/questions? you missed (is|are) back for review/);
  await card.getByRole('link', { name: 'Start reviewing' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Review today');
  const before = await left(page);
  expect(before).toBeGreaterThan(0);

  await answerShownItem(page);
  const result = page.locator('.review-result');
  await expect(result).toContainText(/Right\.|Not quite\./);
  await expect(result).toContainText('The answer:');
  await expect(result).toContainText('Next review in 1 day');
  expect(await left(page)).toBe(before - 1);
});

test('before a quiz, a student reviews everything they missed in that class', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/review');
  const beforeQuiz = page.locator('section', { has: page.getByRole('heading', { name: 'Before a quiz' }) });
  const quiz = beforeQuiz.locator('li', { hasText: 'Circulation warm-up (review test)' });
  await quiz.getByRole('link', { name: /Review everything for this class/ }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Review: Science 9 – Narra');
  await expect(page.locator('.review-item')).toContainText('Science 9 – Narra');

  await page.goto(`student/assignments/${quizId}`);
  await expect(page.getByRole('link', { name: /^Review the \d+ questions? you missed in this class first$/ })).toBeVisible();
});

test('missed practice questions join the queue', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/review');
  const before = await left(page);
  await page.goto('student/learning');
  await page.getByRole('button', { name: 'Practice questions' }).click();
  await expect(page).toHaveURL(/student\/learning\/\d+$/);
  await page.getByRole('button', { name: 'Check my answers' }).click();
  const toast = page.locator('.toast').first();
  await expect(toast).toContainText(/(\d+) items? (is|are) in your review queue for today/);
  const added = Number((await toast.textContent()).match(/(\d+) items? (is|are) in your review queue/)[1]);
  await page.goto('student/review');
  expect(await left(page)).toBe(before + added);
});

test('reviews are the student\'s own: teachers cannot open them', async ({ page }) => {
  await demoLogin(page, 'teacher');
  expect(await statusOf(page, 'student/review')).toBe(403);
});
