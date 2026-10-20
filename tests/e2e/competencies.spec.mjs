import { test, expect } from '@playwright/test';
import { demoLogin, ids, statusOf } from './helpers.mjs';

// Item 17: an administrator imports the school's list of learning competencies (checked row by row first), a teacher
// tags a question and a lesson with codes from it, and the class insights and the Daily Lesson Log show them. The
// demo school's own five are labelled samples (DEMO-SCI9-...); Sibol never makes up real codes.
test.describe.configure({ mode: 'serial' });

const CSV = 'code,description,subject,grade_level,quarter\n'
  + 'E2E-SCI9-1,Describe how plants make their own food,Science,Grade 9,3\n'
  + 'E2E-SCI9-2,,Science,,\n';

test('an administrator imports the school\'s list after checking each row', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/competencies');
  await expect(page.getByRole('cell', { name: 'DEMO-SCI9-Q1-01 Sample' })).toBeVisible();
  await page.setInputFiles('#f-csv', { name: 'competencies.csv', mimeType: 'text/csv', buffer: Buffer.from(CSV) });
  await page.getByRole('button', { name: 'Check file' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Check the list');
  const rows = page.locator('tbody tr');
  await expect(rows).toHaveCount(2);
  await expect(rows.nth(0)).toContainText('Will be added');
  await expect(rows.nth(1)).toContainText('No description');
  await page.getByRole('button', { name: 'Import 1 competency' }).click();
  await expect(page.locator('.toast').first()).toContainText('1 added, 0 updated');
  await expect(page.getByRole('cell', { name: 'E2E-SCI9-1', exact: true })).toBeVisible();
});

test('only administrators manage the list', async ({ page }) => {
  await demoLogin(page, 'teacher');
  expect(await statusOf(page, 'admin/competencies')).toBe(403);
});

test('a teacher tags a question and a lesson; insights and the Daily Lesson Log show the competency', async ({ page }) => {
  await demoLogin(page, 'teacher');
  // A quiz students have taken: its questions are locked, but their competency is a tag and can change.
  await page.goto(`teacher/assignments/${ids().sciPastQuiz}/edit`);
  const question = page.locator('li.question').first();
  await question.locator('summary').click();
  const code = question.getByLabel('Learning competency');
  await expect(code).toHaveValue('DEMO-SCI9-Q1-01');
  await code.fill('NOT-ON-THE-LIST');
  await question.getByRole('button', { name: 'Save competency' }).click();
  await expect(page.locator('.toast').first()).toContainText('"NOT-ON-THE-LIST" is not on your school\'s list of competencies for this class.');
  await page.locator('li.question').first().locator('summary').click();
  await page.locator('li.question').first().getByLabel('Learning competency').fill('e2e-sci9-1');
  await page.locator('li.question').first().getByRole('button', { name: 'Save competency' }).click();
  await expect(page.locator('.toast').first()).toContainText('Question tagged with E2E-SCI9-1.');

  await page.goto(`teacher/insights/${ids().classes.sciNarra}`);
  const row = page.locator('#competencies tbody tr', { hasText: 'E2E-SCI9-1' });
  await expect(row).toContainText('Describe how plants make their own food');
  await expect(row.locator('td').nth(2)).toHaveText('1');
  await expect(row).toContainText('10 students');

  await page.goto(`teacher/lessons/${ids().lessonGasExchange}`);
  await page.locator('.competency-pick summary').click();
  await page.getByLabel(/^E2E-SCI9-1/).check();
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.locator('.toast').first()).toContainText('Draft saved');
  await expect(page.locator('section[aria-labelledby="lc-title"]')).toContainText('DEMO-SCI9-Q1-01, E2E-SCI9-1');
  await page.goto(`teacher/lessons/${ids().lessonGasExchange}/dll`);
  const codes = page.locator('.dll__codes li');
  await expect(codes).toHaveCount(2);
  await expect(codes.nth(1)).toContainText('E2E-SCI9-1 Describe how plants make their own food');

  // Back as the demo school had them, for the tests that follow.
  await page.goto(`teacher/lessons/${ids().lessonGasExchange}`);
  await page.locator('.competency-pick summary').click();
  await page.getByLabel(/^E2E-SCI9-1/).uncheck();
  await page.getByRole('button', { name: 'Save draft' }).click();
  await expect(page.locator('.toast').first()).toContainText('Draft saved');
  await page.goto(`teacher/assignments/${ids().sciPastQuiz}/edit`);
  await page.locator('li.question').first().locator('summary').click();
  await page.locator('li.question').first().getByLabel('Learning competency').fill('DEMO-SCI9-Q1-01');
  await page.locator('li.question').first().getByRole('button', { name: 'Save competency' }).click();
  await expect(page.locator('.toast').first()).toContainText('Question tagged with DEMO-SCI9-Q1-01.');
});
