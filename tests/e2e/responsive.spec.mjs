import { test, expect } from '@playwright/test';
import { demoLogin, ids } from './helpers.mjs';

// Runs in the "phone" project (390 × 844). Every main screen must fit without sideways
// scrolling and keep the bottom navigation reachable.
const screens = {
  student: ['student', 'student/assignments', 'student/classes', 'companion', 'student/progress', 'student/progress/report', 'student/calendar', 'student/learning', 'feedback'],
  teacher: ['teacher', 'teacher/review', 'teacher/assignments', 'teacher/materials', 'teacher/gradebook', 'teacher/insights', 'teacher/advisory', 'feedback'],
  admin: ['admin', 'admin/users', 'admin/classes', 'admin/sections', 'admin/usage', 'admin/policies', 'admin/privacy', 'admin/audit', 'admin/feedback', 'admin/pilot', 'admin/grading', 'admin/academic-periods'],
};

for (const [role, paths] of Object.entries(screens)) {
  test(`${role} screens fit a phone`, async ({ page }) => {
    await demoLogin(page, role);
    for (const path of paths) {
      await page.goto(path);
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      expect(overflow, `${path} scrolls sideways by ${overflow}px`).toBeLessThanOrEqual(0);
      await expect(page.locator('.bottombar'), `${path} has bottom navigation`).toBeVisible();
    }
  });
}

test('the score grid, the printable copy and the section\'s progress reports fit a phone', async ({ page }) => {
  await demoLogin(page, 'teacher');
  for (const path of [`teacher/assignments/${ids().paperTest}/scores`, `teacher/assignments/${ids().paperTest}/print?copy=key`, `teacher/advisory/${ids().sections.narra}/reports`]) {
    await page.goto(path);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
    expect(overflow, `${path} scrolls sideways by ${overflow}px`).toBeLessThanOrEqual(0);
  }
});

test('the sign-in page fits a phone with the seal pinned to the photo', async ({ page }) => {
  await page.goto('');
  const overflow = await page.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
  expect(overflow).toBeLessThanOrEqual(0);
  const photo = await page.locator('.auth__photo').boundingBox();
  const seal = await page.locator('.auth__seal').boundingBox();
  expect(seal.y).toBeLessThan(photo.y + photo.height);
  expect(seal.y + seal.height).toBeGreaterThan(photo.y + photo.height);
  await expect(page.getByLabel('Username or email')).toBeInViewport();
});

test('the menu drawer opens from the bottom bar', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.locator('.bottombar').getByRole('link', { name: 'More' }).click();
  await expect(page.locator('#spine').getByRole('link', { name: 'Gradebook' })).toBeVisible();
});
