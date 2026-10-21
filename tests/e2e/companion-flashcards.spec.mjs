import { test, expect } from '@playwright/test';
import { demoLogin, ids } from './helpers.mjs';

// The companion's Flashcards choice opens the real flip cards in Practice, with the class already picked,
// instead of starting a chat that writes cards as text.
test('Flashcards in the companion opens Practice for the class chosen there', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('companion/new');
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.locator('.mode-grid').getByRole('link', { name: /Flashcards/ }).click();

  await expect(page).toHaveURL(/\/student\/learning\?/);
  await expect(page.getByRole('heading', { level: 1, name: 'Practice' })).toBeVisible();
  await expect(page.getByLabel('Class', { exact: true })).toHaveValue(String(ids().classes.sciNarra));
  await expect(page.getByText('Check the class, then press Flashcards.')).toBeVisible();
  const make = page.getByRole('button', { name: 'Flashcards', exact: true });
  await expect(make).toBeFocused();
  await make.click();

  // Real cards: one at a time, saved as a set, marked "I knew this" or "Not yet".
  await expect(page.locator('[data-flashcards]')).toBeVisible();
  await expect(page.locator('[data-card-progress]')).toContainText('Card 1 of');
  await expect(page.getByRole('button', { name: 'I knew this' }).first()).toBeVisible();
});

test('an old link to a flashcards chat lands in Practice', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto(`companion/new?mode=flashcards&class_id=${ids().classes.mathNarra}`);
  await expect(page).toHaveURL(/\/student\/learning\?/);
  await expect(page.getByLabel('Class', { exact: true })).toHaveValue(String(ids().classes.mathNarra));
});
