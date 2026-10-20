import { test, expect } from '@playwright/test';
import { demoLogin, ids, statusOf } from './helpers.mjs';

// Server-side authorization: hidden links are never the boundary. Out-of-scope records
// answer 404 (their existence is not revealed); out-of-role areas answer 403.

test.describe('A student', () => {
  test.beforeEach(async ({ page }) => demoLogin(page, 'student'));

  test('cannot open teacher or administrator areas', async ({ page }) => {
    expect(await statusOf(page, 'admin')).toBe(403);
    expect(await statusOf(page, 'admin/users')).toBe(403);
    expect(await statusOf(page, 'teacher')).toBe(403);
    expect(await statusOf(page, `teacher/gradebook/${ids().classes.sciNarra}`)).toBe(403);
  });

  test("cannot see another student's work or conversations", async ({ page }) => {
    expect(await statusOf(page, `student/submissions/${ids().beaSubmission}`)).toBe(404);
    expect(await statusOf(page, `companion/${ids().beaConversation}`)).toBe(404);
  });

  test('cannot open draft or not-yet-released materials', async ({ page }) => {
    expect(await statusOf(page, `materials/${ids().draftMaterial}/file`)).toBe(404);
    expect(await statusOf(page, `materials/${ids().scheduledMaterial}/file`)).toBe(404);
    expect(await statusOf(page, `materials/${ids().releasedMaterial}/file`)).toBe(200);
  });

  test('cannot post without the form token', async ({ page }) => {
    const response = await page.request.post('settings/profile', { form: { given_name: 'Hacked' }, maxRedirects: 0 });
    expect(response.status()).toBe(403);
    expect(await response.text()).toContain('This form expired');
  });
});

test.describe('A teacher', () => {
  test.beforeEach(async ({ page }) => demoLogin(page, 'teacher'));

  test("cannot open another teacher's class, gradebook or assignment", async ({ page }) => {
    expect(await statusOf(page, `teacher/classes/${ids().classes.mathNarra}`)).toBe(404);
    expect(await statusOf(page, `teacher/gradebook/${ids().classes.mathNarra}`)).toBe(404);
    expect(await statusOf(page, `teacher/gradebook/${ids().classes.mathNarra}/export`)).toBe(404);
    expect(await statusOf(page, `teacher/assignments/${ids().mathAssignment}`)).toBe(404);
  });

  test("cannot read a student's private companion conversation", async ({ page }) => {
    expect(await statusOf(page, `companion/${ids().beaConversation}`)).toBe(404);
  });

  test('cannot open the administrator area', async ({ page }) => {
    expect(await statusOf(page, 'admin/privacy')).toBe(403);
  });
});

test.describe('A school administrator', () => {
  test.beforeEach(async ({ page }) => demoLogin(page, 'admin'));

  test('has no access to companion conversations', async ({ page }) => {
    expect(await statusOf(page, 'companion')).toBe(403);
    expect(await statusOf(page, `companion/${ids().beaConversation}`)).toBe(403);
  });

  test('cannot use teacher or student areas', async ({ page }) => {
    expect(await statusOf(page, 'teacher/review')).toBe(403);
    expect(await statusOf(page, 'student/assignments')).toBe(403);
  });

  test('sees a reported answer without the rest of the conversation', async ({ page }) => {
    await page.goto(`admin/privacy/reports/${ids().report}`);
    await expect(page.getByRole('heading', { name: 'The reported answer' })).toBeVisible();
    await expect(page.getByText('the student\'s question and the rest of the conversation stay private')).toBeVisible();
    await expect(page.getByText('How do I solve x^2 - 5x + 6 = 0 by factoring?')).toHaveCount(0);
  });
});
