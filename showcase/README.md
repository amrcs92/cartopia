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

## Scene clips

```bash
npm run clips              # every scene, after resetting the database
npx tsx capture/clips.ts checkout admin   # just these, without a reset
```

These record the walkthrough's scenes into `video/public/clips/` (git-ignored). Each scene is a 30 fps H.264 clip with a drawn pointer, and `manifest.json` notes each clip's length plus the moments and screen areas the edit zooms into.

The checkout scene pays in Stripe's sandbox with the test card `4242 4242 4242 4242`. Before you record:
- `.env` needs the Stripe sandbox keys.
- `stripe listen --forward-to https://cartopia.test/webhooks/stripe --skip-verify` must be running. See [payments in sandbox mode](../docs/payments-sandbox.md).

If a scene fails, the page it stopped on is saved to `out/failed-<scene>.png` and `.html`.

## Product photos

`photos/process.sh <raw-dir> <out-dir>` turns downloaded photos into catalogue images: 1200 × 1200 squares of 250 KB or less. `photos/candidates.md` lists where each one came from, and `docs/photo-credits.md` credits the photographers.
