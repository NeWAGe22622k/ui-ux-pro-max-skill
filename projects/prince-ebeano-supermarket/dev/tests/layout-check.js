// Screenshots the public pages at phone, tablet and desktop widths, and fails on
// horizontal scrolling or JavaScript errors.
//   NODE_PATH=$(npm root -g) node dev/tests/layout-check.js [store-slug]
const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://localhost:8000';
const STORE = process.argv[2] || 'ikeja';
const OUT = path.resolve(__dirname, '..', 'out', 'shots');
fs.mkdirSync(OUT, { recursive: true });

const pages = [
  ['home', '/'], ['shop', '/shop.php'], ['aisle', '/shop.php?category=drinks'], ['stores', '/stores.php'],
  ['about', '/about.php'], ['vouchers', '/gift-vouchers.php'], ['careers', '/careers.php'], ['contact', '/contact.php'],
];
const widths = [[390, 844, 'phone'], [768, 1024, 'tablet'], [1440, 900, 'desktop']];

(async () => {
  const browser = await chromium.launch();
  const problems = [];
  for (const [w, h, tag] of widths) {
    // Reduced motion: no scroll fade-ins (full-page shots never scroll) and no slider autoplay.
    const ctx = await browser.newContext({ viewport: { width: w, height: h }, deviceScaleFactor: tag === 'phone' ? 2 : 1, reducedMotion: 'reduce' });
    await ctx.addCookies([{ name: 'pe_branch', value: STORE, url: BASE }]);
    const p = await ctx.newPage();
    p.on('pageerror', e => problems.push(`JS error: ${e.message}`));
    for (const [name, url] of pages) {
      await p.goto(BASE + url, { waitUntil: 'networkidle' });
      const overflow = await p.evaluate(() => document.documentElement.scrollWidth - window.innerWidth);
      if (overflow > 0) problems.push(`${name} @${tag}: scrolls sideways by ${overflow}px`);
      await p.screenshot({ path: path.join(OUT, `${name}-${tag}.png`), fullPage: tag === 'desktop' });
      if (tag === 'phone') await p.screenshot({ path: path.join(OUT, `${name}-${tag}-full.png`), fullPage: true });
    }
    await ctx.close();
  }
  await browser.close();
  console.log(problems.length ? problems.join('\n') : `no sideways scrolling, no JS errors (screenshots in ${OUT})`);
  process.exit(problems.length ? 1 : 0);
})();
