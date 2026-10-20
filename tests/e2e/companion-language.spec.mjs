import { test, expect } from '@playwright/test';
import { passwordLogin } from './helpers.mjs';

// Demo answers follow the session's language and the language of the question, the same rule a
// connected model is given, and a question written in Filipino still finds the English class notes.
// Sofia is used only here.
const SOFIA = 'sofia.delacruz@demo.sibol.test';

async function startSession(page, language, question) {
  await page.goto('companion/new');
  await page.locator('label.mode-option', { hasText: 'Explain a topic' }).click();
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.locator('select[name="language"]').selectOption(language);
  await page.getByLabel('Your first message').fill(question);
  await page.getByRole('button', { name: 'Start session' }).click();
  const answer = page.locator('.msg--assistant').last();
  await expect(answer).toContainText('Demo mode', { timeout: 30_000 });
  return answer;
}

test('a Filipino session gets its demo answer in Filipino, with sources', async ({ page }) => {
  await passwordLogin(page, SOFIA);
  const answer = await startSession(page, 'fil', 'What does the diaphragm do when we breathe in?');
  await expect(answer).toContainText('walang nakakonektang AI model');
  await expect(answer).toContainText('Ang mga siping pinakatugma sa iyong tanong');
  await expect(page.locator('.source-tag').first()).toContainText('Breathing and circulation notes');
});

test('a question written in Filipino finds the English notes and is answered in Filipino', async ({ page }) => {
  await passwordLogin(page, SOFIA);
  const answer = await startSession(page, 'en', 'Ano ang ginagawa ng diaphragm kapag humihinga tayo?');
  await expect(answer).toContainText('Susunod na hakbang');
  await expect(answer).toContainText('Breathing is driven mainly by the diaphragm');
  await expect(page.locator('.source-tag').first()).toContainText('Breathing and circulation notes');
});
