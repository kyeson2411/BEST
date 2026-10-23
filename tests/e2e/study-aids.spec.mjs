import { test, expect } from '@playwright/test';
import { demoLogin, ids } from './helpers.mjs';

// Explain it back, read-aloud, simpler and Filipino versions of a material, and practice kept for offline use.
test.describe.configure({ mode: 'serial' });

const controlled = (page) => page.waitForFunction(() => !!navigator.serviceWorker && navigator.serviceWorker.controller !== null);

test('a student explains a topic back and is told what is missing, with sources', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto('companion/new');
  await page.locator('label.mode-option', { hasText: 'Explain it back' }).click();
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.getByLabel('Your first message').fill('Gas exchange: the alveoli have thin walls so oxygen goes into the blood quickly.');
  await page.getByRole('button', { name: 'Start session' }).click();

  const answer = page.locator('.msg--assistant').last();
  await expect(answer).toContainText('Not in your explanation yet', { timeout: 30_000 });
  await expect(page.locator('.source-tag').first()).toBeVisible();
  await expect(answer.getByRole('button', { name: 'Listen' })).toBeVisible();
});

test('the reading page reads aloud, and Listen turns into Stop while it reads', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.goto(`student/classes/${ids().classes.sciNarra}?tab=materials`);
  await page.getByRole('link', { name: 'Breathing and circulation notes', exact: true }).click();
  const listen = page.getByRole('button', { name: 'Listen' });
  await expect(listen).toBeVisible();
  await listen.click();
  await expect(page.getByRole('button', { name: 'Stop' })).toHaveAttribute('aria-pressed', 'true');
  await page.getByRole('button', { name: 'Stop' }).click();
  await expect(listen).toHaveAttribute('aria-pressed', 'false');
});

test('a teacher makes a simpler version and students see it beside the original once shown', async ({ page, browser }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/materials/${ids().releasedMaterial}`);
  await page.getByRole('link', { name: 'Simpler English' }).click();
  await page.getByRole('button', { name: 'Draft with AI' }).click();
  await expect(page.locator('.toast').first()).toContainText('Draft ready');
  await expect(page.getByLabel('Text')).toHaveValue(/Demo draft/);
  await page.getByLabel('Text').fill('## Breathing in\nYour lungs take in air. The air goes to tiny sacs called alveoli.');
  await page.getByRole('button', { name: 'Save and show to students' }).click();
  await expect(page.locator('.toast').first()).toContainText('shown to students');

  const student = await (await browser.newContext()).newPage();
  await demoLogin(student, 'student');
  await student.goto(`student/classes/${ids().classes.sciNarra}?tab=materials`);
  await student.getByRole('link', { name: 'Simpler English' }).click();
  await expect(student.getByRole('navigation', { name: 'Reading versions' })).toBeVisible();
  await expect(student.locator('#reading')).toContainText('tiny sacs called alveoli');
  await student.getByRole('link', { name: 'Original' }).click();
  await expect(student.locator('#reading')).not.toContainText('tiny sacs called alveoli');
});

test('practice kept on the device works offline and its result is sent when back online', async ({ page, context }) => {
  await demoLogin(page, 'student');
  await page.goto('student/learning');
  await page.getByLabel('Class', { exact: true }).selectOption({ label: 'Science 9 – Narra' });
  await page.getByLabel('Topic').fill('alveoli');
  await page.getByRole('button', { name: 'Practice questions' }).click();
  await expect(page).toHaveURL(/student\/learning\/\d+$/);
  const setUrl = page.url();
  await controlled(page);

  const keep = page.getByRole('button', { name: 'Keep for offline practice' });
  await keep.click();
  await expect(page.getByRole('button', { name: 'Kept on this device' })).toHaveAttribute('aria-pressed', 'true');

  await context.setOffline(true);
  try {
    await page.goto('student/assignments');
    await expect(page.getByRole('heading', { level: 1 })).toHaveText('You are offline');
    const packs = page.locator('[data-pack-list] li');
    await expect(packs).toHaveCount(1);
    await packs.getByRole('button', { name: 'Practise' }).click();
    const player = page.locator('[data-pack-player]');
    for (const question of await player.locator('.question').all()) {
      const radio = question.locator('input[type=radio]').first();
      if (await radio.count()) await radio.check(); else await question.locator('input').fill('alveoli');
    }
    await player.getByRole('button', { name: 'Check my answers' }).click();
    await expect(player).toContainText('Your result is sent when you are back online.');
    await player.getByRole('button', { name: 'Back to the list' }).click();
    await expect(packs).toContainText('Result waiting to be sent');
  } finally {
    await context.setOffline(false);
  }

  await page.goto('student');
  await expect(page.locator('.toast').first()).toContainText('offline practice result was sent');
  await page.goto(setUrl);
  await expect(page.getByRole('button', { name: 'Check my answers' })).toHaveCount(0);
  await expect(page.locator('.score')).toBeVisible();
});
