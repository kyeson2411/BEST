import { test, expect } from '@playwright/test';
import { confirmClick, demoLogin, ids, inDays } from './helpers.mjs';

// Online work is not published with a due date that has already passed (students would get it already missing or
// late); choosing a new date in the future lets it publish.
const TITLE = 'Past-due assignment (browser test)';

test('online work with a past due date is not published until the date is moved', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/assignments/new?class_id=${ids().classes.sciNarra}`);
  await page.getByLabel('Title').fill(TITLE);
  await page.getByLabel('Due', { exact: true }).fill(inDays(-1));
  await page.getByRole('button', { name: 'Create draft' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toContainText(TITLE);
  await expect(page.locator('#publish-problems')).toContainText('The due date has already passed. Choose a new due date');

  await confirmClick(page, page.getByRole('button', { name: 'Publish' }));
  await expect(page.locator('.toast').first()).toContainText('Not published yet: The due date has already passed.');
  await expect(page.locator('.page-lead')).toContainText('Draft');

  await page.getByLabel('Due', { exact: true }).fill(inDays(3));
  await page.getByRole('button', { name: 'Save settings' }).click();
  await expect(page.locator('#publish-problems')).toHaveCount(0);
  await confirmClick(page, page.getByRole('button', { name: 'Publish' }));
  await expect(page.locator('.toast').first()).toContainText('Published.');
});
