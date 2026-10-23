import { test, expect } from '@playwright/test';
import { demoLogin } from './helpers.mjs';

// A teacher sees which topics students asked the companion about that the class materials don't cover: only topics
// at least five different students asked about, and never who asked.
test('Insights shows what the class materials do not cover', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('teacher/insights');
  await page.getByRole('link', { name: 'Science 9 – Narra' }).first().click();

  const gaps = page.locator('#gaps');
  await expect(gaps.getByRole('heading', { name: "What your materials don't cover" })).toBeVisible();
  await expect(gaps).toContainText('punnett, probability');
  await expect(gaps).toContainText('6 students asked');
  await expect(gaps).toContainText('blood, types, inheritance');
  await expect(gaps).not.toContainText('mitosis');
  await expect(gaps).not.toContainText('dihybrid');
  await expect(gaps).not.toContainText('Andrea');

  await gaps.getByRole('link', { name: 'Add a material' }).first().click();
  await expect(page).toHaveURL(/teacher\/materials\/new\?class_id=\d+$/);
});
