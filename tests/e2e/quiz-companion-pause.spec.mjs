import { test, expect } from '@playwright/test';
import { confirmClick, passwordLogin } from './helpers.mjs';

// A quiz offers studying before it, never hints, and the companion and AI practice pause while it is being taken.
test('the companion pauses while a student takes a quiz and comes back when it is submitted', async ({ page }) => {
  await passwordLogin(page, 'patricia.lim');
  await expect(page).toHaveURL(/\/student$/);
  await page.goto('student/assignments');
  await page.getByRole('link', { name: /Heredity basics/ }).first().click();
  await expect(page.getByRole('link', { name: 'Study this topic' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Get a hint' })).toHaveCount(0);

  await confirmClick(page, page.getByRole('button', { name: 'Start quiz' }));
  await expect(page.locator('#quiz-form')).toBeVisible();
  const attempt = page.url();

  await page.goto('companion');
  await expect(page.getByText('paused while you take the quiz “Heredity basics”')).toBeVisible();
  await page.goto('student/learning');
  await expect(page.getByText('paused while you take the quiz “Heredity basics”')).toBeVisible();

  await page.goto(attempt);
  await confirmClick(page, page.getByRole('button', { name: 'Submit quiz' }));
  await expect(page).toHaveURL(/\/student\/submissions\/\d+$/);
  await page.goto('companion');
  await expect(page.getByText('paused while you take the quiz')).toHaveCount(0);
  await expect(page.getByText('New session').first()).toBeVisible();
});
