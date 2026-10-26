import { test, expect } from '@playwright/test';
import { demoLogin, passwordLogin, ids } from './helpers.mjs';

// Teach once, use in every section: the demo teacher copies a material, a quiz and a lesson from
// Science 9 – Narra to Molave. Copies arrive as drafts, so Molave students see nothing until the
// teacher publishes them there.
test.describe.configure({ mode: 'serial' });

const MOLAVE = 'Science 9 – Molave';
let materialCopy = '';

function copyPanel(page) {
  return page.locator('section.panel', { has: page.locator('#copy-title') });
}

async function copyTo(page, section) {
  const panel = copyPanel(page);
  await panel.getByLabel(section).check();
  await panel.getByRole('button', { name: 'Copy as drafts' }).click();
  await expect(page.locator('.toast').first()).toContainText(`Copied to ${section} as a draft`);
  return copyPanel(page);
}

test('a teacher copies a material to another section as a draft', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/materials?class_id=${ids().classes.sciNarra}`);
  await page.getByRole('link', { name: 'Heredity notes', exact: true }).click();

  await copyPanel(page).getByRole('button', { name: 'Copy as drafts' }).click();
  await expect(copyPanel(page)).toContainText('Choose at least one section to copy to.');

  const panel = await copyTo(page, MOLAVE);
  await panel.getByRole('link', { name: MOLAVE }).click();
  materialCopy = page.url();
  await expect(page.locator('.page-lead')).toHaveText(`${MOLAVE}, Module 2: Heredity and variation`);
  await expect(page.getByText('Draft, teachers only')).toBeVisible();
  await expect(page.getByText('Ready for the companion')).toBeVisible();
});

test('Molave students see the copy only once it is published there', async ({ page, browser }) => {
  const student = await browser.newPage();
  await passwordLogin(student, 'janelle.ocampo');
  await expect(student).toHaveURL(/\/student$/);
  const materials = `student/classes/${ids().classes.sciMolave}?tab=materials`;
  await student.goto(materials);
  await expect(student.getByRole('link', { name: 'Heredity notes', exact: true })).toHaveCount(0);

  await demoLogin(page, 'teacher');
  await page.goto(materialCopy);
  await page.getByRole('button', { name: 'Publish' }).click();
  await expect(page.locator('.toast').first()).toContainText(`Students in ${MOLAVE} can now open it`);

  await student.goto(materials);
  await expect(student.getByRole('link', { name: 'Heredity notes', exact: true })).toBeVisible();
  await student.close();
});

test('a quiz copy keeps its questions and links the section\'s own copy of its notes', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto(`teacher/quizzes?class_id=${ids().classes.sciNarra}`);
  await page.getByRole('link', { name: 'Heredity basics' }).click();
  const panel = await copyTo(page, MOLAVE);
  // The notes it links to were copied to Molave in the first test, so nothing else is copied now.
  await expect(page.getByText('Linked materials were copied too')).toHaveCount(0);
  await panel.getByRole('link', { name: MOLAVE }).click();
  await expect(page.locator('.page-lead')).toContainText(MOLAVE);
  await expect(page.locator('.page-lead')).toContainText('Draft');
  await expect(page.locator('ol.questions > li')).toHaveCount(5);
});

test('a lesson copy is a draft whose sources point to the section\'s own materials', async ({ page }) => {
  await demoLogin(page, 'teacher');
  await page.goto('teacher/lessons');
  await page.getByRole('link', { name: 'Gas exchange in the alveoli' }).click();
  const panel = await copyTo(page, MOLAVE);
  await panel.getByRole('link', { name: MOLAVE }).click();
  await expect(page.locator('.page-lead')).toContainText(MOLAVE);
  await expect(page.locator('.page-lead')).toContainText('Draft');
  const source = page.locator('section.panel', { has: page.locator('#src-title') }).getByRole('link', { name: 'Breathing and circulation notes' });
  await expect(source).toHaveAttribute('href', /\/materials\/\d+\/read$/);
  await expect(source).not.toHaveAttribute('href', new RegExp(`/materials/${ids().releasedMaterial}/read$`));
});
