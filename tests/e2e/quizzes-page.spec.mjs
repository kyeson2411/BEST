import { test, expect } from '@playwright/test';
import { demoLogin } from './helpers.mjs';

// Quizzes have their own menu item, for students and teachers, instead of sharing one list with assignments.
test('a student finds quizzes under Quizzes, each tagged, and a quiz lights up Quizzes', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.locator('.spine .nav-link', { hasText: 'Quizzes' }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'Quizzes' })).toBeVisible();
  await page.getByRole('link', { name: /^All \(/ }).click();
  const quiz = page.locator('.entry', { hasText: 'Heredity basics' });
  await expect(quiz.locator('.tag--quiz')).toHaveText('Quiz');
  await expect(page.locator('.entries .tag--assignment')).toHaveCount(0);

  await quiz.getByRole('link', { name: 'Heredity basics', exact: true }).click();
  await expect(page.locator('.crumbs').getByRole('link', { name: 'Quizzes' })).toBeVisible();
  await expect(page.locator('.spine .nav-link[aria-current="page"]')).toContainText('Quizzes');

  await page.locator('.spine .nav-link', { hasText: 'Assignments' }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'Assignments' })).toBeVisible();
  await page.getByRole('link', { name: /^All \(/ }).click();
  await expect(page.locator('.entries .tag--quiz')).toHaveCount(0);
  await expect(page.getByRole('link', { name: 'Quizzes are on their own page.' })).toBeVisible();
});

test('a teacher has a Quizzes page with its own New quiz button', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.locator('.spine .nav-link', { hasText: 'Quizzes' }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'Quizzes' })).toBeVisible();
  await expect(page.getByRole('link', { name: 'Heredity basics' })).toBeVisible();
  await expect(page.locator('tbody .tag--assignment, tbody .tag--paper')).toHaveCount(0);
  await page.getByRole('link', { name: 'New quiz' }).click();
  await expect(page.getByRole('heading', { level: 1, name: 'New quiz' })).toBeVisible();
  await expect(page.locator('.spine .nav-link[aria-current="page"]')).toContainText('Quizzes');
});
