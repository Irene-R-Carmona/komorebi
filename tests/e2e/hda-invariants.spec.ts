/**
 * E2E: HDA Architecture Invariant Tests
 *
 * Verifies the 5 invariants from docs/adr/001-hda-architecture.md:
 * 1. PHP = sole data source for page-load collections
 * 3. Alpine = UI behavior only (no domain collections fetched in init)
 * 5. AJAX only for reactive queries (after user input)
 */

import { test, expect, Page } from '@playwright/test';

const USER_EMAIL    = 'yuki.tanaka@gmail.com';
const USER_PASSWORD = 'komorebi2024';

async function loginAs(page: Page, email: string, password: string) {
  await page.goto('/login');
  await page.fill('input[name="email"]', email);
  await page.fill('input[name="password"]', password);
  await page.click('button[type="submit"]', { force: true });
  await page.waitForURL(url => !url.pathname.includes('/login'), { waitUntil: 'load' });
}

// ─── Invariant 1 + 3: No domain-data fetches on page load ──────────────────

test('INV-1: /cafes — no fetch(/api/v1/cafes) on page load', async ({ page }) => {
  const blocked: string[] = [];
  page.on('request', req => {
    if (req.url().includes('/api/v1/cafes')) blocked.push(req.url());
  });

  await page.goto('/cafes');
  await page.waitForLoadState('networkidle');

  expect(blocked, 'fetch(/api/v1/cafes) must not be called on catalog load').toHaveLength(0);

  // PHP-injected data rendered: at least one cafe card visible
  await expect(page.locator('.card').first()).toBeVisible();
});

test('INV-1: /cafes — cafe names come from PHP-injected x-data, not API', async ({ page }) => {
  await page.goto('/cafes');
  // getAttribute returns raw attribute value (not browser-serialized HTML entities)
  const xdata = await page.locator('[x-data*="catalogoApp"]').getAttribute('x-data');
  expect(xdata).not.toBeNull();
  expect(xdata).toContain('catalogoApp(');
  expect(xdata).toMatch(/"cafes"\s*:\s*\[/);
});

test('INV-1: /menu — no fetch(/api/v1/cart) on page load', async ({ page }) => {
  const cartFetches: string[] = [];
  page.on('request', req => {
    if (req.url().includes('/api/v1/cart') && req.method() === 'GET') {
      cartFetches.push(req.url());
    }
  });

  await page.goto('/menu');
  await page.waitForLoadState('networkidle');

  expect(cartFetches, 'GET /api/v1/cart must not be called in menu init').toHaveLength(0);

  // Cart data injected in page source
  const html = await page.content();
  expect(html).toContain('menuApp(');
  // Second arg is cart JSON
  expect(html).toMatch(/menuApp\(\d+,\s*\{/);
});

test('INV-1: /perfil — no fetch(/api/v1/user/profile) on page load', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);

  const profileFetches: string[] = [];
  const statsFetches: string[]   = [];
  page.on('request', req => {
    if (req.url().includes('/api/v1/user/profile')) profileFetches.push(req.url());
    if (req.url().includes('/api/v1/user/stats'))   statsFetches.push(req.url());
  });

  await page.goto('/perfil');
  await page.waitForLoadState('networkidle');

  expect(profileFetches, 'fetch(/api/v1/user/profile) must not be called').toHaveLength(0);
  expect(statsFetches,   'fetch(/api/v1/user/stats) must not be called').toHaveLength(0);

  // Profile data rendered immediately from PHP
  const name = await page.locator('.member-card__info h2').textContent();
  expect(name?.trim().length).toBeGreaterThan(0);
});

test('INV-1: /perfil — no fetch(/api/v1/user/avatar-options) on page load', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);

  const avatarFetches: string[] = [];
  page.on('request', req => {
    if (req.url().includes('/api/v1/user/avatar-options')) avatarFetches.push(req.url());
  });

  await page.goto('/perfil');
  await page.waitForLoadState('networkidle');

  expect(avatarFetches, 'fetch(/api/v1/user/avatar-options) must not be called').toHaveLength(0);

  // Avatar options are in DOM attribute
  const opts = await page.locator('[data-avatar-options]').getAttribute('data-avatar-options');
  expect(opts).not.toBeNull();
  const parsed = JSON.parse(opts ?? '[]');
  expect(Array.isArray(parsed)).toBe(true);
  expect(parsed.length).toBeGreaterThan(0);
});

// ─── Invariant 5: AJAX IS called reactively after user input ───────────────

test('INV-5: /reservar/paso-2 — slots fetched AFTER user selects date', async ({ page }) => {
  await loginAs(page, USER_EMAIL, USER_PASSWORD);

  // Setup wizard step 1 first by posting
  const slotFetches: string[] = [];

  // Navigate to paso-2 directly (wizard session not set → redirect to /reservar)
  // Instead, go through paso-1 form
  await page.goto('/reservas');
  // Wait for Alpine x-for to render cafe options (more than the disabled placeholder)
  await page.waitForFunction(
    () => (document.querySelector('select[name="cafe_id"]') as HTMLSelectElement | null)?.options.length > 1
  );

  // Select first available cafe
  await page.selectOption('select[name="cafe_id"]', { index: 1 });
  await page.waitForTimeout(300);

  // Select first pass
  await page.locator('input[name="pass_product_id"]').first().check();
  const paso1Btn = page.locator('form[action="/reservar/paso-1"] button[type="submit"]');
  await paso1Btn.waitFor({ state: 'visible', timeout: 5000 });
  await paso1Btn.click();

  // Now on paso-2
  await page.waitForURL('**/reservar/paso-2');

  // Register slot fetch listener AFTER arriving on paso-2
  page.on('request', req => {
    if (req.url().includes('/api/v1/time-slots')) slotFetches.push(req.url());
  });

  // No slots fetched yet (no date selected)
  expect(slotFetches).toHaveLength(0);

  // Select a future date
  const tomorrow = new Date();
  tomorrow.setDate(tomorrow.getDate() + 1);
  const dateStr = tomorrow.toISOString().split('T')[0];
  await page.fill('input[name="fecha"]', dateStr);
  await page.dispatchEvent('input[name="fecha"]', 'change');

  // Slots fetch should be triggered
  await page.waitForResponse(resp => resp.url().includes('/api/v1/time-slots'));
  expect(slotFetches.length).toBeGreaterThan(0);
  expect(slotFetches[0]).toContain(`cafe_id=`);
  expect(slotFetches[0]).toContain(`start_date=${dateStr}`);
});

// ─── No Alpine.store() for domain data ────────────────────────────────────

test('INV-3: no Alpine.store() holding domain collections on catalog', async ({ page }) => {
  await page.goto('/cafes');
  await page.waitForLoadState('networkidle');

  const stores = await page.evaluate(() => {
    const alpine = (globalThis as any).Alpine;
    if (!alpine?.store) return {};
    // Try to access known problematic stores
    try { return { cafes: alpine.store('cafes'), passes: alpine.store('passes') }; } catch { return {}; }
  });

  // No domain store should exist
  expect((stores as any).cafes).toBeUndefined();
  expect((stores as any).passes).toBeUndefined();
});
