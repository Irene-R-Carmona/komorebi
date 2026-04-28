/**
 * E2E: Reservation Wizard — Full PRG Flow
 *
 * Tests the 3-step POST/Redirect/Get reservation wizard:
 *   POST /reservar/paso-1 → GET /reservar/paso-2
 *   POST /reservar/paso-2 → GET /reservar/paso-3
 *   POST /reservar        → GET /reservas/confirmacion/{id}
 *
 * Test data (from DB seeds):
 *   User: yuki.tanaka@gmail.com / komorebi2024
 *   Cafe: usagi-paradise (id=2, has_reservations=1)
 *   Pass: id=43 (Pase Komorebi 60m, ¥1200, min_pax=1)
 *   Slots: cafe_id=2 has slots on 2026-04-27 10:00
 */

import { test, expect, Page } from '@playwright/test';

const USER_EMAIL    = 'yuki.tanaka@gmail.com';
const USER_PASSWORD = 'komorebi2024';

// Cafe with has_reservations=1 and available slots
const CAFE_ID    = '2';   // usagi-paradise
const PASS_ID    = '43';  // Pase Komorebi 60m, min_pax=1

function tomorrowStr(): string {
  const d = new Date();
  d.setDate(d.getDate() + 1);
  return d.toISOString().split('T')[0];
}

async function loginAs(page: Page) {
  await page.goto('/login');
  await page.fill('input[name="email"]', USER_EMAIL);
  await page.fill('input[name="password"]', USER_PASSWORD);
  await page.click('button[type="submit"]', { force: true });
  await page.waitForURL(url => !url.pathname.includes('/login'), { waitUntil: 'load' });
}

async function waitForCafeOptions(page: Page) {
  await page.waitForFunction(
    () => (document.querySelector('select[name="cafe_id"]') as HTMLSelectElement | null)?.options.length > 1
  );
}

async function completePaso1(page: Page) {
  await page.goto('/reservas');
  await waitForCafeOptions(page);
  await page.selectOption('select[name="cafe_id"]', CAFE_ID);
  await page.waitForTimeout(300);
  await page.locator(`input[name="pass_product_id"][value="${PASS_ID}"]`).check();
  const submitBtn = page.locator('form[action="/reservar/paso-1"] button[type="submit"]');
  await submitBtn.waitFor({ state: 'visible', timeout: 5000 });
  await submitBtn.click();
  await page.waitForURL('**/reservar/paso-2');
}

// ─── Step 1: /reservas ─────────────────────────────────────────────────────

async function completePaso2(page: Page) {
  const dateStr = tomorrowStr();

  // Wait for Alpine component to be ready
  await page.waitForSelector('[x-data]', { state: 'attached' });

  // Fill date and trigger Alpine's $watch via input + change events
  await page.locator('input[name="fecha"]').fill(dateStr);
  await page.evaluate((date) => {
    const input = document.querySelector('input[name="fecha"]') as HTMLInputElement;
    if (input) {
      input.value = date;
      input.dispatchEvent(new Event('input',  { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }, dateStr);

  // Wait for the API call and slots to appear
  await page.waitForResponse(
    resp => resp.url().includes('/api/v1/time-slots'),
    { timeout: 8000 }
  );
  const firstSlot = page.locator('.booking-slot:not(.booking-slot--full)').first();
  await expect(firstSlot).toBeVisible({ timeout: 8000 });
  await firstSlot.click();

  const paso2SubmitBtn = page.locator('form[action="/reservar/paso-2"] button[type="submit"]');
  await paso2SubmitBtn.waitFor({ state: 'visible', timeout: 5000 });
  await paso2SubmitBtn.click();
  await page.waitForURL('**/reservar/paso-3');
  return dateStr;
}

test('WIZ-1: /reservas renders cafe select and pass grid from PHP', async ({ page }) => {
  await loginAs(page);
  await page.goto('/reservas');

  // Page contains cafe dropdown with options (rendered by Alpine x-for from PHP-injected cafes)
  const cafeSelect = page.locator('select[name="cafe_id"]');
  await expect(cafeSelect).toBeVisible();
  await waitForCafeOptions(page);
  const optionCount = await cafeSelect.locator('option').count();
  expect(optionCount).toBeGreaterThan(1);

  // No loading state — cafes come from PHP, not AJAX
  await expect(page.locator('[data-loading]')).toHaveCount(0);
});

test('WIZ-1: selecting cafe reveals pass grid', async ({ page }) => {
  await loginAs(page);
  await page.goto('/reservas');
  await waitForCafeOptions(page);

  await page.selectOption('select[name="cafe_id"]', CAFE_ID);
  await page.waitForTimeout(200);

  const passGrid = page.locator('.booking-pass-grid');
  await expect(passGrid).toBeVisible();
  await expect(page.locator('.booking-pass').first()).toBeVisible();
});

test('WIZ-1: submit paso-1 redirects to /reservar/paso-2', async ({ page }) => {
  await loginAs(page);
  await completePaso1(page);
  await expect(page).toHaveURL(/\/reservar\/paso-2/);
});

// ─── Step 2: /reservar/paso-2 ─────────────────────────────────────────────

test('WIZ-2: paso-2 shows previous selection summary', async ({ page }) => {
  await loginAs(page);
  await completePaso1(page);

  const summary = page.locator('.booking-summary-mini');
  await expect(summary).toBeVisible();
  const text = await summary.textContent();
  expect(text?.length).toBeGreaterThan(5);
});

test('WIZ-2: selecting a date triggers slot fetch and renders slots', async ({ page }) => {
  await loginAs(page);
  await completePaso1(page);

  const dateStr = tomorrowStr();
  await page.locator('input[name="fecha"]').fill(dateStr);
  await page.evaluate((date) => {
    const input = document.querySelector('input[name="fecha"]') as HTMLInputElement;
    if (input) {
      input.value = date;
      input.dispatchEvent(new Event('input',  { bubbles: true }));
      input.dispatchEvent(new Event('change', { bubbles: true }));
    }
  }, dateStr);

  await page.waitForResponse(
    resp => resp.url().includes('/api/v1/time-slots'),
    { timeout: 8000 }
  );
  await expect(page.locator('.booking-slot').first()).toBeVisible({ timeout: 8000 });
});

test('WIZ-2: submit paso-2 with slot redirects to /reservar/paso-3', async ({ page }) => {
  await loginAs(page);
  await completePaso1(page);
  await completePaso2(page);
  await expect(page).toHaveURL(/\/reservar\/paso-3/);
});

// ─── Step 3: /reservar/paso-3 ─────────────────────────────────────────────

test('WIZ-3: paso-3 shows reservation summary (PHP-rendered, no JS)', async ({ page }) => {
  await loginAs(page);
  await completePaso1(page);
  const dateStr = await completePaso2(page);

  // Pure PHP-rendered summary: no Alpine, no loading state
  const dl = page.locator('.booking-summary-dl');
  await expect(dl).toBeVisible();

  // Fecha row shows tomorrow
  const [y, m, d] = dateStr.split('-');
  const fechaFmt = `${d}/${m}/${y}`;
  await expect(page.locator('.booking-summary-dl')).toContainText(fechaFmt);

  // Total is rendered server-side
  await expect(page.locator('.booking-summary__line--total')).toBeVisible();
  await expect(page.locator('.booking-summary__line--total')).toContainText('¥');

  // Paso-3 content is pure SSR — no x-data inside the booking card
  const contentAlpineData = await page.locator('.rsv2-card [x-data]').count();
  expect(contentAlpineData).toBe(0);
});

test('WIZ-3: confirming reservation redirects to /reservas/confirmacion/{id}', async ({ page }) => {
  await loginAs(page);
  await completePaso1(page);
  await completePaso2(page);

  await page.locator('form[action="/reservar"] button[type="submit"]').click({ force: true });
  await expect(page).toHaveURL(/\/reservas\/confirmacion\/\d+/);
});

// ─── PRG: Back button works without JS ───────────────────────────────────

test('WIZ-PRG: back link on paso-3 is <a href>, not JS history', async ({ page }) => {
  await loginAs(page);
  await completePaso1(page);
  await completePaso2(page);

  // Volver link is an anchor, not a button with onClick handler
  const backLink = page.locator('a:has-text("Volver"), a:has-text("volver")').first();
  await expect(backLink).toBeVisible();
  const href = await backLink.getAttribute('href');
  expect(href).toBe('/reservar/paso-2');

  // Clicking it navigates back server-side
  await backLink.click();
  await expect(page).toHaveURL(/\/reservar\/paso-2/);
});

// ─── Auth guard ──────────────────────────────────────────────────────────

test('WIZ-AUTH: unauthenticated access to paso-2 redirects to /login', async ({ page }) => {
  await page.goto('/reservar/paso-2');
  await expect(page).toHaveURL(/\/login/);
});

test('WIZ-AUTH: unauthenticated access to paso-3 redirects to /login', async ({ page }) => {
  await page.goto('/reservar/paso-3');
  await expect(page).toHaveURL(/\/login/);
});
