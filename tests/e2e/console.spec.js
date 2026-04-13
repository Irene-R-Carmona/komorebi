/* Playwright E2E: comprobar que no hay errores en consola en páginas críticas */
const { test, expect } = require('@playwright/test');

const PATHS = ['/', '/cafes', '/menu', '/historia', '/contacto', '/quiz', '/cafes/alpaca-hill', '/cafes/mipig-cafe'];

for (const path of PATHS) {
  test(`Console should be clean: ${path}`, async ({ page }) => {
    const errors = [];
    page.on('console', msg => {
      if (msg.type() === 'error') errors.push(msg.text());
    });
    page.on('pageerror', err => { errors.push(String(err)); });

    await page.goto(path);

    expect(errors).toHaveLength(0);
  });
}
