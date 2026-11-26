// Clicks through the static preview the way a visitor would.
//   python3 -m http.server 8091 --directory dev/out/preview-public   (or dev/out/preview)
//   NODE_PATH=$(npm root -g) node dev/tests/preview.test.js
const { chromium } = require('playwright');

const B = (process.env.PREVIEW || 'http://127.0.0.1:8091') + '/';
const results = [];
const ck = (n, ok, d = '') => { results.push(ok); console.log((ok ? 'PASS ' : 'FAIL ') + n + (d ? ' — ' + d : '')); };

(async () => {
  const br = await chromium.launch();
  const p = await (await br.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  const errs = [];
  p.on('pageerror', e => errs.push(e.message));

  await p.goto(B + 'home.html');
  ck('first visit opens store picker', await p.locator('#branch-sheet[open]').count() === 1);
  await p.click('#branch-sheet a:has-text("Ikeja")');
  ck('picking Ikeja opens Ikeja home', p.url().endsWith('ikeja-index.html'));

  await p.fill('#site-q', 'milo');
  await p.click('.header-search button');
  await p.waitForURL(/ikeja-shop\.html/);
  const vis = await p.locator('.product-card:visible').count();
  ck('search finds Milo', vis === 1 && (await p.locator('.product-card:visible .product-name').textContent()).includes('Milo'), vis + ' visible');

  await p.fill('#site-q', '');
  await p.click('.header-search button');
  await p.waitForLoadState();
  const all = await p.locator('.product-card:visible').count();
  await p.check('input[name=hide_out]');
  const inOnly = await p.locator('.product-card:visible').count();
  ck('hide sold out removes out-of-stock items', inOnly < all, `${all} → ${inOnly}`);

  await p.click('.aisle-bar a:has-text("Drinks")');
  ck('aisle bar opens Drinks', p.url().endsWith('ikeja-shop-drinks.html'));
  await p.selectOption('select[name=sort]', 'price_asc');
  const prices = await p.$$eval('.product-card:not([hidden])', els => els.map(e => +e.dataset.price));
  ck('sort by price works', prices.every((v, i) => i === 0 || prices[i - 1] <= v), prices.join(','));

  await p.goto(B + 'admin-login.html');
  await p.fill('#l-user', 'ikeja-drinks'); await p.fill('#l-pass', 'x'); await p.fill('#l-name', 'Bisi');
  await p.click('button[type=submit]');
  await p.waitForURL(/admin-staff\.html/);
  ck('staff login opens morning update', (await p.textContent('h1')).includes('Drinks'));
  ck('sidebar shows typed name', (await p.textContent('.admin-who strong')).trim() === 'Bisi');
  await p.locator('[data-row]', { hasText: 'Coca-Cola' }).locator('label', { hasText: 'Out' }).click();
  await p.locator('[data-row]', { hasText: 'Fanta' }).locator('input[data-price]').fill('750');
  await p.click('.save-bar button');
  ck('save shows confirmation', (await p.textContent('.pv-flash')).includes('Saved 2 changes'));
  await p.click('.pv-flash a');
  await p.waitForURL(/ikeja-shop-drinks/);
  ck('shop shows Coca-Cola out of stock', (await p.locator('.product-card', { hasText: 'Coca-Cola' }).textContent()).includes('Out of stock'));
  ck('shop shows Fanta at ₦750', (await p.locator('.product-card', { hasText: 'Fanta' }).textContent()).includes('₦750'));
  ck('freshness note names Bisi', (await p.textContent('#drinks .fresh-ribbon')).includes('by Bisi'));
  await p.goto(B + 'admin-staff.html');
  ck('morning page remembers saved state', await p.locator('[data-row]', { hasText: 'Coca-Cola' }).locator('input[value=out_of_stock]').isChecked());

  await p.goto(B + 'ikeja-contact.html');
  await p.fill('#c-name', 'Ada'); await p.fill('#c-message', 'Do you have ofada rice?');
  await p.click('main form button[type=submit]');
  ck('contact form shows thanks', (await p.textContent('.form-card')).includes('Thank you, Ada'));

  await p.goto(B + 'ikeja-careers.html');
  await p.click('.chips a:has-text("Wuse")');
  ck('careers filter by store', await p.locator('.job-card:visible').count() === 1);

  await p.goto(B + 'admin-login.html');
  await p.fill('#l-user', 'owner'); await p.fill('#l-pass', 'x');
  await p.click('button[type=submit]');
  await p.waitForURL(/admin-owner\.html/);
  await p.click('a:has-text("Messages")');
  ck('owner inbox has demo message', (await p.content()).includes('ofada rice'));

  await p.goto(B + 'ikeja-shop-drinks.html');
  await p.click('[data-pv-reset]');
  await p.waitForLoadState();
  ck('reset restores demo data', !(await p.locator('.product-card', { hasText: 'Coca-Cola' }).textContent()).includes('Out of stock'));
  ck('no sideways scrolling at 390px', await p.evaluate(() => document.documentElement.scrollWidth - innerWidth) <= 0);
  ck('no JS errors', errs.length === 0, errs.slice(0, 3).join(' | '));

  await br.close();
  const failed = results.filter(ok => !ok).length;
  console.log(`${results.length - failed}/${results.length} passed`);
  process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
