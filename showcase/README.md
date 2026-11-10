# Showcase

The scripts that make the README screenshots and the walkthrough video. They are separate from the store: their own `package.json`, and the store's build, lint and CI never load them.

Every script that drives the store first resets your **local** database to the `ShowcaseSeeder` state, so each run shows the same basket, balance, orders and reviews. If `.env` doesn't have `APP_ENV=local`, or points at the test database, the script refuses to run.

## Setup

```bash
cd showcase
npm install
npx playwright install chromium
```

The scripts call `php` and ImageMagick's `magick`, so both must be on your `PATH`. If `php` isn't, set `PHP_BIN` to its full path. They expect the store at <https://cartopia.test>, or set `SHOWCASE_URL`.

## Screenshots

```bash
npm run screenshots
```

This writes every still to `docs/screenshots/` as a JPG of 400 KB or less:
- the store pages in light and dark
- the admin screens in light
- the product page at phone width
- `responsive.jpg`, which shows the desktop and phone views side by side

## Product photos

`photos/process.sh <raw-dir> <out-dir>` turns downloaded photos into catalogue images: 1200 × 1200 squares of 250 KB or less. `photos/candidates.md` lists where each one came from, and `docs/photo-credits.md` credits the photographers.
