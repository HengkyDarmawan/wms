#!/usr/bin/env node
/* NexaDash — verifikasi setiap href/src lokal di seluruh halaman benar-benar ada.
   Jalankan: node tools/qa-links.js  */
const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..');
const SKIP = new Set(['tools', 'partials', 'node_modules', '.git']);

const walk = (dir, base = '') => fs.readdirSync(dir, { withFileTypes: true }).flatMap(e => {
  if (SKIP.has(e.name)) return [];
  const rel = base ? base + '/' + e.name : e.name;
  if (e.isDirectory()) return walk(path.join(dir, e.name), rel);
  return e.name.endsWith('.html') ? [rel] : [];
});

const pages = walk(ROOT).sort();
const broken = [];
let checked = 0;

pages.forEach(page => {
  const html = fs.readFileSync(path.join(ROOT, page), 'utf8');
  const dir = path.dirname(path.join(ROOT, page));

  [...html.matchAll(/(?:href|src|data-quick-action-href)="([^"]+)"/g)].map(m => m[1]).forEach(value => {
    if (/^(https?:|\/\/|#|mailto:|tel:|data:)/.test(value)) return;
    const target = value.split('#')[0];
    if (!target) return;
    checked++;
    if (!fs.existsSync(path.resolve(dir, target))) broken.push(`${page} -> ${value}`);
  });
});

console.log(`\n=== Link check: ${pages.length} pages, ${checked} local references ===\n`);
if (broken.length) {
  broken.forEach(b => console.log('✗ ' + b));
  console.log('\n' + broken.length + ' broken reference(s).');
  process.exit(1);
}
console.log('✓ Every local link and asset resolves.');
