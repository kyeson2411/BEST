import { test, expect } from '@playwright/test';
import { demoLogin } from './helpers.mjs';

// A teacher plans a lesson with the companion and saves the answer as a lesson draft for review.
test('a "Plan a lesson" answer is saved as a lesson draft', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('companion/new');
  await page.locator('label.mode-option', { hasText: 'Plan a lesson' }).click();
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.getByLabel('Your first message').fill('Grade 9 Science, how we breathe, 60 minutes');
  await page.getByRole('button', { name: 'Start session' }).click();

  const answer = page.locator('.msg--assistant').last();
  await expect(answer).toContainText('Demo mode', { timeout: 30_000 });
  await answer.getByRole('button', { name: 'Save as lesson draft' }).click();

  await expect(page).toHaveURL(/teacher\/lessons\/\d+$/);
  await expect(page.locator('.toast').first()).toContainText('Saved as a lesson draft');
  await expect(page.getByText('Draft', { exact: true }).first()).toBeVisible();
  await expect(page.locator('body')).toContainText('Breathing and circulation notes');
});

// An "Explain for my class" briefing is for the teacher; "Turn into a lesson" asks for a lesson for students built on it.
test('an "Explain for my class" briefing turns into a lesson that can be saved', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('companion/new');
  await page.locator('label.mode-option', { hasText: 'Explain for my class' }).click();
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.getByLabel('Your first message').fill('Help me prepare: how we breathe');
  await page.getByRole('button', { name: 'Start session' }).click();

  const briefing = page.locator('.msg--assistant').last();
  await expect(briefing).toContainText('Demo mode', { timeout: 30_000 });
  await expect(briefing.getByRole('button', { name: 'Save as lesson draft' })).toHaveCount(0);
  await briefing.getByRole('button', { name: 'Turn into a lesson' }).click();

  await expect(page.locator('.msg--user').last()).toContainText('Turn the plan above into a lesson for my students');
  const lesson = page.locator('.msg--assistant').last();
  await expect(lesson.getByRole('button', { name: 'Save as lesson draft' })).toBeVisible({ timeout: 30_000 });
  await expect(page.locator('.msg--assistant').first().getByRole('button', { name: 'Save as lesson draft' })).toHaveCount(0);
  await lesson.getByRole('button', { name: 'Save as lesson draft' }).click();
  await expect(page).toHaveURL(/teacher\/lessons\/\d+$/);
});
