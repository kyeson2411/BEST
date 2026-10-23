import { test, expect } from '@playwright/test';
import { demoLogin } from './helpers.mjs';

// A Markdown material opens as a formatted page inside Sibol, not as bare text.
test('a student opens a text material as a page to read', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/classes');
  await page.getByRole('link', { name: 'English 9 – Narra' }).first().click();
  await page.getByRole('link', { name: 'Materials', exact: true }).click();
  await page.getByRole('link', { name: 'Making inferences while reading', exact: true }).click();

  await expect(page).toHaveURL(/materials\/\d+\/read$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Making inferences while reading');
  await expect(page.getByRole('heading', { level: 2, name: 'What an inference is' })).toBeVisible();
  await expect(page.locator('.prose')).not.toContainText('##');
  await expect(page.getByRole('link', { name: 'Download' })).toHaveAttribute('href', /\/file\?download=1$/);

  await page.getByRole('link', { name: 'English 9 – Narra' }).click();
  await expect(page).toHaveURL(/tab=materials$/);
});
