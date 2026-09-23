#!/usr/bin/env node
/* NexaDash — cek horizontal overflow di 5 breakpoint + screenshot sampel.
   Jalankan: node tools/qa-responsive.js  */
const fs = require('fs');
const path = require('path');
const puppeteer = require('puppeteer-core');
const CHROME = process.env.NX_CHROME || require('os').homedir() + '/.cache/puppeteer/chrome/mac_arm-152.0.7977.75/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing';

const BASE = process.env.NX_BASE || 'http://localhost:8888/nexa/dashboard-template/';
const ROOT = path.join(__dirname, '..');
const SHOTS = process.env.NX_SHOTS || path.join(__dirname, 'screenshots');

const WIDTHS = [
  { name: 'xl', w: 1440, h: 900 },
  { name: 'lg', w: 1100, h: 800 },
  { name: 'md', w: 900, h: 800 },
  { name: 'sm', w: 700, h: 800 },
  { name: 'xs', w: 390, h: 844 }
];

const SHOT_PAGES = ['index.html', 'dashboards/clinical.html', 'apps/kanban.html', 'ui/buttons.html', 'auth/login.html'];

(async () => {
  fs.mkdirSync(SHOTS, { recursive: true });
  const SKIP = new Set(['tools', 'partials', 'node_modules', 'assets', '.git']);
  const walk = (dir, base = '') => fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => {
      if (SKIP.has(e.name)) return [];
      const rel = base ? base + '/' + e.name : e.name;
      if (e.isDirectory()) return walk(path.join(dir, e.name), rel);
      return e.name.endsWith('.html') ? [rel] : [];
    });
    const pages = walk(ROOT).sort();
  const browser = await puppeteer.launch({ headless: 'new', executablePath: CHROME, args: ['--no-sandbox'] });
  const problems = [];

  for (const file of pages) {
    const page = await browser.newPage();
    for (const bp of WIDTHS) {
      await page.setViewport({ width: bp.w, height: bp.h });
      if (bp === WIDTHS[0]) {
        await page.goto(BASE + file, { waitUntil: 'networkidle2', timeout: 45000 });
        await new Promise(r => setTimeout(r, 900));
      } else {
        await new Promise(r => setTimeout(r, 450));
      }
      const over = await page.evaluate(() => {
        const de = document.documentElement;
        const diff = de.scrollWidth - de.clientWidth;
        if (diff <= 2) return null;
        // Cari elemen yang meluber.
        const guilty = [...document.querySelectorAll('body *')]
          .filter(el => el.getBoundingClientRect().right > de.clientWidth + 2)
          .slice(0, 3)
          .map(el => el.tagName.toLowerCase() + (el.className && typeof el.className === 'string' ? '.' + el.className.trim().split(/\s+/).slice(0, 2).join('.') : ''));
        return { diff, guilty };
      });
      if (over) problems.push(`${file} @${bp.name}(${bp.w}px): +${over.diff}px — ${over.guilty.join(', ')}`);
    }

    if (SHOT_PAGES.includes(file)) {
      await page.setViewport({ width: 1440, height: 900 });
      await new Promise(r => setTimeout(r, 700));
      await page.screenshot({ path: path.join(SHOTS, file.replace(/\.html$/, '').replace(/\//g, '-') + '-light.png') });
      await page.evaluate(() => {
        document.documentElement.setAttribute('data-bs-theme', 'dark');
        document.dispatchEvent(new CustomEvent('nx:theme-changed', { detail: { theme: 'dark' } }));
      });
      await new Promise(r => setTimeout(r, 900));
      await page.screenshot({ path: path.join(SHOTS, file.replace(/\.html$/, '').replace(/\//g, '-') + '-dark.png') });
      await page.setViewport({ width: 390, height: 844 });
      await new Promise(r => setTimeout(r, 600));
      await page.screenshot({ path: path.join(SHOTS, file.replace(/\.html$/, '').replace(/\//g, '-') + '-mobile.png') });
    }
    await page.close();
  }

  await browser.close();
  console.log('\n=== Responsive check (' + pages.length + ' pages × ' + WIDTHS.length + ' breakpoints) ===\n');
  if (problems.length) { problems.forEach(p => console.log('✗ ' + p)); console.log('\n' + problems.length + ' overflow issue(s).'); }
  else console.log('✓ No horizontal overflow at any breakpoint.');
  console.log('\nScreenshots in ' + SHOTS);
})();
