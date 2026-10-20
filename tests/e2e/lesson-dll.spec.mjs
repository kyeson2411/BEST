import { test, expect } from '@playwright/test';
import { demoLogin, passwordLogin, statusOf } from './helpers.mjs';

// Item 13: a lesson printed in the DepEd Daily Lesson Log layout, for the class's teachers only.
test('a teacher prints a lesson as a Daily Lesson Log', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('teacher/lessons');
  await page.getByRole('link', { name: 'Gas exchange in the alveoli' }).first().click();
  await page.getByRole('link', { name: 'Daily Lesson Log' }).click();
  await expect(page).toHaveURL(/teacher\/lessons\/\d+\/dll$/);
  const log = page.locator('article.dll');
  await expect(log.getByRole('row', { name: /C\. Learning competencies or objectives/ })).toContainText('Describe the structure of the alveoli.');
  await expect(log.getByRole('row', { name: /^Subject matter/ })).toContainText('Gas exchange in the alveoli');
  await expect(log).toContainText('IV. Procedures');
  await expect(log).toContainText('VI. Reflection');
  await expect(page.getByText(/Check this layout against your school's own DLL template/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Print' })).toBeVisible();
  const path = new URL(page.url()).pathname.replace(/^.*?\/teacher\//, 'teacher/');

  // Joy Santiago does not teach this class.
  await page.context().clearCookies();
  await passwordLogin(page, 'joy.santiago@demo.sibol.test');
  await expect(page).toHaveURL(/\/teacher$/);
  expect(await statusOf(page, path)).toBe(404);
});
