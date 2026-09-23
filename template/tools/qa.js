#!/usr/bin/env node
/* NexaDash — QA: buka setiap halaman di Chrome headless, catat error console,
   request gagal, dan periksa layout + dark mode. Jalankan: node tools/qa.js  */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');
const CHROME = process.env.NX_CHROME || require('os').homedir() + '/.cache/puppeteer/chrome/mac_arm-152.0.7977.75/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';

const BASE = process.env.NX_BASE || 'http://localhost:8888/nexa/dashboard-template/';
const ROOT = path.join(__dirname, '..');

(async () => {
  const SKIP = new Set(['tools', 'partials', 'node_modules', 'assets', '.git']);
  const walk = (dir, base = '') => fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => {
      if (SKIP.has(e.name)) return [];
      const rel = base ? base + '/' + e.name : e.name;
      if (e.isDirectory()) return walk(path.join(dir, e.name), rel);
      return e.name.endsWith('.html') ? [rel] : [];
    });
    const pages = walk(ROOT).sort();
  const browser = await puppeteer.launch({ headless: 'new', executablePath: CHROME, args: ['--no-sandbox'] });
  const report = [];

  for (const file of pages) {
    const page = await browser.newPage();
    await page.setViewport({ width: 1440, height: 900 });
    const errors = [];
    const failed = [];

    page.on('console', m => { if (m.type() === 'error') errors.push(m.text().slice(0, 220)); });
    page.on('pageerror', e => errors.push('PAGEERROR: ' + String(e).slice(0, 220)));
    page.on('requestfailed', r => {
      // Chrome membatalkan sisa request <video>/<audio> begitu buffer cukup.
      // Itu bukan kegagalan memuat, jadi jangan dihitung sebagai masalah.
      const aborted = (r.failure() || {}).errorText === 'net::ERR_ABORTED';
      if (aborted && r.resourceType() === 'media') return;
      failed.push(r.url().slice(0, 120));
    });
    page.on('response', r => {
      if (r.status() >= 400 && r.url().startsWith(BASE)) failed.push(r.status() + ' ' + r.url().replace(BASE, ''));
    });

    try {
      await page.goto(BASE + file, { waitUntil: 'networkidle2', timeout: 45000 });
      await new Promise(r => setTimeout(r, 1200));

      const info = await page.evaluate(() => {
        const isApp = document.body.hasAttribute('data-nx-layout');
        return {
          isApp,
          sidebar: !!document.querySelector('.nx-sidebar'),
          header: !!document.querySelector('.nx-header'),
          footer: !!document.querySelector('.nx-footer'),
          activeMenu: (document.querySelector('.nx-menu-link.active') || {}).textContent?.trim() || null,
          palette: !!document.getElementById('nxPalette'),
          overflowX: document.documentElement.scrollWidth > window.innerWidth + 2,
          charts: document.querySelectorAll('.apexcharts-canvas').length,
          h1: (document.querySelector('h1') || {}).textContent?.trim().slice(0, 60) || null
        };
      });

      // Dark mode: paksa light lalu dark, pastikan keduanya berbeda.
      const dark = await page.evaluate(() => {
        const set = t => {
          document.documentElement.setAttribute('data-bs-theme', t);
          document.dispatchEvent(new CustomEvent('nx:theme-changed', { detail: { theme: t } }));
          return getComputedStyle(document.body).backgroundColor;
        };
        const light = set('light');
        const dark = set('dark');
        return { light, dark, changed: light !== dark };
      });
      await new Promise(r => setTimeout(r, 400));

      report.push({ file, errors, failed, info, dark });
    } catch (e) {
      report.push({ file, errors: ['NAVIGATION: ' + e.message], failed, info: null, dark: null });
    }
    await page.close();
  }

  await browser.close();

  let problems = 0;
  console.log('\n=== NexaDash QA report (' + report.length + ' pages) ===\n');
  report.forEach(r => {
    const issues = [];
    if (r.errors.length) issues.push('JS errors: ' + r.errors.join(' | '));
    if (r.failed.length) issues.push('Failed requests: ' + [...new Set(r.failed)].join(', '));
    if (r.info) {
      if (r.info.isApp && !(r.info.sidebar && r.info.header && r.info.footer)) issues.push('layout partial missing');
      if (r.info.isApp && !r.info.activeMenu) issues.push('no active sidebar item');
      if (r.info.overflowX) issues.push('horizontal overflow');
      if (!r.info.palette) issues.push('command palette not built');
      if (r.dark && !r.dark.changed) issues.push('dark mode did not change body bg');
    }
    if (issues.length) {
      problems++;
      console.log('✗ ' + r.file);
      issues.forEach(i => console.log('    - ' + i));
    } else {
      console.log('✓ ' + r.file + (r.info && r.info.charts ? '  (' + r.info.charts + ' charts)' : ''));
    }
  });
  console.log('\n' + (problems ? problems + ' page(s) with issues.' : 'All pages clean.'));
  fs.writeFileSync(path.join(__dirname, 'qa-report.json'), JSON.stringify(report, null, 2));
})();
