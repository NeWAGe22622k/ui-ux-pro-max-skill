# Refugehomes Ltd website

A minimal, professional website for Refugehomes Ltd with a built-in admin panel.
It is plain PHP with no database, so it runs on any Hostinger web hosting plan.

**Pages:** Home · About us · Services · Our properties (managed portfolio + before/after renovation projects) · Rentals · Contact

**Admin (`/admin`):** add, edit, reorder and delete rentals, managed properties and renovation projects
(with photo uploads); edit contact details and home-page text; read contact-form enquiries.

---

## 1. Upload to Hostinger

1. Log in to **hPanel** and open **Websites → Manage → File Manager**.
2. Open the `public_html` folder. Delete Hostinger's default `default.php` / `index.html` if present.
3. Upload **the contents** of this project's `public_html/` folder (not the folder itself).
   The easiest way is to zip the contents, upload the zip, then right-click → **Extract**.
   Make sure hidden files such as `.htaccess` are included.
4. Under **Security → SSL**, make sure SSL is active and turn on **Force HTTPS**.
5. Visit `https://your-domain/admin` **straight away** and create your admin password.
   Until a password exists, the first person to open `/admin` can set one, so do this step immediately.

Requirements: PHP 8.1 or newer (Hostinger default is fine; check under **Advanced → PHP Configuration**).

### Folder permissions
The admin saves into `content/`, `private/` and `uploads/`. Hostinger's defaults (folders `755`, files `644`)
work. If you see "Could not save ... check folder permissions", set those three folders to `755`.

### Photo upload limits
Photos are automatically resized to 2000px and compressed, so large phone photos are fine.
If uploads fail, raise the limits under **Advanced → PHP Configuration → PHP options**:
`upload_max_filesize` = 20M, `post_max_size` = 128M, `max_file_uploads` = 50.
iPhone HEIC photos must be converted to JPG first (or set the iPhone camera to "Most Compatible").

### Contact form email
Enquiries are **always** saved in the admin under **Enquiries**, and also emailed to the address in
**Site settings**. For reliable delivery, create a mailbox on your domain in Hostinger (e.g. `hello@your-domain`)
and use it as the public / enquiry email.

---

## 2. Everyday editing (admin)

Go to `https://your-domain/admin` and log in.

| Task | Where |
|---|---|
| Add a home to rent | **Rentals → + Add rental**. Add photos, rent, details and the links to where it is advertised (Rightmove, Zoopla, OpenRent, etc.). |
| Mark a rental as taken | Edit it and set **Status → Let agreed** (it stays visible with a "Let agreed" badge), or **Hidden**, or delete it. |
| Show a rental on the home page | Tick **Show on the home page** (up to 3 appear). |
| Add a managed property | **Managed properties → + Add**. |
| Add a before/after renovation | **Renovation projects → + Add**. Upload *Before* and *After* photos of the same rooms **in the same order** so the comparison slider pairs them up. The first project in the list appears on the home page. |
| Change the order | Use the ↑ ↓ arrows in a list. The website shows items in the same order. |
| Reorder or remove photos | In the edit screen use ← → to move a photo, × to remove it. The first photo is the cover. |
| Change phone, email, address, hours, socials, home-page heading | **Site settings** |
| Read messages from the contact form | **Enquiries** |

Deleting a listing also deletes its uploaded photos from the server.

---

## 3. Placeholders to replace

The site launches with clearly marked placeholder content. See **[PLACEHOLDERS.md](PLACEHOLDERS.md)** for the full checklist.

- **Sample listings** show an amber **"Sample listing"** badge on the website and a **Sample** tag in the admin.
  Edit them with real details (the badge disappears when you save) or delete them.
- **Placeholder images** are labelled "PLACEHOLDER IMAGE" with a description of the photo needed.
- **Placeholder text** is written in `[square brackets]`.

---

## 4. Preview on your own computer (optional)

With PHP installed:

```bash
cd public_html
php -S localhost:8000 router.php
```

Then open http://localhost:8000 (admin at http://localhost:8000/admin).

---

## 5. How it is built (for developers)

```
public_html/
├── index.php, about.php, services.php, properties.php, rentals.php, contact.php, 404.php
├── admin/            Admin panel (index.php, admin.css, admin.js)
├── assets/           CSS, JS, logo, favicon, placeholder images
├── content/          Site content as JSON, written by the admin (blocked from public access)
│   ├── rentals.json, managed.json, flips.json, settings.json
├── private/          Admin password hash, enquiries, login attempts (blocked from public access)
├── uploads/          Uploaded photos (scripts can't run here)
├── includes/         Shared PHP: bootstrap, header, footer, cards, admin library
├── .htaccess         Clean URLs, security headers, caching, folder protection
└── router.php        Local preview only (ignored on Hostinger)
```

- Page copy for About, Services and the home page sections lives in the matching `.php` file.
- Listings render server-side (good for SEO). The photo/details modal is filled from JSON embedded in each page by `assets/js/site.js`.
- Every listing can be deep-linked, e.g. `/rentals#sample-rental-1` opens that home directly.
- Security: password hashing, login rate-limiting (5 attempts / 15 min), CSRF tokens on every form,
  session timeout, uploaded images re-encoded with GD (strips metadata and anything hidden in the file).

**Backups:** download `content/`, `private/` and `uploads/` regularly, or use Hostinger's backups.
When uploading a new version of the site, **don't overwrite those three folders**, or you will replace your live listings.
