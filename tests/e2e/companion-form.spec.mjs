import { test, expect } from '@playwright/test';
import { demoLogin } from './helpers.mjs';

// The companion's "new session" form follows the kind of help, and material lists follow the chosen class.
test('each kind of help asks its own question', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('companion/new');
  const hint = page.locator('#f-question-hint');
  const box = page.getByLabel('Your first message');
  const level = page.getByLabel('Explanation level');
  await expect(hint).toHaveText('What should it explain?');
  await expect(level).toBeVisible();

  await page.locator('label.mode-option', { hasText: 'Make a study plan' }).click();
  await expect(hint).toHaveText('What test or deadline is coming, and when are you free?');
  await expect(box).toHaveAttribute('placeholder', 'For example: Science quiz on Friday. I am free after 5 PM.');
  await expect(level).toBeHidden();

  await page.locator('label.mode-option', { hasText: 'Vocabulary and translation' }).click();
  await expect(hint).toHaveText('Which words or terms?');

  await page.locator('label.mode-option', { hasText: 'Explain a topic' }).click();
  await expect(level).toBeVisible();
});

test("the companion's material list offers only the chosen class's materials", async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('companion/new');
  const material = page.getByLabel('Focus on one material');
  await expect(material).toBeDisabled();
  await expect(material.locator('option').first()).toHaveText('Choose a class first');

  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'English 9 – Narra' });
  await expect(material).toBeEnabled();
  let titles = await material.locator('option').allTextContents();
  expect(titles).toContain('Making inferences while reading');
  expect(titles).not.toContain('Quadratic equations: four methods');

  // Changing the class drops a material from the old class.
  await material.selectOption({ label: 'Making inferences while reading' });
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Mathematics 9 – Narra' });
  await expect(material).toHaveValue('');
  titles = await material.locator('option').allTextContents();
  expect(titles).toContain('Quadratic equations: four methods');
  expect(titles).not.toContain('Making inferences while reading');
});

test("Practice offers only the chosen class's materials", async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/learning');
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Mathematics 9 – Narra' });
  const titles = await page.getByLabel('Material').locator('option').allTextContents();
  expect(titles).toContain('Quadratic equations: four methods');
  expect(titles).not.toContain('Making inferences while reading');
});
