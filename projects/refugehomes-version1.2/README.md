# Refugehomes Ltd website — version1.2

A minimal, professional website for Refugehomes Ltd, with a built-in admin panel for managing properties and rentals. It needs no database and no build step. It runs on any Hostinger plan that has PHP 8.0 or newer, which is every current plan.

| Page | File |
|---|---|
| Home | `index.php` |
| About us | `about.php` |
| Services | `services.php` |
| Our Properties: managed properties and before/after projects, with photo galleries | `properties.php` |
| Rentals: available homes, full details and links to Rightmove, Zoopla and other portals | `rentals.php` |
| Contact: the form emails you | `contact.php` |
| Privacy, terms and cookies (placeholder text) | `legal.php` |
| **Admin panel** | `/admin` |

---

## 1. Put it on Hostinger (first time)

1. In **hPanel → Websites → Manage → File Manager**, open `public_html`.
   - If an old site is there, download a backup first.
   - Then delete the old files.
2. Upload **everything inside this folder**, including the hidden files `.htaccess` and `.user.ini`.
   - Upload the contents of this folder, not the folder itself.
   - The easiest way is to zip the folder's contents on your computer, upload the zip, then right-click it → **Extract**.
3. Make sure the `data` and `uploads` folders are writable. Hostinger's default permission, 755, is fine.
4. Under **hPanel → Advanced → PHP Configuration**, choose **PHP 8.1 or newer**.
5. Under **hPanel → Security → SSL**, make sure SSL is installed. The site sends everyone to `https://` automatically.
6. **Straight away**, visit `https://yourdomain/admin` and **choose your admin password**.
   - The first person to visit `/admin` sets the password, so do this as soon as the files are uploaded.

## 2. Day-to-day: using the admin panel

Go to **`https://yourdomain/admin`** and log in.

- **Our Properties**
  - Click **+ Add property**.
  - Choose **Managed property** for a normal photo gallery, or **Before & after** for a refurbishment or flip project.
  - Upload photos and click **Save**.
  - Drag photos to reorder them. The first photo is the cover.
- **Rentals**
  - Click **+ Add rental**.
  - Fill in the rent, bedrooms, description and features (one per line), and upload photos.
  - Under **Where it is advertised**, paste the link for each portal the home is listed on.
  - When a home is taken, click **Mark let**. It stays on the site with a *Let agreed* label. Click **Hide** to remove it from the site completely.
- Use **↑ / ↓** to change the order things appear in on the website.
- **Hide / Publish** lets you prepare a listing before it goes live.
- **Site details** is where you change the phone numbers, email, address, opening hours and social media links.
  - Any social link left blank is hidden.
  - This is also where you change your password.

Photos straight from a phone are fine. They are resized automatically to keep the site fast.

## 3. Replacing the placeholder content

- **Property and rental photos and text:** use the admin panel. Delete the `[SAMPLE]` and `[PLACEHOLDER]` entries once your real ones are in.
- **Home, About and Services photos:** replace the files in `assets/img/placeholder/`, keeping the same file names. For example, `hero.jpg` is the large home-page photo, and `founder-1.jpg` to `founder-3.jpg` are the founder headshots (portrait, about 900×1100).
- **Privacy, terms and cookies:** edit `legal.php`. Search the file for `[PLACEHOLDER]`.
- **Page wording:** edit the matching `.php` file. The text sits between the HTML tags.

## 4. Updating the website files later

Your properties, rentals, settings, password and uploaded photos live in **`data/`** and **`uploads/`**.

**When you upload a new version of the site, do not overwrite or delete those two folders**, or you will lose your listings.

## 5. Contact form emails

Messages go to the address in **Admin → Site details → "Send contact-form messages to"**.

If they don't arrive:

1. Check your spam folder.
2. Make sure the *website email* (also under Site details) is an address on your own domain, such as `admin@refugehomesuk.com`, and exists in **hPanel → Emails**.

If the mail server is ever down, messages are saved to `data/unsent-enquiries.log`. Open it in the File Manager.

## 6. Preview on your own computer (optional)

```bash
php -S localhost:8000 -t projects/refugehomes-version1.2
```

Then open http://localhost:8000.

## How it fits together (for a developer)

```
includes/        shared PHP (blocked from the web): lib.php helpers, header/footer, cards, admin auth
assets/          css/style.css (design tokens at the top), js/main.js, js/listings.js, img/
data/            properties.json, rentals.json, settings.json, auth.json (blocked from the web)
uploads/         admin-uploaded photos (scripts can't run here)
admin/           password-protected panel: index (list), edit, action, settings
```

- Colours and fonts are CSS variables at the top of `assets/css/style.css`.
- The brand green `#1a652e` was sampled from the logo.
- Headings use Cormorant Garamond and body text uses Inter, both from Google Fonts.

Security:

- Passwords are hashed with bcrypt.
- Every admin form has a CSRF token.
- After 5 failed logins, that IP address is locked out for 15 minutes.
- JSON files are written atomically, with a `.bak` copy of the previous version.
- Uploads are re-encoded as JPEG.

## Versions

Two complete, separate versions of the site live side by side in this repository. Each folder is a full site you can upload to Hostinger on its own.

| Version | Folder | What's different |
|---|---|---|
| **version1.1** | `projects/refugehomes-version1.1/` | The site as deployed live in October 2026, with the original Services page (four alternating image/text blocks). |
| **version1.2** | `projects/refugehomes-version1.2/` | Same site with the redesigned Services page: service index, guaranteed-rent flagship section with "How it works", condition tags, numbered service list, linked audience tiles. |

Snapshots before any later changes: version1.1 = commit `95aa6a1`, version1.2 = commit `550841b`.

**To switch the live site to either version:** upload `refugehomes-version1.1.zip` or `refugehomes-version1.2.zip` to `public_html` and extract it, choosing *Overwrite*. Neither zip contains `data/` or `uploads/`, so listings, photos, settings and the admin password are kept.
