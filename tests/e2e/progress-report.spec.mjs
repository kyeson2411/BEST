import { test, expect } from '@playwright/test';
import { demoLogin, ids, passwordLogin, statusOf } from './helpers.mjs';

// Item 8: progress reports per grading period. A student prints their own; the section's adviser prints the
// section's subjects for every student; other teachers get a 404, and opening reports is audit-logged.
test.describe.configure({ mode: 'serial' });

test('a student prints their own progress report, marked as not an official report card', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/progress');
  await page.getByRole('link', { name: 'Progress report' }).click();
  await expect(page).toHaveURL(/student\/progress\/report$/);
  const sheet = page.locator('article.report');
  await expect(sheet).toHaveCount(1);
  await expect(sheet).toContainText('Not an official report card (SF9)');
  await expect(sheet).toContainText('Andrea Cruz');
  await expect(sheet.getByRole('row', { name: /Science 9 – Narra/ })).toBeVisible();
  await expect(sheet).toContainText('Strong inference.');
  await expect(page.getByRole('button', { name: 'Print' })).toBeVisible();
});

test('an adviser prints one sheet per student of the section, and other teachers get a 404', async ({ page }) => {
  const narra = ids().sections.narra;
  const andrea = ids().users.andrea;
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/advisory/${narra}`);
  const students = await page.locator('table tbody tr').count();
  await page.getByRole('link', { name: 'Progress reports' }).click();
  await expect(page.locator('article.report')).toHaveCount(students);
  await expect(page.locator('article.report').first()).toContainText('Grade 9 – Narra');

  await page.goto(`teacher/advisory/${narra}`);
  await page.getByRole('link', { name: /^Progress report\W+Andrea Cruz$/ }).click();
  await expect(page.locator('article.report')).toHaveCount(1);
  await expect(page.locator('article.report')).toContainText('Andrea Cruz');
  await expect(page.locator('article.report')).toContainText('Ramon Dizon');

  // Joy Santiago teaches English to the section but is not its adviser.
  await page.context().clearCookies();
  await passwordLogin(page, 'joy.santiago@demo.sibol.test');
  await expect(page).toHaveURL(/\/teacher$/);
  expect(await statusOf(page, `teacher/advisory/${narra}/reports`)).toBe(404);
  expect(await statusOf(page, `teacher/advisory/${narra}/students/${andrea}/report`)).toBe(404);
});

test('opening reports is in the audit log, without what they show', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/audit');
  await expect(page.getByText(/Opened \d+ progress reports?/).first()).toBeVisible();
});
