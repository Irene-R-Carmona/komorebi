#!/usr/bin/env node
// Script de barrido de consola usando Playwright
// Ejecutar: node tests/e2e/run-console-sweep.js

const fs = require('node:fs');
const path = require('node:path');
const { chromium } = require('playwright');

const PATHS = ['/', '/cafes', '/menu', '/historia', '/contacto', '/quiz', '/cafes/alpaca-hill', '/cafes/mipig-cafe'];
const BASE = process.env.BASE_URL || 'http://localhost:8080';

(async () => {
  const browser = await chromium.launch();
  const page = await browser.newPage();

  const timestamp = new Date().toISOString().replaceAll(/[:.]/g, '-');
  const logDir = path.join(__dirname, '..', '..', 'storage', 'logs');
  if (!fs.existsSync(logDir)) fs.mkdirSync(logDir, { recursive: true });
  const outFile = path.join(logDir, `console-sweep-${timestamp}.log`);

  const results = [];

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
  out.push(`Console sweep result - ${new Date().toISOString()}`);
  out.push('========================================');
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
  console.log(`Wrote console sweep to ${outFile}`);
  process.exit(0);
})();
