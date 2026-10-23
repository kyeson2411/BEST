import { test, expect } from '@playwright/test';
import { demoLogin } from './helpers.mjs';

// Item 12: email is optional. The test .env has no MAIL_ settings, so Sibol must not promise any email: links are
// copied as before, no password is shown, and nobody is offered a daily summary. (Sending is covered by PHPUnit
// with messages kept in memory, and was tried against a local SMTP test server; see README > Email.)
test('without MAIL_ settings, links are copied and no email is promised', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/integrations');
  const panel = page.locator('section', { has: page.getByRole('heading', { name: 'Email', exact: true }) });
  await expect(panel).toContainText('Not connected');
  await expect(page.locator('main')).not.toContainText('MAIL_PASSWORD');

  await page.goto('settings');
  await expect(page.getByLabel(/Email me a daily summary/)).toHaveCount(0);

  await page.goto('admin/users/new');
  await page.getByLabel('Given name').fill('Lorna');
  await page.getByLabel('Family name').fill('Email Test');
  await page.getByLabel(/^Email address/).fill('lorna.emailtest@school.test');
  await page.getByRole('button', { name: 'Create invitation' }).click();
  const reveal = page.locator('section', { has: page.locator('#reveal-title') });
  await expect(reveal).toContainText('B.E.S.T. Learning and Interactive Study System does not send email');
  await expect(reveal).not.toContainText('emailed');
  await expect(reveal.getByRole('button', { name: 'Copy link' })).toBeVisible();
});
