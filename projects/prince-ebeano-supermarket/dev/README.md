# Development tools

Nothing in `dev/` is uploaded to Hostinger. It is for running the site on your own computer,
testing it, and building a clickable preview to share.

## Run the site locally

Needs PHP 8.2+ (with `pdo_mysql` and `gd`) and MySQL or MariaDB.

```bash
dev/db.sh setup        # once: database, user and app/config.php
dev/db.sh demo         # sample stores and products, plus demo logins
php -S localhost:8000 -t public dev/router.php
```

Open http://localhost:8000. Demo logins: `owner` / `owner-demo-123`, and staff such as
`ikeja-drinks` / `demo-1234`.

## Tests

Uses Playwright with Chromium.

```bash
dev/db.sh reset                                         # the end-to-end test needs no logins yet
NODE_PATH=$(npm root -g) node dev/tests/e2e.js          # logins, permissions, stock updates, uploads
NODE_PATH=$(npm root -g) node dev/tests/layout-check.js # screenshots + no sideways scrolling
```

Screenshots are saved in `dev/out/shots/`.

## Clickable preview (no PHP needed)

```bash
dev/db.sh demo
php -S localhost:8000 -t public dev/router.php &
python3 dev/preview/build.py      # dev/out/preview/        real branding, open index.html
python3 dev/preview/debrand.py    # dev/out/preview-public/ placeholder name and logo
python3 -m http.server 8091 --directory dev/out/preview-public &
NODE_PATH=$(npm root -g) node dev/tests/preview.test.js
```

In the preview, search, filters, sorting and the staff morning update all work, but changes are
kept only in the viewer's browser.
