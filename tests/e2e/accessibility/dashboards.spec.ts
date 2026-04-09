import { test, expect } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

const dashboards = [
  { url: '/manager/dashboard', name: 'Manager Dashboard' },
  { url: '/supervisor/dashboard', name: 'Supervisor Dashboard' },
  { url: '/backoffice/keeper/dashboard', name: 'Keeper Dashboard' },
];

// Skip auth for local testing (configurar según necesidad)
test.beforeEach(async ({ page }) => {
  // TODO: Implementar login automático si es necesario
  // await page.goto('/login');
  // await page.fill('[name="email"]', 'manager@test.com');
  // await page.fill('[name="password"]', 'password');
  // await page.click('button[type="submit"]');
});

for (const dashboard of dashboards) {
  test(`${dashboard.name} - WCAG 2.1 AA compliance`, async ({ page }) => {
    await page.goto(dashboard.url);
    await page.waitForLoadState('networkidle');

    const results = await new AxeBuilder({ page })
      .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa'])
      .analyze();

    expect(results.violations).toEqual([]);
  });

  test(`${dashboard.name} - Keyboard navigation`, async ({ page }) => {
    await page.goto(dashboard.url);
    await page.keyboard.press('Tab');

    const focusedElement = await page.evaluate(() => {
      const el = document.activeElement;
      const styles = window.getComputedStyle(el);
      return {
        tagName: el?.tagName,
        outline: styles.outline,
        boxShadow: styles.boxShadow,
        visible: el !== null
      };
    });

    expect(focusedElement.visible).toBeTruthy();
    expect(
      focusedElement.outline !== 'none' || focusedElement.boxShadow !== 'none'
    ).toBeTruthy();
  });

  test(`${dashboard.name} - Color contrast`, async ({ page }) => {
    await page.goto(dashboard.url);

    const results = await new AxeBuilder({ page })
      .withTags(['cat.color'])
      .analyze();

    expect(results.violations).toEqual([]);
  });
}
