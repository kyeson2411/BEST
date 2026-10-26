import { test, expect } from '@playwright/test';
import { demoLogin, ids, passwordLogin, statusOf } from './helpers.mjs';

// Item 13: a lesson printed in the DepEd Daily Lesson Log layout, for the class's teachers only.
test('a teacher prints a lesson as a Daily Lesson Log', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('teacher/lessons');
  await page.getByRole('link', { name: 'Gas exchange in the alveoli' }).first().click();
  await page.getByRole('link', { name: 'Daily Lesson Log' }).click();
  await expect(page).toHaveURL(/teacher\/lessons\/\d+\/dll$/);
  const log = page.locator('article.dll');
  await expect(log.getByRole('row', { name: /C\. Learning competencies or objectives/ })).toContainText('Describe the structure of the alveoli.');
  await expect(log.getByRole('row', { name: /^Subject matter/ })).toContainText('Gas exchange in the alveoli');
  await expect(log).toContainText('IV. Procedures');
  await expect(log).toContainText('VI. Reflection');
  await expect(page.getByText(/Check this layout against your school's own DLL template/)).toBeVisible();
  await expect(page.getByRole('button', { name: 'Print' })).toBeVisible();
  const path = new URL(page.url()).pathname.replace(/^.*?\/teacher\//, 'teacher/');

  // Joy Santiago does not teach this class.
  await page.context().clearCookies();
  await passwordLogin(page, 'joy.santiago@demo.sibol.test');
  await expect(page).toHaveURL(/\/teacher$/);
  expect(await statusOf(page, path)).toBe(404);
});

// Students read the lesson; the teacher's plan for class time (activities, checks, exit ticket) stays with the teacher.
test('the teaching plan is folded away in the editor, prints in the log, and never reaches students', async ({ page }) => {
  const lesson = ids().lessonGasExchange;
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/lessons/${lesson}`);
  await expect(page.getByRole('group', { name: 'What students read' }).getByLabel('Explanation')).toHaveValue(/one cell thick/);
  const plan = page.locator('details.lesson-plan');
  await expect(plan.locator('summary')).toContainText('Only you see this');
  await expect(plan.locator('summary')).toContainText('3 of 3 parts written');
  await expect(page.getByLabel(/^Exit ticket/)).toBeHidden();
  await plan.locator('summary').click();
  await expect(page.getByLabel(/^Exit ticket/)).toHaveValue(/alveolar wall is only one cell thick/);
  await page.goto(`teacher/lessons/${lesson}/dll`);
  await expect(page.locator('article.dll').getByRole('row', { name: /^I\. Evaluating learning/ })).toContainText('alveolar wall is only one cell thick');

  await page.context().clearCookies();
  await demoLogin(page, 'student');
  await page.goto(`student/classes/${ids().classes.sciNarra}/lessons/${lesson}`);
  await expect(page.locator('#lesson-text')).toContainText('Each alveolus has a wall one cell thick');
  await expect(page.locator('#lesson-text')).not.toContainText('Pairs sketch an alveolus');
  await expect(page.locator('#lesson-text')).not.toContainText('Name two features that make gas exchange fast');
  await expect(page.locator('#lesson-text')).not.toContainText('alveolar wall is only one cell thick');
});
