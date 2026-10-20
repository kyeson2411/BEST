import { test, expect } from '@playwright/test';
import { mkdtempSync, writeFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { confirmClick, demoLogin, ids, passwordLogin, statusOf } from './helpers.mjs';

// Item 19: students without email sign in with a username; administrators, advisers and teachers
// print one-time sign-in and password slips, which work by QR link or by typing the code.
test.describe.configure({ mode: 'serial' });

/** Reads the code printed on the first slip of the slips page. */
async function firstSlipCode(page) {
  await expect(page.getByRole('heading', { name: 'Sign-in slips' })).toBeVisible();
  const code = (await page.locator('.slip__code').first().textContent()).trim();
  expect(code).toMatch(/^[A-Z2-9]{4}-[A-Z2-9]{4}-[A-Z2-9]{4}$/);
  return code;
}

test('students imported without email set up their account from a printed slip', async ({ page, browser }) => {
  await demoLogin(page, 'admin');
  const dir = mkdtempSync(join(tmpdir(), 'sibol-'));
  const file = join(dir, 'molave.csv');
  writeFileSync(file, [
    'given_name,family_name,role,grade_level,class',
    'Mylene,Abad,student,Grade 9,Science 9 - Molave',
    'Renz,Villamor,student,Grade 9,Science 9 - Molave',
  ].join('\n'));
  await page.goto('admin/users/import');
  await page.getByLabel('CSV file').setInputFiles(file);
  await page.getByRole('button', { name: 'Check file' }).click();
  await expect(page.getByText('Username made from the name')).toHaveCount(2);
  await confirmClick(page, page.getByRole('button', { name: /Import 2 people/ }));
  await expect(page.getByRole('heading', { name: 'Import complete' })).toBeVisible();
  await expect(page.getByText('mylene.abad')).toBeVisible();

  await page.getByRole('link', { name: 'Print sign-in slips' }).click();
  await expect(page.locator('.slip')).toHaveCount(2);
  await expect(page.getByRole('heading', { name: /Grade 9 – Molave/ })).toBeVisible();
  await expect(page.locator('.slip').first()).toContainText('Set up your Sibol account');
  await expect(page.locator('.slip').first()).toContainText('mylene.abad');
  await expect(page.locator('.slip svg.slip__qr')).toHaveCount(2);
  const code = await firstSlipCode(page);

  // On a school computer without a camera: type the code, in small letters with spaces.
  const student = await browser.newPage();
  await student.goto('login');
  await student.getByRole('link', { name: 'Have a sign-in slip? Type its code' }).click();
  await student.getByLabel('Code from your slip').fill('nope-nope-nope');
  await student.getByRole('button', { name: 'Continue' }).click();
  await expect(student.getByText(/Type the 12 letters and numbers/)).toBeVisible();
  await student.getByLabel('Code from your slip').fill(code.toLowerCase().replace(/-/g, ' '));
  await student.getByRole('button', { name: 'Continue' }).click();
  await expect(student).toHaveURL(new RegExp(`/invite/${code.replace(/-/g, '')}$`));
  await expect(student.getByRole('heading', { name: 'Set up your account' })).toBeVisible();
  await expect(student.getByText('mylene.abad')).toBeVisible();
  await student.getByLabel('Create a password').fill('blue jeepney morning');
  await student.getByLabel('Type the password again').fill('blue jeepney morning');
  await student.getByRole('button', { name: 'Set up account' }).click();
  await expect(student).toHaveURL(/\/student$/);

  // The code worked once; afterwards the student signs in with the username.
  await student.goto(`invite/${code}`);
  await expect(student.getByText(/no longer valid/i).first()).toBeVisible();
  await student.context().clearCookies();
  await passwordLogin(student, 'mylene.abad', 'blue jeepney morning');
  await expect(student).toHaveURL(/\/student$/);
  await student.close();
});

test("an adviser prints a password slip and the student chooses a new password", async ({ page, browser }) => {
  // Ramon Dizon advises Grade 9 Narra.
  await demoLogin(page, 'teacher');
  await page.getByRole('link', { name: 'Advisory' }).first().click();
  await expect(page.getByRole('heading', { level: 1 })).toHaveText('Grade 9 – Narra');
  const row = page.getByRole('row').filter({ hasText: 'Enzo Castillo' });
  await expect(row).toContainText('enzo.castillo');
  await confirmClick(page, row.getByRole('button', { name: 'Password slip' }));
  await expect(page.locator('.slip')).toHaveCount(1);
  await expect(page.locator('.slip')).toContainText('Choose a new Sibol password');
  const code = await firstSlipCode(page);

  // The QR code holds the reset link; opening it is the same as scanning.
  const student = await browser.newPage();
  await student.goto(`reset/${code.replace(/-/g, '')}`);
  await expect(student.getByRole('heading', { name: 'Choose a new password' })).toBeVisible();
  await expect(student.getByText('enzo.castillo')).toBeVisible();
  await student.getByLabel('New password').fill('green mango festival');
  await student.getByLabel('Type the password again').fill('green mango festival');
  await student.getByRole('button', { name: 'Save password' }).click();
  await expect(student).toHaveURL(/\/student$/);
  await student.context().clearCookies();
  await passwordLogin(student, 'enzo.castillo', 'green mango festival');
  await expect(student).toHaveURL(/\/student$/);
  await student.close();

  // The audit log records the slip, never the code.
  const admin = await browser.newPage();
  await demoLogin(admin, 'admin');
  await admin.goto('admin/audit');
  await expect(admin.getByText('Printed a password slip').first()).toBeVisible();
  await expect(admin.locator('main')).not.toContainText(code);
  await admin.close();
});

test('a subject teacher prints slips from the class roster, only for their own students', async ({ page }) => {
  await passwordLogin(page, 'joy.santiago');
  await expect(page).toHaveURL(/\/teacher$/);
  await expect(page.getByRole('link', { name: 'Advisory' })).toHaveCount(0);
  await page.goto(`teacher/classes/${ids().classes.engNarra}?tab=roster`);
  // Rafael Domingo has not set up his account yet.
  await expect(page.getByRole('row').filter({ hasText: 'Rafael Domingo' })).toContainText('Not set up yet');
  await confirmClick(page, page.getByRole('button', { name: 'Print 1 slip' }));
  await expect(page.locator('.slip')).toHaveCount(1);
  await expect(page.locator('.slip')).toContainText('rafael.domingo');

  // Janelle Ocampo is in Grade 9 Molave, which Joy neither teaches nor advises.
  const token = await page.locator('meta[name="csrf-token"]').getAttribute('content');
  const response = await page.request.post(`teacher/students/${ids().users.janelle}/slip`, { form: { _token: token }, maxRedirects: 0 });
  expect(response.status()).toBe(404);
  expect(await statusOf(page, `teacher/advisory/${ids().sections.narra}`)).toBe(404);
});

test('an administrator chooses a section adviser', async ({ page, browser }) => {
  await demoLogin(page, 'admin');
  await page.goto('admin/sections');
  await expect(page.getByRole('link', { name: 'Sections and advisers' })).toHaveAttribute('aria-current', 'page');
  const molave = page.locator(`#section-${ids().sections.molave}`);
  await expect(molave).toContainText('Grade 9 – Molave');
  await molave.getByLabel(/Adviser of Grade 9 – Molave/).selectOption({ label: 'Joy Santiago' });
  await molave.getByRole('button', { name: 'Save' }).click();
  await expect(page.locator('.toast').first()).toContainText('Adviser saved');

  const joy = await browser.newPage();
  await passwordLogin(joy, 'joy.santiago');
  await joy.getByRole('link', { name: 'Advisory' }).first().click();
  await expect(joy.getByRole('heading', { level: 1 })).toHaveText('Grade 9 – Molave');
  await expect(joy.getByRole('row').filter({ hasText: 'Janelle Ocampo' })).toBeVisible();
  await joy.close();
});
