import { test, expect } from '@playwright/test';
import { demoLogin, ids } from './helpers.mjs';

// The bell's badge and accessible name update without a page load: the page asks for the unread
// count every 90 seconds while it is visible (the clock is fast-forwarded here), and the answer is
// the count and nothing else.
test('a new notification reaches the bell without reloading the page', async ({ page, browser }) => {
  await page.clock.install();
  await demoLogin(page, 'student');
  const menu = page.locator('details[data-notifications]');
  const bell = menu.locator('summary');
  const before = Number(await menu.getAttribute('data-unread'));
  await expect(bell).toHaveAttribute('aria-label', before ? `Notifications, ${before} unread` : 'Notifications');

  // The teacher posts an announcement to the student's class, which notifies its students.
  const teacher = await browser.newPage();
  await demoLogin(teacher, 'teacher');
  await teacher.goto(`teacher/classes/${ids().classes.sciNarra}?tab=announcements`);
  await teacher.getByLabel('Title').fill('Bring your lab notebook');
  await teacher.getByLabel('Message').fill('We will finish the breathing-rate lab on Friday.');
  await teacher.getByRole('button', { name: 'Post to class' }).click();
  await expect(teacher.locator('.toast').first()).toBeVisible();
  await teacher.close();

  await page.clock.fastForward('01:31');
  await expect(bell).toHaveAttribute('aria-label', `Notifications, ${before + 1} unread`);
  await expect(bell.locator('.icon-btn__dot')).toHaveText(String(before + 1));

  // Opening the bell now lists the new announcement.
  await bell.click();
  await expect(menu.locator('.notif-panel')).toContainText('Bring your lab notebook');

  const response = await page.request.get('notifications/count', { headers: { Accept: 'application/json' } });
  expect(await response.json()).toEqual({ count: before + 1 });
});
