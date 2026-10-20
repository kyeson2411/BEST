import { test, expect } from '@playwright/test';
import { confirmClick, demoLogin, ids, inDays } from './helpers.mjs';

// Maths written as \( … \) or \[ … \] is typeset with KaTeX. Its files (about 300 KB) are fetched only when a
// page actually shows maths, so other pages stay light on mobile data (item 23).
test('maths is typeset where it appears, and pages without maths do not download KaTeX', async ({ page }) => {
  const fetched = [];
  page.on('request', (r) => { if (r.url().includes('/vendor/katex/')) fetched.push(r.url()); });
  await demoLogin(page, 'teacher');

  // A quiz page without maths does not fetch KaTeX.
  await page.goto(`teacher/assignments/${ids().sciPastQuiz}`);
  await page.waitForLoadState('load');
  expect(fetched).toEqual([]);

  // A question with maths: KaTeX arrives and typesets it.
  await page.goto(`teacher/assignments/new?kind=quiz&class_id=${ids().classes.sciNarra}`);
  await page.getByLabel('Title').fill('Maths check (browser test)');
  await page.getByLabel('Due', { exact: true }).fill(inDays(3));
  await page.getByRole('button', { name: 'Create draft' }).click();
  await page.getByText('Add a question').click();
  const form = page.locator('form', { has: page.getByRole('button', { name: 'Add question' }) });
  await form.getByLabel('Question', { exact: true }).fill('Which values of x solve \\(x^2 - 5x + 6 = 0\\)?');
  await form.getByLabel('Choice A', { exact: true }).fill('2 and 3');
  await form.getByLabel('Choice B', { exact: true }).fill('1 and 6');
  await form.getByLabel('Choice A is correct').check();
  await form.getByRole('button', { name: 'Add question' }).click();
  const prompt = page.locator('.question__prompt', { hasText: 'Which values of x solve' });
  await expect(prompt.locator('.katex').first()).toBeVisible();
  expect(fetched.some((url) => url.includes('katex.min.js'))).toBe(true);

  await confirmClick(page, page.getByRole('button', { name: 'Delete draft' }));
  await expect(page.locator('.toast').first()).toBeVisible();
});
