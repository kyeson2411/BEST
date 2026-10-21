import { test, expect } from '@playwright/test';
import { demoLogin, ids, inDays } from './helpers.mjs';

// In a school that weighs categories, work without one counts in no grade: the form says so, and the work's page
// warns until the teacher chooses a category.
const TITLE = 'Quiz without a category (browser test)';

test('a teacher is told that work without a grading category is not counted, until they choose one', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/assignments/new?kind=quiz&class_id=${ids().classes.sciNarra}`);
  await expect(page.getByText('Work without a category is not counted in the gradebook.')).toBeVisible();
  await page.getByLabel('Title').fill(TITLE);
  await page.getByLabel('Due', { exact: true }).fill(inDays(5));
  await page.getByRole('button', { name: 'Create draft' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toContainText(TITLE);
  const workPath = new URL(page.url()).pathname.replace(/^.*\/teacher\//, 'teacher/').replace(/\/edit$/, '');

  await page.goto(workPath);
  await expect(page.getByText('Not counted in the gradebook yet')).toBeVisible();
  await page.getByRole('link', { name: 'Choose a category' }).click();
  await page.getByLabel('Grading category').selectOption({ label: 'Written work (40%)' });
  await page.getByRole('button', { name: 'Save settings' }).click();

  await page.goto(workPath);
  await expect(page.getByText('Not counted in the gradebook yet')).toHaveCount(0);
  await expect(page.locator('.dl')).toContainText('Written work');
});
