import { test, expect } from '@playwright/test';
import { demoLogin, ids, passwordLogin, statusOf } from './helpers.mjs';

// Item 10: a teacher's comment bank. Comments are saved once and inserted while grading; only their owner sees them.
test.describe.configure({ mode: 'serial' });

test('a teacher inserts saved comments while grading and saves a new one without leaving the page', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('teacher/comments');
  await page.locator('#cb-body').fill('Quote the data that supports your claim.');
  await page.getByRole('button', { name: 'Save comment' }).click();
  await expect(page.locator('.toast').first()).toContainText('Comment saved to your bank');

  await page.goto(`teacher/submissions/${ids().beaSubmission}`);
  const bank = page.locator('[data-comment-bank]');
  await bank.locator('summary').click();
  const overall = page.getByLabel('Overall comment');
  await overall.fill('Good work.');
  await bank.getByRole('button', { name: /Quote the data that supports your claim/ }).click();
  await expect(overall).toHaveValue('Good work.\nQuote the data that supports your claim.');

  // It goes into the comment box the teacher was last writing in.
  const questionComment = page.locator('input[name^="comments["]').first();
  await questionComment.click();
  await bank.getByRole('button', { name: /Quote the data that supports your claim/ }).click();
  await expect(questionComment).toHaveValue('Quote the data that supports your claim.');

  await overall.fill('Your diagram is clear and well labelled.');
  await bank.getByRole('button', { name: 'Save the comment you are writing' }).click();
  await expect(page.locator('.toast').first()).toContainText('Saved to your comment bank');
  await expect(bank.getByRole('button', { name: /Your diagram is clear and well labelled/ })).toBeVisible();

  page.once('dialog', (dialog) => dialog.accept());
  await page.goto('teacher/comments');
  await expect(page.locator('main')).toContainText('Your diagram is clear and well labelled.');
  await expect(page.locator('main')).toContainText('Used 2 times');
});

test('another teacher cannot see or change someone\'s comments', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('teacher/comments');
  const edit = page.getByRole('link', { name: /^Edit/ }).first();
  const id = (await edit.getAttribute('href')).match(/edit=(\d+)/)[1];

  await page.context().clearCookies();
  await passwordLogin(page, 'joy.santiago@demo.sibol.test');
  await expect(page).toHaveURL(/\/teacher$/);
  await page.goto('teacher/comments');
  await expect(page.locator('main')).not.toContainText('Quote the data that supports your claim.');
  expect(await statusOf(page, `teacher/comments?edit=${id}`)).toBe(404);
  const response = await page.request.post(`teacher/comments/${id}/delete`, {
    form: { _token: await page.locator('meta[name="csrf-token"]').getAttribute('content') },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(404);
});
