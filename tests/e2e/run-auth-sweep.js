#!/usr/bin/env node
// Barrido autenticado usando Playwright
// Ejecutar: node tests/e2e/run-auth-sweep.js

const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const BASE = process.env.BASE_URL || 'http://localhost:8080';
const EMAIL = process.env.E2E_USER_EMAIL || 'admin@komorebi.local';
const PASSWORD = process.env.E2E_USER_PASSWORD || 'Admin123!';

// Rutas que requieren autenticación o son privadas
const PATHS = [
  '/admin',
  '/admin/dashboard',
  '/admin/users',
  '/admin/waitlists',
  '/backoffice',
  '/backoffice/keeper',
  '/profile',
  '/reservas'
];

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  const timestamp = new Date().toISOString().replaceAll(/[:.]/g, '-');
  const logDir = path.join(__dirname, '..', '..', 'storage', 'logs');
  if (!fs.existsSync(logDir)) fs.mkdirSync(logDir, { recursive: true });
  const outFile = path.join(logDir, `auth-sweep-${timestamp}.log`);

  const results = [];

  // Login
  try {
    const loginUrl = `${BASE}/login`;
    await page.goto(loginUrl, { waitUntil: 'load', timeout: 30000 });

    // Rellenar formulario
    await page.fill('input[name="email"]', EMAIL);
    await page.fill('input[name="password"]', PASSWORD);
    await Promise.all([
      page.waitForNavigation({ waitUntil: 'load', timeout: 30000 }),
      page.click('button[type="submit"]')
    ]);
  } catch (e) {
    console.error('Login failed:', e.message);
    fs.writeFileSync(outFile, `Login failed: ${e.message}\n`);
    await browser.close();
    process.exit(1);
  }

  for (const p of PATHS) {
    const errors = [];
    const warnings = [];
    const failedRequests = [];

    page.removeAllListeners();
    page.on('console', msg => {
      const type = msg.type();
      const text = msg.text();
      if (type === 'error') errors.push(text);
      else if (type === 'warning') warnings.push(text);
    });
    page.on('pageerror', err => { errors.push(String(err)); });
    page.on('requestfailed', req => {
      const failure = req.failure();
      failedRequests.push({ url: req.url(), status: failure && failure.errorText ? failure.errorText : 'requestfailed' });
    });
    page.on('response', res => {
      try {
        const status = res.status();
        if (status >= 400) {
          failedRequests.push({ url: res.url(), status });
        }
      } catch (e) {
        // ignore
      }
    });

    const url = `${BASE}${p}`;
    try {
      await page.goto(url, { waitUntil: 'load', timeout: 30000 });
    } catch (e) {
      errors.push(`Navigation error: ${e.message}`);
    }

    results.push({ path: p, url, errors, warnings, failedRequests });
  }

  await browser.close();

  const out = [];
  out.push(`Authenticated console sweep - ${new Date().toISOString()}`);
  out.push('========================================');
  out.push(`Logged in as: ${EMAIL}`);
  out.push('');
  for (const r of results) {
    out.push(`PATH: ${r.path}  URL: ${r.url}`);
    out.push(`  Errors: ${r.errors.length}`);
    for (const e of r.errors) out.push(`    - ${e}`);
    out.push(`  Warnings: ${r.warnings.length}`);
    for (const w of r.warnings) out.push(`    - ${w}`);
    out.push(`  FailedRequests: ${r.failedRequests ? r.failedRequests.length : 0}`);
    if (r.failedRequests && r.failedRequests.length > 0) {
      for (const fr of r.failedRequests) {
        out.push(`    - ${fr.status}  ${fr.url}`);
      }
    }
    out.push('');
  }

  fs.writeFileSync(outFile, out.join('\n'));
  console.log(`Wrote authenticated sweep to ${outFile}`);
  process.exit(0);
})();
