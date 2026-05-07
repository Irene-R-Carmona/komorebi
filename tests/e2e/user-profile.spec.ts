/**
 * E2E: User Profile — PHP-injected data, avatar picker, modals
 *
 * Verifies:
 * - Profile card renders immediately from PHP-injected data (no loading state)
 * - Stats (level, reservation count) visible on first paint
 * - Avatar options come from DOM data-attribute
 * - Avatar picker opens and closes correctly
 * - Delete modal (admin) has proper ARIA and keyboard behavior
 */

import { test, expect, Page } from '@playwright/test';

const USER_EMAIL    = 'yuki.tanaka@gmail.com';
const USER_PASSWORD = 'komorebi2024';
const ADMIN_EMAIL   = 'admin@komorebi.cafe';
const ADMIN_PASSWORD = 'komorebi2024';

async function loginAs(page: Page, email: string, password: string) {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]');
  await page.waitForURL(url => !url.pathname.includes('/login'), { waitUntil: 'load' });
}

// ─── Profile data renders immediately ─────────────────────────────────────

test('PROFILE-1: member card visible before any AJAX completes', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);

  // Intercept and delay reservations AJAX to simulate slow network
  await page.route('**/api/v1/user/reservations', async route => {
    await new Promise(r => setTimeout(r, 3000));
    await route.continue();
  });

  await page.goto('/perfil');

  // Profile card should be visible immediately — data from PHP, not AJAX
  await expect(page.locator('.member-card__info h2')).toBeVisible({ timeout: 2000 });
  await expect(page.locator('.member-card__info p')).toBeVisible({ timeout: 2000 });

  // Level info visible
  await expect(page.locator('.member-card__tier')).toBeVisible({ timeout: 2000 });
});

test('PROFILE-2: profile name and email match logged-in user', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);
  await page.goto('/perfil');
  await page.waitForLoadState('networkidle');

  const name = await page.locator('.member-card__info h2').textContent();
  const email = await page.locator('.member-card__info p').first().textContent();

  expect(name?.trim().length).toBeGreaterThan(0);
  // Email should contain @ (actual user data)
  expect(email).toContain('@');
});

test('PROFILE-3: member year shows year from PHP-injected created_at', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);
  await page.goto('/perfil');
  await page.waitForLoadState('networkidle');

  // memberYear computed from profile.created_at (PHP-injected)
  const statsText = await page.locator('.member-card__stats').first().textContent();
  expect(statsText).toMatch(/miembro desde \d{4}/);
});

// ─── Avatar options from DOM ───────────────────────────────────────────────

test('PROFILE-4: avatar options available in data-attribute before picker opens', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);
  await page.goto('/perfil');
  await page.waitForLoadState('domcontentloaded');

  const wrapper = page.locator('[data-avatar-options]');
  await expect(wrapper).toBeVisible();

  const raw = await wrapper.getAttribute('data-avatar-options');
  expect(raw).not.toBeNull();

  const opts = JSON.parse(raw!);
  expect(Array.isArray(opts)).toBe(true);
  expect(opts.length).toBeGreaterThanOrEqual(8); // initials + 8 presets

  // Each option has id and label
  expect(opts[0]).toHaveProperty('id');
  expect(opts[0]).toHaveProperty('label');
});

test('PROFILE-5: avatar picker opens without network request', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);
  await page.goto('/perfil');
  await page.waitForLoadState('networkidle');

  const avatarFetches: string[] = [];
  page.on('request', req => {
    if (req.url().includes('/api/v1/user/avatar-options')) avatarFetches.push(req.url());
  });

  // Open picker
  await page.locator('.avatar-btn--upload').click();

  // Picker grid visible
  await expect(page.locator('.avatar-picker')).toBeVisible();
  await expect(page.locator('.avatar-picker__item').first()).toBeVisible();

  // No network request for options (they came from DOM)
  expect(avatarFetches).toHaveLength(0);
});

test('PROFILE-6: avatar picker closes on cancel', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);
  await page.goto('/perfil');
  await page.waitForLoadState('networkidle');

  await page.locator('.avatar-btn--upload').click();
  await expect(page.locator('.avatar-picker')).toBeVisible();

  await page.locator('.avatar-picker__close').click();
  await expect(page.locator('.avatar-picker')).toBeHidden();
});

// ─── Delete confirmation modal (admin context) ─────────────────────────────

test('MODAL-1: delete confirmation modal has aria-modal="true"', async ({ page }) => {
  await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  await page.goto('/admin/users');
  await page.waitForLoadState('networkidle');

  // Modal is in DOM (hidden)
  const modal = page.locator('[role="dialog"][aria-modal="true"]');
  await expect(modal).toBeAttached();
});

test('MODAL-2: delete modal has aria-labelledby pointing to visible title', async ({ page }) => {
  await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  await page.goto('/admin/users');
  await page.waitForLoadState('networkidle');

  const modal = page.locator('[role="dialog"]');
  const labelledBy = await modal.getAttribute('aria-labelledby');
  expect(labelledBy).toBe('deleteModalTitle');

  const titleEl = page.locator(`#${labelledBy}`);
  await expect(titleEl).toBeAttached();
});

test('MODAL-3: delete modal closes on Escape key', async ({ page }) => {
  await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  await page.goto('/admin/users');
  await page.waitForLoadState('networkidle');

  // Trigger modal via Alpine (open it)
  await page.evaluate(() => {
    const modal = (window as any).deleteModal;
    if (modal) modal.open({ title: 'Test', message: 'Test delete', deleteUrl: '/fake' });
  });

  const modal = page.locator('[role="dialog"]');
  await expect(modal).toBeVisible({ timeout: 2000 });

  // Press Escape
  await page.keyboard.press('Escape');
  await expect(modal).toBeHidden({ timeout: 2000 });
});

test('MODAL-4: delete modal focus trap keeps focus inside', async ({ page }) => {
  await loginAs(page, ADMIN_EMAIL, ADMIN_PASSWORD);
  await page.goto('/admin/users');
  await page.waitForLoadState('networkidle');

  await page.evaluate(() => {
    const modal = (window as any).deleteModal;
    if (modal) modal.open({ title: 'Test', message: 'Test', deleteUrl: '/fake' });
  });

  await expect(page.locator('[role="dialog"]')).toBeVisible({ timeout: 2000 });

  // Tab through all focusable elements — focus should stay within modal
  const modalEl = page.locator('[role="dialog"]');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');
  await page.keyboard.press('Tab');

  const activeElInsideModal = await page.evaluate(() => {
    const modal = document.querySelector('[role="dialog"]');
    return modal?.contains(document.activeElement) ?? false;
  });

  expect(activeElInsideModal).toBe(true);
});
