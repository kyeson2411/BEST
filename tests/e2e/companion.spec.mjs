import { test, expect } from '@playwright/test';
import { demoLogin } from './helpers.mjs';

// Flow E: the companion answers from class materials with sources (demo mode here, clearly
// labelled), the student reports an answer, and an administrator reviews only that answer.
test.describe.configure({ mode: 'serial' });

const NOTE = 'Browser test: please check this answer.';

test('a student gets a sourced, labelled answer and reports it', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('companion/new');
  // Mode cards hide their radio button and show focus and selection on the card itself.
  await page.locator('label.mode-option', { hasText: 'Ask my class materials' }).click();
  await expect(page.getByRole('radio', { name: /Ask my class materials/ })).toBeChecked();
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.getByLabel('Your first message').fill('Why are the walls of the alveoli so thin?');
  await page.getByRole('button', { name: 'Start session' }).click();

  const answer = page.locator('.msg--assistant').last();
  await expect(answer).toContainText('Demo mode', { timeout: 30_000 });
  await expect(page.locator('.source-tag').first()).toBeVisible();
  await expect(page.locator('.source-tag').first()).toContainText('Breathing and circulation notes');
  await expect(page.getByText('AI can make mistakes')).toBeVisible();

  // The sessions list now has a title taken from the question.
  await page.reload();
  await expect(page.locator('.companion__sessions')).toContainText('Why are the walls of the alveoli so thin?');

  await page.locator('[data-report]').last().click();
  const dialog = page.locator('dialog[open]');
  await dialog.getByLabel('It is incorrect').check();
  await dialog.getByLabel('Anything else we should know?').fill(NOTE);
  await dialog.getByRole('button', { name: 'Send report' }).click();
  await expect(page.locator('.toast').first()).toContainText('report was sent');
});

test('an administrator reviews the report and closes it', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/privacy');
  await page.getByRole('link', { name: 'Incorrect' }).first().click();
  await expect(page.getByText(NOTE)).toBeVisible();
  await expect(page.getByText('Why are the walls of the alveoli so thin?')).toHaveCount(0);
  await page.getByLabel('Message to the reporter').fill('Thanks, we checked the notes with the teacher.');
  await page.getByRole('button', { name: 'Close report' }).click();
  await expect(page.locator('.toast').first()).toContainText('Report closed');
});

test('the student is told the report was reviewed', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('notifications');
  await expect(page.getByText('The answer you reported was reviewed').first()).toBeVisible();
});
