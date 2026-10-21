import { test, expect } from '@playwright/test';
import { demoLogin, ids } from './helpers.mjs';

// The side box on a student's work page says what already happened, and "Get a hint" is offered only while the
// work can still be done. (Runs before extensions.spec, which holds this quiz's answers back for an extension.)
test('graded work says it was released and offers no hint; open work offers one', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto(`student/assignments/${ids().sciPastQuiz}`);
  const glance = page.locator('section', { has: page.locator('#glance-title') });
  await expect(glance).toContainText('Released');
  await expect(glance).toContainText('Shown below with your answers.');
  await expect(glance).not.toContainText('Shown when your teacher releases it.');
  await expect(page.getByRole('link', { name: 'Get a hint' })).toHaveCount(0);

  await page.goto('student/assignments');
  await page.getByRole('link', { name: /Inference journal/ }).first().click();
  await expect(page.getByRole('link', { name: 'Get a hint' })).toBeVisible();
});
