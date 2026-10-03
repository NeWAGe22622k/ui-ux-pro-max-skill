# Placeholder checklist

Everything below is placeholder content to replace before (or soon after) going live.

## Change in the admin (no code)

**Site settings** (`/admin` → Site settings)
- [ ] Phone number (currently `+44 (0)000 000 0000`)
- [ ] Public email (currently `hello@your-domain.co.uk`)
- [ ] Enquiry email (where contact-form messages are sent)
- [ ] Office address (currently `[Office address line] / [City], [Postcode]`)
- [ ] Opening hours
- [ ] Company registration number (currently `[Company number]`)
- [ ] WhatsApp number, Facebook / Instagram / LinkedIn links (optional; hidden while empty)
- [ ] Home-page heading and introduction (optional; current text is ready to use)

**Listings** (each shows a "Sample listing" badge until edited)
- [ ] Rentals: 4 sample homes. Replace with real ones, including real Rightmove / Zoopla / OpenRent links
- [ ] Managed properties: 4 samples
- [ ] Renovation projects: 3 samples, each with before/after photos

## Change in the files

| File | What to replace |
|---|---|
| `public_html/index.php` | Stats `[X]+ Years of experience` and `[X]% Average occupancy`; the testimonial quote, `[Client name]` and `[City]` |
| `public_html/about.php` | "Our story" text (`[year]`, `[area]`); the three team members' names, roles and photos |
| `public_html/services.php` | Service descriptions and bullet points: check they match what you offer |
| `public_html/assets/img/placeholders/hero.svg` | Home page hero photo. Upload a real photo (portrait, about 1200×1400) and update the path in `index.php` |
| `public_html/assets/img/placeholders/about.svg` | About page photo (about 1200×1000), path in `about.php` |
| `public_html/assets/img/placeholders/team-1.svg` … `team-3.svg` | Team headshots (square, about 1000×1000), paths in `about.php` |

Tip: put new photos in `public_html/assets/img/` (e.g. `assets/img/hero.jpg`) and change the path in the PHP file.
Every placeholder spot in the code is marked with a `PLACEHOLDER` comment, so you can search for that word.

## Assumptions to confirm

- **UK-based business:** British English, `£` rents, `pcm`, UK listing sites (Rightmove, Zoopla, OpenRent).
  Rent is typed freely per listing, so any currency works.
- **Services offered:** property management, lettings/tenant finding, renovation, and buying properties.
  Remove any that don't apply in `services.php` (and the matching cards on the home page).
