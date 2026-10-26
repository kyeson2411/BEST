import { test, expect } from '@playwright/test';
import { confirmClick, demoLogin, passwordLogin } from './helpers.mjs';

/** Dispatches a paste into the element and reports whether the page blocked it. */
async function pasteBlocked(locator) {
  return locator.evaluate((box) => {
    const event = new ClipboardEvent('paste', { bubbles: true, cancelable: true, clipboardData: new DataTransfer() });
    box.dispatchEvent(event);
    return event.defaultPrevented;
  });
}

// Anti-cheating during a quiz. Miguel takes Heredity basics here (the demo school has no attempt of his yet,
// and no other browser test uses him).
test('a quiz blocks pasting and counts leaving the page, and the teacher sees it on the attempt', async ({ page, browser }) => {
  await passwordLogin(page, 'miguel.bautista');
  await expect(page).toHaveURL(/\/student$/);
  await page.goto('student/quizzes');
  await page.getByRole('link', { name: 'Heredity basics', exact: true }).click();
  await expect(page.getByText('Anti-cheating is on for this quiz')).toBeVisible();
  await confirmClick(page, page.getByRole('button', { name: 'Start quiz' }));
  await expect(page.locator('#quiz-form')).toBeVisible();

  expect(await pasteBlocked(page.locator('#quiz-form input[type="text"], #quiz-form textarea').first())).toBe(true);
  await expect(page.locator('[data-anticheat-note]')).toBeVisible();

  // Switching to another tab or app, then coming back.
  await page.evaluate(() => window.dispatchEvent(new Event('blur')));
  await page.evaluate(() => window.dispatchEvent(new Event('focus')));
  const dialog = page.locator('[data-anticheat-dialog]');
  await expect(dialog).toBeVisible();
  await expect(dialog).toContainText('recorded 1 time');
  await dialog.getByRole('button', { name: 'Back to the quiz' }).click();
  await expect(dialog).toBeHidden();

  // Handing it in leaves the page too, but that is not counted.
  await confirmClick(page, page.getByRole('button', { name: 'Submit quiz' }));
  await expect(page).toHaveURL(/\/student\/submissions\/\d+$/);
  await expect(page.locator('.receipt')).toContainText('Left the quiz page 1 time');
  await expect(page.locator('.receipt')).toContainText('Tried to paste 1 time');

  const id = page.url().match(/submissions\/(\d+)$/)[1];
  const teacher = await browser.newPage();
  await demoLogin(teacher, 'teacher');
  await teacher.goto(`teacher/submissions/${id}`);
  await expect(teacher.getByText('Left the quiz page 1 time')).toBeVisible();
  await teacher.close();
});

test('an assignment blocks pasting into the typed response', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('student/assignments');
  await page.getByRole('link', { name: 'Inference journal: three clues', exact: true }).click();
  await expect(page.getByText('Anti-cheating is on: copy and paste are off in your response')).toBeVisible();
  expect(await pasteBlocked(page.locator('#f-body'))).toBe(true);
  await expect(page.locator('[data-anticheat-note]')).toBeVisible();
});
