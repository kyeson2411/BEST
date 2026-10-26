import { test, expect } from '@playwright/test';
import { demoLogin, passwordLogin, ids, inDays } from './helpers.mjs';

// Extensions and excused work: the teacher gives Carlo his own due date and extra minutes on the
// past quiz he missed, and excuses Paolo. Carlo can take it; answers released "after the due date"
// wait for his extension; Paolo sees the work as excused.
test.describe.configure({ mode: 'serial' });

const QUIZ = 'Respiratory and circulatory systems check';

test('a teacher gives one student more time and excuses another', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/assignments/${ids().sciPastQuiz}/submissions`);
  await expect(page.getByRole('row', { name: /Carlo Reyes/ })).toContainText('Missing');
  await page.getByRole('row', { name: /Carlo Reyes/ }).getByRole('link', { name: /Adjust/ }).click();

  const form = page.locator('#adjust form');
  await expect(form.getByLabel('Student', { exact: true })).toHaveValue(String(ids().users.carlo));
  await form.getByLabel('Their own due date').fill(inDays(3));
  await form.getByLabel('Extra minutes').fill('10');
  await form.getByRole('button', { name: 'Save for this student' }).click();
  await expect(page.locator('.toast').first()).toContainText('Saved for Carlo Reyes');
  await expect(page.getByRole('row', { name: /Carlo Reyes/ })).toContainText('Not started');
  await expect(page.getByRole('row', { name: /Carlo Reyes/ })).toContainText('+10 minutes');

  await form.getByLabel('Student', { exact: true }).selectOption({ label: 'Paolo Aquino' });
  await form.getByLabel('Excuse this student from this work').check();
  await form.getByRole('button', { name: 'Save for this student' }).click();
  await expect(page.getByRole('row', { name: /Paolo Aquino/ })).toContainText('Excused');
  await expect(page.locator('#adjust')).toContainText('Current adjustments');
});

test('the student uses his own due date and extra minutes', async ({ page }) => {
  await passwordLogin(page, 'carlo.reyes');
  await expect(page).toHaveURL(/\/student$/);
  await expect(page.locator('main')).toContainText(QUIZ);

  await page.goto(`student/assignments/${ids().sciPastQuiz}`);
  await expect(page.getByText('Your teacher gave you more time')).toBeVisible();
  await expect(page.getByText('You have 10 extra minutes on the time limit.')).toBeVisible();
  await expect(page.locator('.dl')).toContainText('30 minutes');
  await expect(page.getByRole('button', { name: 'Start quiz' })).toBeVisible();

  await page.goto('notifications');
  await expect(page.getByText(`New due date for ${QUIZ}`)).toBeVisible();
});

test('answers released after the due date wait for the extension', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto(`student/assignments/${ids().sciPastQuiz}`);
  const review = page.locator('section', { has: page.locator('#review-title') });
  await expect(review).toContainText('Correct answers appear after the due date.');
  await expect(review.getByText('Your answer:')).toHaveCount(0);
});

test('the excused student sees the work as excused', async ({ page }) => {
  await passwordLogin(page, 'paolo.aquino');
  await expect(page).toHaveURL(/\/student$/);
  await page.goto(`student/assignments/${ids().sciPastQuiz}`);
  await expect(page.getByText('You are excused from this work')).toBeVisible();
  await page.goto('student/quizzes?filter=excused');
  await expect(page.getByRole('link', { name: QUIZ })).toBeVisible();
});

test("another teacher cannot change a class's extensions", async ({ page }) => {
  await passwordLogin(page, 'liza.mercado@demo.sibol.test');
  await expect(page).toHaveURL(/\/teacher$/);
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const response = await page.request.post(`teacher/assignments/${ids().sciPastQuiz}/overrides`, {
    form: { _token: token, student_id: String(ids().users.carlo), excused: '1' },
    maxRedirects: 0,
  });
  expect(response.status()).toBe(404);
});
