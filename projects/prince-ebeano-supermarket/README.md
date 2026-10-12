# Prince Ebeano Supermarket — live stock website

Shoppers pick their store and see **what's on the shelves right now** — stock status, price in Naira and a photo for every product.
Every morning, staff for each **store + aisle** (e.g. *Ikeja · Drinks*) log in and update only their own products.

- Plain **PHP 8.2+ and MySQL** — runs on any Hostinger web hosting plan (Premium/Business). No Composer, no build step.
- Public pages: Home, Shop, Store locator, About, Gift vouchers, Careers, Contact.
- Staff area: morning stock update, add/edit products with photos, activity log.
- Owner area: branches, aisles, staff logins, job openings, contact inbox, site settings.

```
prince-ebeano-supermarket/
├── app/        PRIVATE code + config  → upload BESIDE public_html (never inside it)
├── public/     the website            → upload the CONTENTS into public_html
└── database/   schema.sql (+ optional sample-data.sql) → import with phpMyAdmin
```

---

## 1. Put it on Hostinger (about 20 minutes)

1. **Create the database** — hPanel → *Websites* → your site → *Databases* → *MySQL Databases*.
   Create a database and user, and note the **database name, username and password** (they look like `u123456789_ebeano`).
2. **Import the tables** — on the same page click *Enter phpMyAdmin* → select your database → *Import* →
   choose `database/schema.sql` → *Go*.
   Want demo stores, aisles and products to try things out? Also import `database/sample-data.sql`
   (it adds the 6 stores, 8 aisles and some products — you can edit or delete them later).
3. **Upload the files** — hPanel → *File Manager*. You'll see `domains/yourdomain.com/public_html`.
   - Upload everything **inside** `public/` into `public_html/` (including the hidden `.htaccess` files).
   - Upload the `app/` folder **next to** `public_html` so you have `domains/yourdomain.com/app/`.
     (Keeping it outside `public_html` means nobody can download your passwords or code.)
4. **Add your settings** — in `app/`, copy `config.sample.php` to `config.php` and fill in the database details
   from step 1, your web address, phone numbers, opening hours and social links.
5. **PHP version** — hPanel → *Advanced* → *PHP Configuration* → choose **PHP 8.2** or newer.
6. **HTTPS** — hPanel → *Security* → *SSL* → install the free SSL certificate. The site forces HTTPS automatically.
7. **Create the owner login** — visit `https://yourdomain.com/setup.php` and choose the owner username and password.
   This page switches itself off as soon as the owner exists.
8. **Create staff logins** — log in at `https://yourdomain.com/admin/` → *Staff logins* →
   *Set up a whole store* creates a login for every aisle at a store (e.g. `ikeja-drinks`, `ikeja-cereals`)
   and shows the passwords **once** — print the page and hand each password to the right team.

> The pages look for the private folder at `../app` relative to `public_html`, so the two folders must sit side by side.
> `app/.htaccess` is included as a safety net that blocks web access if it ever ends up somewhere public.

### Photos and permissions
Product photos are saved in `public_html/uploads/`. If uploads fail, set that folder to permission **755** in File Manager.
Photos are automatically resized to 800px and converted to WebP so pages stay fast on mobile data.

### Updating the site later
Upload changed files over the old ones. Your `config.php`, database and `uploads/` folder are never overwritten
because they aren't part of this repository.

---

## 2. Staff guide (print this for each team)

**Every morning, before opening:**

1. Go to **yourdomain.com/admin** on your phone or the store computer.
2. Enter your team's username (e.g. `ikeja-drinks`), the password, and **your own name**.
3. Walk your aisle. For each product tap:
   - **In** — plenty on the shelf
   - **Low** — only a few left
   - **Out** — none on the shelf
   - **Not sold** — this store doesn't carry it
4. Check the **₦ price** matches the shelf label. Type a new price if it changed.
5. Tap **Save & mark checked**. Shoppers now see *"Freshly checked today at 7:42am by Chioma"*.

**New product on the shelf?** Tap **Add a product**, type the name with brand and size (e.g. *Nestlé Milo 400g tin*),
take a photo, set the stock and price, and save. Other stores will see it too and can mark whether they sell it.

Your login can only change **your store and your aisle** — you can't break anything elsewhere.
If you forget the password, ask the owner to issue a new one.

---

## 3. Owner guide

| Page | What it's for |
| --- | --- |
| Morning update | Same screen as staff, but you can switch store and aisle |
| Activity log | Every stock/price change, who made it and when |
| Branches | Add stores, edit address/hours/phones/map link, or hide a store |
| Categories | Add or rename aisles, pick an icon and colour |
| Staff logins | Create logins, issue new passwords, switch logins off |
| Job openings | Roles shown on the Careers page |
| Messages | Messages sent from the Contact page |
| Site settings | Feedback/complaint QR codes, gift voucher values, careers email |

Phone numbers, opening hours and social links shown in the header/footer live in `app/config.php`.

### Logo
`public/assets/img/` holds the logo files:
- `logo-badge.png` — the original round badge (footer, sharing image, phone home-screen icon)
- `logo-mark.svg` / `favicon.svg` — the green "e" tile used in the header and browser tab
- `logo-badge.svg` — a sharp, scalable redraw of the badge used on the About page

To use a higher-resolution original, replace the file with one of the **same name** — no code changes needed.

---

## 4. Security built in

- Passwords hashed with `password_hash`; sessions regenerated on login; cookies are HttpOnly, Secure and SameSite.
- Every form has a CSRF token. Every database query uses prepared statements. Everything printed is escaped.
- Logins lock for 15 minutes after 5 wrong attempts.
- Staff permissions are enforced on the server for every read and write, not just hidden buttons.
- Uploaded photos are type-checked, size-limited (3MB), re-encoded (which strips hidden content) and renamed;
  nothing in `uploads/` can run as code.
- The contact form uses a hidden honeypot field and a per-visitor rate limit instead of a CAPTCHA.

## 5. Running it on your own computer

```bash
# needs PHP 8.2+ with pdo_mysql + gd, and MySQL/MariaDB
mysql -e "CREATE DATABASE ebeano CHARACTER SET utf8mb4"
mysql ebeano < database/schema.sql
mysql ebeano < database/sample-data.sql
cp app/config.sample.php app/config.php   # set db details, base_url http://localhost:8000, secure_cookies false
php -S localhost:8000 -t public
```

## 6. Later: pickup & delivery orders

The database is ready to grow into online orders:
- add a `quantity` column to `branch_stock` and decrement it on each sale (online and at the till),
- `stock_updates` already logs every change with who/when, which is the basis for reconciling online and in-store sales,
- add `orders` / `order_items` tables keyed by `branch_id`, so each order is fulfilled by one store.

Once the tills can report sales (or staff record them), "In / Low / Out" can be worked out automatically from quantity.
