// End-to-end check of the real PHP site. Needs a fresh database with no logins:
//   dev/db.sh reset && php -S localhost:8000 -t public dev/router.php
//   NODE_PATH=$(npm root -g) node dev/tests/e2e.js
const { chromium } = require('playwright');
const { execFileSync } = require('child_process');
const fs = require('fs');
const path = require('path');

const BASE = process.env.BASE || 'http://localhost:8000';
const OUT = path.resolve(__dirname, '..', 'out');
fs.mkdirSync(OUT, { recursive: true });

// Test files for uploads: a real PNG and a text file pretending to be one.
const photo = path.join(OUT, 'test-photo.png');
const fake = path.join(OUT, 'fake.png');
execFileSync('php', ['-r', `$im=imagecreatetruecolor(600,600); imagefill($im,0,0,imagecolorallocate($im,20,60,160)); imagefilledellipse($im,300,300,360,360,imagecolorallocate($im,230,40,40)); imagepng($im, ${JSON.stringify(photo)});`]);
fs.writeFileSync(fake, 'not an image <?php echo 1; ?>');

const results = [];
const check = (name, ok, detail = '') => {
  results.push(ok);
  console.log(`${ok ? 'PASS' : 'FAIL'}  ${name}${detail ? '  — ' + detail : ''}`);
};

(async () => {
  const browser = await chromium.launch();
  const page = await (await browser.newContext()).newPage();
  page.on('pageerror', e => check('no JS errors', false, e.message));

  // Setup
  await page.goto(BASE + '/setup.php');
  await page.fill('#s-user', 'owner');
  await page.fill('#s-pass', 'owner-pass-123');
  await page.fill('#s-confirm', 'owner-pass-123');
  await page.click('button[type=submit]');
  check('setup creates owner and redirects to login', page.url().includes('/admin/login.php'));
  check('setup locks after first account', (await page.goto(BASE + '/setup.php')).status() === 404);

  // Owner
  await page.goto(BASE + '/admin/login.php');
  await page.fill('#l-user', 'owner');
  await page.fill('#l-pass', 'owner-pass-123');
  await page.click('button[type=submit]');
  check('owner logs in', page.url().includes('/admin/index.php'));

  await page.goto(BASE + '/admin/accounts.php');
  await page.selectOption('#bulk-branch', { label: 'Ikeja' });
  await page.click('form:has(#bulk-branch) button[type=submit]');
  const creds = {};
  for (const row of await page.$$('table:has(th:text("Password")) tbody tr')) {
    const cells = await row.$$eval('td', tds => tds.map(t => t.innerText.trim()));
    creds[cells[1]] = cells[2];
  }
  check('bulk created 8 Ikeja logins', Object.keys(creds).length === 8, Object.keys(creds).join(', '));
  await page.selectOption('#n-branch', { label: 'Lekki Phase 1' });
  await page.selectOption('#n-cat', { label: 'Drinks' });
  await page.click('form:has(#n-branch) button[type=submit]');
  creds['lekki-phase-1-drinks'] = await page.$eval('table:has(th:text("Password")) tbody tr td:nth-child(3)', td => td.innerText.trim());
  check('single login created', !!creds['lekki-phase-1-drinks']);

  // Staff: morning update
  const sp = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  sp.on('pageerror', e => check('no JS errors (staff)', false, e.message));
  await sp.goto(BASE + '/admin/login.php');
  await sp.fill('#l-user', 'ikeja-drinks');
  await sp.fill('#l-pass', creds['ikeja-drinks']);
  await sp.click('button[type=submit]');
  check('staff login without name is rejected', (await sp.content()).includes('Please type your name'));
  await sp.fill('#l-pass', creds['ikeja-drinks']);
  await sp.fill('#l-name', 'Chioma');
  await sp.click('button[type=submit]');
  const heading = await sp.textContent('h1');
  check('staff lands on Drinks · Ikeja', heading.includes('Drinks') && heading.includes('Ikeja'), heading);
  check('staff does not see owner menu', (await sp.$$('a[href*="accounts.php"]')).length === 0);

  const rowFor = name => sp.locator('[data-row]', { hasText: name });
  await rowFor('Coca-Cola 50cl').locator('label', { hasText: 'Out' }).click();
  await rowFor('Fanta Orange 50cl').locator('input[data-price]').fill('650');
  await rowFor('Malta Guinness').locator('label', { hasText: 'Not sold' }).click();
  const counter = await sp.textContent('[data-changed-count]');
  check('changed counter updates', counter.includes('3 products changed'), counter);
  check('price disabled when "Not sold"', await rowFor('Malta Guinness').locator('input[data-price]').isDisabled());
  await sp.click('.save-bar button[type=submit]');
  const flashText = await sp.textContent('.flash-success');
  check('save reports 3 changes', flashText.includes('Saved 3 changes'), flashText.trim());

  await sp.click('a:has-text("Add a product")');
  await sp.fill('#p-name', 'Pepsi 50cl');
  await sp.setInputFiles('input[name=photo]', photo);
  await sp.selectOption('#p-status', 'in_stock');
  await sp.fill('#p-price', '400');
  await sp.click('form.card button[type=submit]');
  check('product added', (await sp.textContent('.flash-success')).includes('Pepsi 50cl'));

  await sp.goto(BASE + '/admin/product.php?category=1');
  await sp.fill('#p-name', 'Fake');
  await sp.setInputFiles('input[name=photo]', fake);
  await sp.click('form.card button[type=submit]');
  check('non-image upload rejected', (await sp.content()).includes('Please upload a JPG, PNG or WebP photo'));

  // Shopper sees the changes
  const pp = await (await browser.newContext({ viewport: { width: 1440, height: 900 } })).newPage();
  await pp.goto(BASE + '/shop.php?branch=ikeja&category=drinks');
  const card = name => pp.locator('.product-card', { hasText: name });
  check('Coca-Cola shows Out of stock at Ikeja', (await card('Coca-Cola 50cl').textContent()).includes('Out of stock'));
  check('Fanta shows ₦650', (await card('Fanta Orange').textContent()).includes('₦650'));
  check('Malta hidden at Ikeja', (await card('Malta Guinness').count()) === 0);
  check('Pepsi listed with photo', (await card('Pepsi 50cl').locator('img').count()) === 1);
  const ribbon = await pp.textContent('.fresh-ribbon');
  check('fresh ribbon says today by Chioma', /today at .* by Chioma/.test(ribbon), ribbon.trim());
  await pp.goto(BASE + '/shop.php?branch=ikeja&category=drinks&sort=price_asc');
  const sorted = await pp.$$eval('.product-card', els => els.map(e => +e.dataset.price));
  check('sort by price works', sorted.every((v, i) => i === 0 || sorted[i - 1] <= v), sorted.join(','));
  await pp.goto(BASE + '/shop.php?branch=lekki-phase-1&category=drinks');
  check('Lekki still has Malta', (await card('Malta Guinness').count()) === 1);
  check('Lekki does not list Pepsi', (await card('Pepsi 50cl').count()) === 0);

  // Permissions
  await sp.goto(BASE + '/admin/index.php');
  const csrf = await sp.inputValue('input[name=csrf]');
  const post = (p, form) => sp.request.post(BASE + p, { form, maxRedirects: 0 });
  let r = await post('/admin/index.php', { csrf, branch_id: '5', category_id: '1', 'status[1]': 'out_of_stock' });
  check('ikeja-drinks cannot update Lekki stock (403)', r.status() === 403, String(r.status()));
  r = await post('/admin/index.php', { csrf, branch_id: '2', category_id: '2', 'status[7]': 'out_of_stock' });
  check('ikeja-drinks cannot update Ikeja cereals (403)', r.status() === 403, String(r.status()));
  r = await sp.request.get(BASE + '/admin/product.php?id=7');
  check('ikeja-drinks cannot edit a cereal product (403)', r.status() === 403, String(r.status()));
  r = await sp.request.get(BASE + '/admin/accounts.php');
  check('staff cannot open owner pages (403)', r.status() === 403, String(r.status()));
  r = await post('/admin/index.php', { branch_id: '2', category_id: '1', 'status[1]': 'in_stock' });
  check('POST without CSRF token rejected (400)', r.status() === 400, String(r.status()));

  // Login lockout
  const lp = await (await browser.newContext()).newPage();
  for (let i = 0; i < 6; i++) {
    await lp.goto(BASE + '/admin/login.php');
    await lp.fill('#l-user', 'ikeja-cereals');
    await lp.fill('#l-pass', 'wrong-password-' + i);
    await lp.fill('#l-name', 'Tester');
    await lp.click('button[type=submit]');
  }
  check('lockout after 5 failures', (await lp.content()).includes('Too many wrong attempts'));
  await lp.goto(BASE + '/admin/login.php');
  await lp.fill('#l-user', 'ikeja-cereals');
  await lp.fill('#l-pass', creds['ikeja-cereals']);
  await lp.fill('#l-name', 'Tester');
  await lp.click('button[type=submit]');
  check('even the right password is blocked during lockout', (await lp.content()).includes('Too many wrong attempts'));

  // Contact form reaches the owner inbox
  await pp.goto(BASE + '/contact.php');
  await pp.fill('#c-name', 'Ada Shopper');
  await pp.fill('#c-email', 'ada@example.com');
  await pp.fill('#c-subject', 'Do you stock ofada rice?');
  await pp.fill('#c-message', 'Hello, please do you have ofada rice at Ikeja?');
  await pp.click('main form button[type=submit]');
  check('contact form thanks the user', (await pp.textContent('.flash-success')).includes('Thank you, Ada Shopper'));
  await page.goto(BASE + '/admin/messages.php');
  check('owner inbox shows the message', (await page.content()).includes('Do you stock ofada rice?'));

  await page.goto(BASE + '/admin/activity.php');
  check("activity log lists Chioma's changes", (await page.locator('td', { hasText: 'Chioma' }).count()) >= 4);

  // First visit opens the store picker
  const fresh = await (await browser.newContext({ viewport: { width: 390, height: 844 } })).newPage();
  await fresh.goto(BASE + '/', { waitUntil: 'networkidle' });
  check('store picker opens on first visit', await fresh.locator('#branch-sheet[open]').count() === 1);

  await browser.close();
  const failed = results.filter(ok => !ok).length;
  console.log(`\n${results.length - failed}/${results.length} checks passed`);
  process.exit(failed ? 1 : 0);
})().catch(e => { console.error(e); process.exit(2); });
