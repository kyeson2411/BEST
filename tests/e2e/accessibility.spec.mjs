import { test, expect } from '@playwright/test';
import { demoLogin, ids } from './helpers.mjs';

// Structural accessibility checks on the main screens: one h1, a page language, unique ids,
// a name for every form control, button and link, and alternative text for images.
const pages = {
  guest: ['', 'login', 'start', 'privacy', 'help', 'login?lang=fil'],
  student: ['student', 'student/assignments', 'student/classes', 'companion/new', 'student/progress', 'student/progress/report', 'student/review', 'student/calendar', 'student/learning', 'settings', 'feedback'],
  teacher: ['teacher', 'teacher/review', 'teacher/assignments/new', 'teacher/materials/new', 'teacher/lessons', 'teacher/gradebook', 'teacher/insights', 'teacher/advisory', 'feedback'],
  admin: ['admin', 'admin/users', 'admin/users/new', 'admin/users/import', 'admin/classes/new', 'admin/subjects', 'admin/sections', 'admin/usage', 'admin/policies', 'admin/settings', 'admin/privacy', 'admin/audit', 'admin/feedback', 'admin/pilot', 'admin/grading', 'admin/academic-periods'],
};

async function audit(page) {
  return page.evaluate(() => {
    const problems = [];
    const visible = (el) => !!(el.offsetWidth || el.offsetHeight || el.getClientRects().length);
    const name = (el) => {
      const labelledby = el.getAttribute('aria-labelledby');
      if (labelledby) return labelledby.split(/\s+/).map((id) => document.getElementById(id)?.textContent || '').join(' ').trim();
      if (el.getAttribute('aria-label')) return el.getAttribute('aria-label').trim();
      if (el.id) {
        const label = document.querySelector(`label[for="${CSS.escape(el.id)}"]`);
        if (label) return label.textContent.trim();
      }
      const wrapping = el.closest('label');
      if (wrapping) return wrapping.textContent.trim();
      if (el.title) return el.title.trim();
      return (el.textContent || el.value || el.alt || '').trim();
    };
    if (document.querySelectorAll('h1').length !== 1) problems.push(`${document.querySelectorAll('h1').length} h1 elements`);
    if (!document.documentElement.lang) problems.push('missing lang');
    const seen = new Set();
    document.querySelectorAll('[id]').forEach((el) => { if (seen.has(el.id)) problems.push(`duplicate id #${el.id}`); seen.add(el.id); });
    document.querySelectorAll('input:not([type=hidden]), select, textarea').forEach((el) => { if (!name(el)) problems.push(`unnamed control ${el.name || el.outerHTML.slice(0, 60)}`); });
    document.querySelectorAll('button, a[href], summary').forEach((el) => {
      if (!visible(el) && !el.closest('dialog')) return;
      const n = name(el) || el.querySelector('svg[aria-label]')?.getAttribute('aria-label') || el.querySelector('img[alt]')?.alt;
      if (!n) problems.push(`unnamed ${el.tagName.toLowerCase()} ${el.outerHTML.slice(0, 80)}`);
    });
    document.querySelectorAll('img').forEach((img) => { if (!img.hasAttribute('alt')) problems.push(`img without alt ${img.src}`); });
    return problems;
  });
}

for (const [role, paths] of Object.entries(pages)) {
  test(`${role} screens pass structural accessibility checks`, async ({ page }) => {
    if (role !== 'guest') await demoLogin(page, role);
    for (const path of paths) {
      await page.goto(path);
      expect(await audit(page), path).toEqual([]);
    }
  });
}

test('paper work screens pass structural accessibility checks', async ({ page }) => {
  const id = ids().paperTest;
  await demoLogin(page, 'teacher');
  for (const path of ['teacher/assignments/new?kind=paper', `teacher/assignments/${id}`, `teacher/assignments/${id}/scores`, `teacher/assignments/${id}/print`, `teacher/assignments/${id}/print?copy=key`]) {
    await page.goto(path);
    expect(await audit(page), path).toEqual([]);
  }
  await page.context().clearCookies();
  await demoLogin(page, 'student');
  await page.goto(`student/assignments/${id}`);
  expect(await audit(page), 'student paper work').toEqual([]);
});

test('an adviser\'s progress reports pass structural accessibility checks', async ({ page }) => {
  const narra = ids().sections.narra;
  await demoLogin(page, 'teacher');
  for (const path of [`teacher/advisory/${narra}/reports`, `teacher/advisory/${narra}/students/${ids().users.andrea}/report`]) {
    await page.goto(path);
    expect(await audit(page), path).toEqual([]);
  }
});

test('keyboard users can skip to the content', async ({ page }) => {
  await demoLogin(page, 'student');
  await page.keyboard.press('Tab');
  await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
});
