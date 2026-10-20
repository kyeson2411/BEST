import { test, expect } from '@playwright/test';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { confirmClick, demoLogin, ids } from './helpers.mjs';

// Flow A and F: onboarding by invitation, spreadsheet import with a preview, class setup,
// policy changes that take effect at once, and a data export request end to end.
test.describe.configure({ mode: 'serial' });

test('an invited teacher sets a password through the one-time link', async ({ page, browser }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/users/new');
  await page.getByRole('radio', { name: /^Teacher/ }).check();
  await page.getByLabel('Given name').fill('Lorna');
  await page.getByLabel('Family name').fill('Bautista');
  await page.getByLabel('Email address').fill('lorna.bautista@example.org');
  await page.getByRole('button', { name: 'Create invitation' }).click();

  const link = await page.locator('#reveal-url').inputValue();
  expect(link).toMatch(/\/invite\/[A-Za-z0-9_-]{30,}$/);
  await page.reload();
  await expect(page.locator('#reveal-url')).toHaveCount(0); // shown once only

  const invitee = await browser.newPage();
  await invitee.goto(link);
  await expect(invitee.getByRole('heading', { name: 'Set up your account' })).toBeVisible();
  await invitee.getByLabel('Create a password').fill('mango river lantern 7');
  await invitee.getByLabel('Type the password again').fill('mango river lantern 7');
  await invitee.getByRole('button', { name: 'Set up account' }).click();
  await expect(invitee).toHaveURL(/\/teacher$/);
  await invitee.goto(link);
  await expect(invitee.getByText(/no longer valid|already been used|expired/i).first()).toBeVisible();
  await invitee.close();
});

test('a CSV import shows problems before anything is saved', async ({ page }) => {
  await demoLogin(page, 'admin');
  const dir = mkdtempSync(join(tmpdir(), 'sibol-'));
  const file = join(dir, 'people.csv');
  writeFileSync(file, [
    'given_name,family_name,email,role,grade_level,class',
    'Paulo,Santos,paulo.santos@example.org,student,Grade 9,Science 9 - Narra',
    'Ria,Gomez,ria.gomez@example.org,student,Grade 9,',
    'No,Email,,student,,',
    'Andrea,Cruz,andrea.cruz@demo.sibol.test,student,Grade 9,',
  ].join('\n'));
  await page.goto('admin/users/import');
  await page.getByLabel('CSV file').setInputFiles(file);
  await page.getByRole('button', { name: 'Check file' }).click();

  await expect(page.getByRole('heading', { name: 'Check the import' })).toBeVisible();
  await expect(page.getByText('The email address is not valid.')).toBeVisible();
  await expect(page.getByText('Already has an account; nothing to do.')).toBeVisible();
  await expect(page.locator('.stat').filter({ hasText: 'New accounts' })).toContainText('2');

  await confirmClick(page, page.getByRole('button', { name: /Import 2 people/ }));
  await expect(page.getByRole('heading', { name: 'Import complete' })).toBeVisible();
  await expect(page.locator('input[readonly]')).toHaveCount(2);
  const download = page.waitForEvent('download');
  await page.getByRole('link', { name: 'Download links as CSV' }).click();
  expect((await download).suggestedFilename()).toBe('sibol-invitation-links.csv');
});

test('an administrator creates a class and enrols students', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/classes/new');
  await page.getByLabel('Class name').fill('Filipino 9 – Narra');
  await page.getByLabel('Subject').selectOption({ label: 'Filipino' });
  await page.getByLabel('Grade level').selectOption('Grade 9');
  await page.getByLabel('Teacher').selectOption({ label: 'Joy Santiago' });
  await page.getByRole('button', { name: 'Create class' }).click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Filipino 9 – Narra');

  await page.getByLabel('Andrea Cruz').check();
  await page.getByLabel('Bea Ramos').check();
  await page.getByRole('button', { name: 'Add students' }).click();
  await expect(page.locator('.toast').first()).toContainText('2 people added');
  await expect(page.locator('#members')).toContainText('Andrea Cruz');
});

test('turning off practice quizzes takes effect for students at once', async ({ page, browser }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/policies');
  await page.getByLabel('Practice quizzes').uncheck();
  await page.getByRole('button', { name: 'Save policies' }).click();
  await expect(page.locator('.toast').first()).toContainText('Policies saved');

  const student = await browser.newPage();
  await demoLogin(student, 'student');
  await student.goto('companion/new');
  await expect(student.getByRole('radio', { name: /Practice quiz/ })).toHaveCount(0);
  await expect(student.getByRole('radio', { name: /Explain a topic/ })).toHaveCount(1);
  await student.close();

  await page.getByLabel('Practice quizzes').check();
  await page.getByRole('button', { name: 'Save policies' }).click();
  await expect(page.locator('.toast').first()).toContainText('Policies saved');
});

test('a data export request is prepared by the admin and downloaded by the student only', async ({ page, browser }) => {
  await demoLogin(page, 'admin');
  await page.goto(`admin/privacy/requests/${ids().exportRequest}`);
  await confirmClick(page, page.getByRole('button', { name: 'Prepare export and complete' }));
  await expect(page.locator('.toast').first()).toContainText('Export prepared');

  // Nicole Tan made the seeded request; her own settings page offers the download.
  const nicole = await browser.newPage();
  await nicole.goto('login');
  await nicole.getByLabel('Email').fill('nicole.tan@demo.sibol.test');
  await nicole.getByLabel('Password').fill('Sibol-demo-2026');
  await nicole.getByRole('button', { name: 'Sign in', exact: true }).click();
  await nicole.goto('settings#privacy');
  const download = nicole.waitForEvent('download');
  await nicole.getByRole('link', { name: 'Download' }).first().click();
  expect((await download).suggestedFilename()).toMatch(/^sibol-my-data-.*\.json$/);
  await nicole.close();

  // The administrator cannot download it.
  const res = await page.request.get(`settings/privacy-requests/${ids().exportRequest}/download`, { maxRedirects: 0 });
  expect(res.status()).toBe(404);
});

test('the audit log records administrative actions without content', async ({ page }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/audit');
  await expect(page.getByText('Imported 2 people from CSV')).toBeVisible();
  await expect(page.getByText('Prepared a data export')).toBeVisible();
  await expect(page.getByText('Changed school policies').first()).toBeVisible();
});
