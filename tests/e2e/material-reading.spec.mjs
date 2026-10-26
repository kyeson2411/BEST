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

// A PDF opens as its text inside Sibol; the file itself is a click away instead of a forced download.
test('a student reads a PDF material in Sibol and chooses whether to download it', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/classes');
  await page.getByRole('link', { name: 'Science 9 – Narra' }).first().click();
  await page.getByRole('link', { name: 'Materials', exact: true }).click();
  await page.getByRole('link', { name: 'Heredity notes', exact: true }).click();

  await expect(page).toHaveURL(/materials\/\d+\/read$/);
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Heredity notes');
  await expect(page.getByRole('heading', { level: 2, name: 'Page 1' })).toBeVisible();
  await expect(page.locator('.prose')).toContainText('The instructions for traits are carried by genes.');
  await expect(page.getByRole('link', { name: 'Open the PDF' })).toHaveAttribute('href', /\/file$/);
  await expect(page.getByRole('link', { name: 'Download' })).toHaveAttribute('href', /\/file\?download=1$/);
});
