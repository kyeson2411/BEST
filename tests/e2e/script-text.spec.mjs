import { test, expect } from '@playwright/test';
import { passwordLogin } from './helpers.mjs';

// Text that scripts write (card counters, companion progress and status) follows the person's
// language like the rest of the page: the server translates it and passes it in data-i18n attributes.
// Janelle is used only here, and is switched back to English at the end.
test('a student reading in Filipino sees Filipino text written by the page scripts', async ({ page }) => {
  await passwordLogin(page, 'janelle.ocampo');
  await expect(page).toHaveURL(/\/student$/);

  // A flashcard deck and a companion session, made while the interface is still in English.
  await page.goto('student/learning');
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Molave' });
  await page.getByRole('button', { name: 'Flashcards', exact: true }).click();
  await expect(page.locator('[data-flashcards]')).toBeVisible();
  const deck = page.url();

  await page.goto('companion/new');
  await page.locator('label.mode-option', { hasText: 'Ask my class materials' }).click();
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Molave' });
  await page.getByLabel('Your first message').fill('Why are the walls of the alveoli so thin?');
  await page.getByRole('button', { name: 'Start session' }).click();
  await expect(page.locator('.msg--assistant').last()).toContainText('Demo mode', { timeout: 30_000 });
  const conversation = page.url();

  await page.goto('settings');
  await page.getByLabel('Language').selectOption('fil');
  await page.getByRole('button', { name: 'Save preferences' }).click();
  await expect(page.locator('html')).toHaveAttribute('lang', 'fil');

  try {
    // Flashcards: practice.js writes the counter and the side label.
    await page.goto(deck);
    const cards = page.locator('[data-flashcards]');
    await expect(cards.locator('[data-card-progress]')).toHaveText(/^Card 1 sa \d+$/);
    const card = cards.locator('[data-card]:visible');
    await card.locator('[data-flip]').click();
    await expect(card.locator('[data-side]')).toHaveText('Likod');
    await card.locator('[data-known]').click();
    await expect(cards.locator('[data-card-progress]')).toHaveText(/^Card 2 sa \d+$/);

    // Companion: companion.js writes the question's label and the status read out when the answer is ready.
    await page.goto(conversation);
    const input = page.locator('#composer-input');
    await input.fill('What carries oxygen in the blood?');
    await input.press('Enter');
    await expect(page.locator('.msg--user .msg__who').last()).toHaveText('Ikaw');
    await expect(page.locator('[data-composer-status]')).toHaveText('Handa na ang sagot.', { timeout: 30_000 });
    await expect(page.locator('.exchange--live')).toHaveCount(0);
  } finally {
    await page.goto('settings');
    await page.locator('select[name="language"]').selectOption('en');
    await page.locator('#preferences button[type="submit"]').click();
    await expect(page.locator('html')).toHaveAttribute('lang', 'en');
  }
});
