import { test, expect } from '@playwright/test';
import { statusOf } from './helpers.mjs';

// Item 16: "Sign in with Google" stays off, with no button and its addresses not found, until the school sets
// GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET and GOOGLE_WORKSPACE_DOMAINS. The round trip with Google is tested in
// PHPUnit (GoogleSignInTest) against a fake Google, with ID tokens signed by a test key.
test('Google sign-in is off until the school sets it up', async ({ page }) => {
  await page.goto('login');
  await expect(page.getByRole('link', { name: 'Sign in with your school Google account' })).toHaveCount(0);
  for (const path of ['auth/google', 'auth/google/callback?state=x&code=y']) {
    expect(await statusOf(page, path), path).toBe(404);
  }
});
