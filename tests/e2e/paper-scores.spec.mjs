import { test, expect } from '@playwright/test';
import { confirmClick, demoLogin, ids, inDays, passwordLogin, statusOf } from './helpers.mjs';

// Item 20: work done in class or on paper. The teacher types scores into a grid (absent, excused,
// held or released); they count in the gradebook like online work. Any assignment prints as a blank
// copy, and the teacher's copy carries the answer key.
test.describe.configure({ mode: 'serial' });

test('a teacher enters paper scores, marks who was absent and releases them', async ({ page, browser }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/assignments/new?kind=paper&class_id=${ids().classes.sciNarra}`);
  await expect(page.getByRole('heading', { name: 'New work in class or on paper' })).toBeVisible();
  await expect(page.getByLabel('Late work')).toHaveCount(0);
  await page.getByLabel('Title').fill('Quarter quiz on paper');
  await page.getByLabel('Points').fill('10');
  await page.getByLabel('Date in class').fill(inDays(-1, 9));
  await page.getByRole('button', { name: 'Create draft' }).click();
  await expect(page).toHaveURL(/\/teacher\/assignments\/\d+\/edit$/);
  const id = page.url().match(/assignments\/(\d+)\/edit/)[1];
  await confirmClick(page, page.getByRole('button', { name: 'Publish' }));
  await page.getByRole('link', { name: 'Enter scores' }).click();
  await expect(page).toHaveURL(new RegExp(`/teacher/assignments/${id}/scores$`));

  // A score above the points is refused and nothing is saved.
  await page.getByLabel('Score for Andrea Cruz').fill('11');
  await page.getByLabel('Score for Bea Ramos').fill('7.5');
  await page.getByRole('button', { name: 'Save scores' }).click();
  await expect(page.getByText('Enter a score from 0 to 10.')).toBeVisible();
  await expect(page.getByLabel('Score for Bea Ramos')).toHaveValue('7.5');

  await page.getByLabel('Score for Andrea Cruz').fill('9');
  await page.getByLabel('Absent: Paolo Aquino').check();
  await page.getByLabel('Excused: Carlo Reyes').check();
  await page.getByRole('button', { name: 'Save scores' }).click();
  await expect(page.locator('.toast').first()).toContainText('Scores saved');
  await expect(page.getByRole('row').filter({ hasText: 'Andrea Cruz' })).toContainText('Held');
  await expect(page.getByRole('row').filter({ hasText: 'Paolo Aquino' })).toContainText('Absent');

  // The gradebook counts them like online work: held scores are marked, the absent student is missing.
  await page.goto(`teacher/gradebook/${ids().classes.sciNarra}`);
  const andreaRow = page.getByRole('row').filter({ hasText: 'Cruz, Andrea' });
  await expect(andreaRow).toContainText('9');
  await expect(page.getByRole('row').filter({ hasText: 'Aquino, Paolo' })).toContainText('absent');

  // Andrea sees the work, nothing to hand in, and no score until it is released.
  const student = await browser.newPage();
  await passwordLogin(student, 'andrea.cruz');
  await student.goto(`student/assignments/${id}`);
  await expect(student.getByText('Waiting for your score').first()).toBeVisible();
  await expect(student.getByRole('button', { name: /Submit/ })).toHaveCount(0);
  await expect(student.getByRole('heading', { name: 'Your grade and feedback' })).toHaveCount(0);

  await page.goto(`teacher/assignments/${id}/scores`);
  await confirmClick(page, page.getByRole('button', { name: 'Save and release' }));
  await expect(page.locator('.toast').first()).toContainText('2 scores released');

  await student.reload();
  await expect(student.getByRole('heading', { name: 'Your grade and feedback' })).toBeVisible();
  await expect(student.locator('.stat__value').first()).toContainText('9');
  await student.close();
});

test('the blank copy has no answers and the teacher copy has the key', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/assignments/${ids().paperTest}/print`);
  const sheet = page.locator('.print-sheet');
  await expect(sheet).toContainText('Name');
  await expect(sheet).toContainText('Which structure carries air from the larynx to the bronchi?');
  await expect(sheet).not.toContainText('Accepted answers');
  await expect(sheet.locator('.is-correct')).toHaveCount(0);

  await page.getByRole('link', { name: 'With answer key' }).click();
  await expect(sheet).toContainText('Answer key: teacher\'s copy');
  await expect(sheet).toContainText('Accepted answers: oxygen');
  await expect(sheet.locator('.is-correct')).toHaveCount(5);
  await expect(page.getByRole('button', { name: 'Print' })).toBeVisible();
});

test('students and other teachers cannot open the score grid or the key', async ({ page, browser }) => {
  await demoLogin(page, 'student');
  expect(await statusOf(page, `teacher/assignments/${ids().paperTest}/print?copy=key`)).toBe(403);
  expect(await statusOf(page, `teacher/assignments/${ids().paperTest}/scores`)).toBe(403);

  const joy = await browser.newPage();
  await passwordLogin(joy, 'joy.santiago');
  expect(await statusOf(joy, `teacher/assignments/${ids().paperTest}/print?copy=key`)).toBe(404);
  expect(await statusOf(joy, `teacher/assignments/${ids().paperTest}/scores`)).toBe(404);
  const token = await joy.locator('meta[name="csrf-token"]').getAttribute('content');
  const response = await joy.request.post(`teacher/assignments/${ids().paperTest}/scores`, { form: { _token: token, [`scores[${ids().users.andrea}]`]: '0' }, maxRedirects: 0 });
  expect(response.status()).toBe(404);
  await joy.close();
});
