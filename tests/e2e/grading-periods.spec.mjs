import { execFileSync } from 'node:child_process';
import { test, expect } from '@playwright/test';
import { confirmClick, demoLogin, ids } from './helpers.mjs';
import { php } from './global-setup.mjs';

// Item 7: an administrator starts from DepEd Order No. 8, s. 2015 (four quarters, weights by subject group, its
// transmutation table), checks the table before switching it on; teachers see quarter and final grades and can
// place work in a quarter; students see their quarter grades.
test.describe.configure({ mode: 'serial' });
test.afterAll(() => { execFileSync(php, ['tests/e2e/grading-reset.php'], { encoding: 'utf8' }); });

test('an administrator loads DepEd 2015, checks its table and groups the subjects', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/grading');
  const preset = page.locator('form.preset', { hasText: 'DepEd Order No. 8, s. 2015' });
  await expect(preset.getByLabel(/into four quarters/)).toBeChecked();
  await confirmClick(page, preset.getByRole('button', { name: 'Load template' }));
  await expect(page.locator('.toast').first()).toContainText('with 4 grading periods');
  await expect(page.locator('#periods')).toContainText('Quarter 4');

  const table = page.locator('#transmutation');
  await expect(table.locator('.badge')).toHaveText('Off');
  await expect(table.locator('table')).toContainText('98.40 – 99.99');
  // Switching the table on is refused until someone has checked it.
  await table.getByLabel('Use this table for grading period grades').check();
  await table.getByRole('button', { name: 'Save table' }).click();
  await expect(page.getByText(/Tick that you have checked every line/)).toBeVisible();
  await table.getByLabel('I have checked every line against the official order').check();
  await table.getByLabel('Use this table for grading period grades').check();
  await table.getByRole('button', { name: 'Save table' }).click();
  await expect(page.locator('.toast').first()).toContainText('switched on');
  await expect(page.locator('#transmutation .badge')).toHaveText('On');

  await page.getByLabel('Science', { exact: true }).selectOption({ label: 'Mathematics and Science' });
  await page.getByRole('button', { name: 'Save subject groups' }).click();
  await expect(page.locator('.toast').first()).toContainText('Subject groups saved');
  await page.goto('admin/audit');
  await expect(page.getByText('Switched the transmutation table on').first()).toBeVisible();

  // With the groups named, their weights table is wider than a phone: it scrolls inside its frame, not the page.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.goto('admin/grading');
  expect(await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth)).toBeLessThanOrEqual(0);
});

test('a teacher sees quarter and final grades, exports them and places work in a quarter', async ({ page }) => {
  await demoLogin(page, 'teacher');
  const gradebook = `teacher/gradebook/${ids().classes.sciNarra}`;
  await page.goto(gradebook);
  const tabs = page.getByRole('navigation', { name: 'Grading periods' });
  await expect(tabs.locator('[aria-current="page"]')).toHaveText(/^Quarter \d$/);
  await expect(page.locator('main')).toContainText('Marks use your school\'s weights for Mathematics and Science.');
  await expect(page.locator('table.gradebook thead')).toContainText('Initial grade');

  await tabs.getByRole('link', { name: 'Final grades' }).click();
  await expect(page.locator('table.gradebook thead')).toContainText('Final grade');
  await expect(page.locator('table.gradebook tbody')).toContainText(/so far, from 1 period/);

  const csv = await (await page.request.get(`${gradebook}/export`)).text();
  const header = csv.split('\n')[0];
  expect(header).toContain('Quarter 1 initial grade');
  expect(header).toContain('Quarter 4 grade');
  expect(header).toContain('Final grade');

  // The quiz was due this quarter; its teacher counts it in Quarter 1 instead.
  await page.goto(`teacher/assignments/${ids().sciPastQuiz}/edit`);
  await page.getByLabel('Grading period').selectOption({ index: 1 });
  await page.getByRole('button', { name: 'Save settings' }).click();
  await page.goto(gradebook);
  await tabs.getByRole('link', { name: 'Quarter 1' }).click();
  await expect(page.locator('table.gradebook thead')).toContainText('Respiratory and circulatory systems');
});

test('a student sees their quarter grades and the final grade so far', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/progress');
  const science = page.locator('article', { hasText: 'Science 9 – Narra' });
  await expect(science.locator('.period-grades')).toContainText('Quarter 1');
  await expect(science.locator('.period-grades')).toContainText('Final grade so far');
});
