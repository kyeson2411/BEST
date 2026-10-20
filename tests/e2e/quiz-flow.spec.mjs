import { test, expect } from '@playwright/test';
import { confirmClick, demoLogin, ids, inDays } from './helpers.mjs';

// Flows B–D: a teacher builds a quiz (one written question, AI-drafted questions that must be
// reviewed), publishes it; a student takes it and gets a receipt; the teacher releases the
// grade; the student sees the released result.
test.describe.configure({ mode: 'serial' });

const TITLE = 'Gas exchange check (browser test)';
let assignmentPath = '';

test('a teacher builds and publishes a quiz with reviewed AI drafts', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/assignments/new?kind=quiz&class_id=${ids().classes.sciNarra}`);
  await page.getByLabel('Title').fill(TITLE);
  await page.getByLabel('Instructions for students').fill('Answer every question. You have one attempt.');
  await page.getByLabel('Due', { exact: true }).fill(inDays(3));
  await page.getByRole('button', { name: 'Create draft' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toContainText(TITLE);
  assignmentPath = new URL(page.url()).pathname.replace(/^.*\/teacher\//, 'teacher/').replace(/\/edit$/, '');

  // A question written by the teacher.
  await page.getByText('Add a question').click();
  const form = page.locator('form', { has: page.getByRole('button', { name: 'Add question' }) });
  await form.getByLabel('Question', { exact: true }).fill('Where does gas exchange happen in the lungs?');
  await form.getByLabel('Choice A', { exact: true }).fill('In the trachea');
  await form.getByLabel('Choice B', { exact: true }).fill('In the alveoli');
  await form.getByLabel('Choice C', { exact: true }).fill('In the bronchi');
  await form.getByLabel('Choice B is correct').check();
  await form.getByRole('button', { name: 'Add question' }).click();
  await expect(page.locator('.question__prompt', { hasText: 'Where does gas exchange happen in the lungs?' })).toBeVisible();

  // Drafted questions wait for review and block publishing until approved.
  const panel = page.locator('section', { has: page.getByRole('heading', { name: 'Draft questions from materials' }) });
  await panel.getByLabel('Breathing and circulation notes').check();
  await panel.getByLabel('Multiple choice').fill('2');
  await panel.getByLabel('True or false').fill('1');
  await panel.getByLabel('Short answer').fill('0');
  await panel.getByRole('button', { name: 'Draft questions' }).click();
  await expect(page.getByText('AI draft: needs review').first()).toBeVisible();
  await expect(page.locator('#publish-problems')).toBeVisible();

  await confirmClick(page, page.getByRole('button', { name: 'I have checked them all: approve all' }));
  await expect(page.getByText('AI draft: needs review')).toHaveCount(0);

  await confirmClick(page, page.getByRole('button', { name: 'Publish', exact: true }));
  await expect(page.locator('.toast').first()).toBeVisible();
  await page.goto(assignmentPath);
  await expect(page.getByText('Published').first()).toBeVisible();
});

test('a student takes the quiz and gets a receipt', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/assignments');
  await page.getByRole('link', { name: TITLE }).click();
  await confirmClick(page, page.getByRole('button', { name: 'Start quiz' }));
  await expect(page.locator('#quiz-form')).toBeVisible();

  await page.getByLabel('In the alveoli').check();
  // Answer the remaining (drafted) questions with the first option so every question has a response.
  for (const group of await page.locator('#quiz-form fieldset').all()) {
    const radios = group.locator('input[type=radio]');
    if ((await radios.count()) && !(await group.locator('input[type=radio]:checked').count())) {
      await radios.first().check();
    }
  }
  await confirmClick(page, page.getByRole('button', { name: 'Submit quiz' }));
  await expect(page.getByRole('heading', { name: 'Submission receipt' })).toBeVisible();
  await expect(page.getByText('Receipt code', { exact: true })).toBeVisible();
});

test('the teacher releases the grade and the student sees it', async ({ page, browser }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`${assignmentPath}/submissions`);
  await page.getByRole('link', { name: /Andrea Cruz/ }).first().click();
  await page.getByLabel('Overall comment').fill('Well done on the alveoli question.');
  await page.getByRole('button', { name: /Save and release/ }).click();
  await expect(page.getByText('Released').first()).toBeVisible();

  const student = await browser.newPage();
  await demoLogin(student, 'student');
  // Finished work moves out of the "to do" list, so open the quiz directly.
  await student.goto(assignmentPath.replace('teacher/', 'student/'));
  await expect(student.getByText('Well done on the alveoli question.')).toBeVisible();
  await student.close();
});
