# Cartopia portfolio showcase: design

- **Date:** 2026-11-10
- **Status:** awaiting review
- **Branch:** `showcase/portfolio-presentation`

## Goal

This presents Cartopia to two audiences:
- hiring managers who click the GitHub link on Amr's CV
- freelance clients deciding whether Amr can build their store

Neither audience clones the project. Each gives it a minute or two, and judges by what it sees first and by what the store does. Under the hood comes second.

**Success:** within a minute, a visitor knows what Cartopia is, has seen it working, knows Amr built it, and knows how to hire him.

**In scope:**
- a rewritten README, with the developer docs moved to `docs/`
- new screenshots
- repository details
- a fuller showcase catalogue and demo state
- a 90-second narrated walkthrough video

**Out of scope:** a hosted live demo.

## 1. README, docs and repository details

### README.md, top to bottom

1. **Title and one-line pitch.** For example: "An online store with a wallet, card and PayPal checkout, and an admin back office, rebuilt from a 2018 PHP project in Laravel 13 and Vue."
2. **Video:** the 720p walkthrough, uploaded through GitHub's editor so it plays in the page, with a YouTube link underneath.
3. **Main screenshots,** in a 2×2 grid:
   - the shop
   - checkout with the "Pay with" choices
   - the admin dashboard
   - a product page at phone width beside the desktop view

   A collapsed `<details>` section, "More screenshots", holds the full set.
4. **Work with me.** The text, verbatim:
   > Like what you see? I build online stores and custom web applications with Laravel and Vue, from the first idea to launch. If you have a project in mind, email me at [amrcs1992@gmail.com](mailto:amrcs1992@gmail.com) and tell me about it.
5. **What it does:** about six plain-language bullets on what a shopper and a store owner can do.
6. **What I built:** about six highlights:
   - real Stripe and PayPal payments in sandbox, with webhooks
   - a full Filament back office
   - money that can't go wrong: integer cents, locked transactions and an append-only ledger
   - reviews from buyers only
   - the automated test count, taken from a real run on the day, including browser tests
   - the 2018 → 2026 rewrite, linked to its doc
7. **Stack and quality:** one short list, with the CI badge.
8. **Run it locally:** two lines and a link to `docs/setup.md`.

### New docs pages

The current README text moves into these pages, mostly as it is:

| Page | Content | Fixes |
|---|---|---|
| `docs/setup.md` | Laragon setup and demo logins | Drop `git checkout laravel`; new demo login |
| `docs/payments-sandbox.md` | "Payments in sandbox mode", with setup and the "Try it" checklist | None |
| `docs/admin-panel.md` | "Admin panel" | None |
| `docs/rewrite-2018-2026.md` | The table of 2018 bugs and their 2026 fixes, plus the money note | None |

### Demo login

The seeded demo shopper changes from Amr's personal address to:
- **Email:** `demo@cartopia.test`
- **Password:** `12345`
- **Name:** `Sam Rivera`, so the header and screenshots show a natural name

To match, update `DatabaseSeeder` and `DatabaseSeederTest`. Amr's own local account is untouched until he reseeds.

### Repository details, set with `gh repo edit`

- **Description:** "Online store with wallet, Stripe/PayPal checkout and a Filament admin panel. Laravel 13, Vue 3, Inertia."
- **Topics:** laravel, vue, inertiajs, typescript, tailwindcss, filament, stripe, paypal, pest, ecommerce
- **Homepage:** the YouTube URL, set once Amr has uploaded the video.

## 2. Showcase data

### Fuller catalogue

About 24 products across 6 aisles, all featured as today. The current three aisles are fruit and veg, drinks and dairy. Three new aisles are added: bakery, pantry and snacks.
- **Where it lives:** `DatabaseSeeder`, so anyone who installs the project gets a full store.
- **Photo sources:** free stock photos from Unsplash or Pexels, chosen for a consistent look: one product on a plain, light background.
- **Approval before download:** before anything is downloaded, Amr approves the list of files, each with its source URL and licence.
- **Credits:** each photo is credited in `docs/photo-credits.md`.
- **Format:**
  - square, 1200 × 1200, which meets the admin panel's photo rule
  - JPG or WebP, at most 250 KB each
  - stored in `database/seeders/images/`
- **Product data:** names, short names, descriptions, prices and SKUs are written for each product. Prices are in cents, as in the current seeder.

### ShowcaseSeeder

`ShowcaseSeeder` runs on top of `DatabaseSeeder` with `php artisan migrate:fresh --seed --seeder=ShowcaseSeeder`. It only runs when asked, and resets the local `cartopia` database to one known state:

**The demo shopper**
- 3 items in the basket
- a wallet balance of $42.50
- 4 past orders: at least one paid by wallet and one split between wallet and Stripe, the latter recorded as a settled payment
- 2 reviews on products they bought

**Other customers**
- about 8 other customers, with orders spread over the past 7 days, so the admin dashboard's sales chart shows a believable week
- some reviews, so product pages show ratings

**Determinism:** dates are relative to the moment it runs, and every count is fixed.

## 3. Capture tooling

A new top-level `showcase/` folder holds the screenshot and video work, with its own `package.json`. The store's `npm run build` and CI are unaffected.

| Part | Contents |
|---|---|
| `showcase/capture/` | Playwright scripts that drive https://cartopia.test |
| `showcase/voice/` | The narration script and a Python generator |
| `showcase/video/` | The Remotion project |

`showcase/README.md` explains the order to run things in.

The `showcase/` npm scripts:

| Script | What it does |
|---|---|
| `npm run screenshots` | Writes every still to `docs/screenshots/` |
| `npm run clips` | Records each video scene as a clip into `showcase/video/public/clips/` (git-ignored) |
| `npm run voice` | Renders the narration and its word timings |
| `npm run render` | Renders the final videos into `showcase/out/` (git-ignored) |

### Screenshots

- **Sizes:** desktop 1440 × 900 and phone 390 × 844, both at device scale 2.
- **Themes:** store pages are shot in light and dark. Admin pages are shot in light only.
- **Set:**
  - home, shop, product, basket, checkout, wallet, orders, receipt and the product page's reviews section
  - the admin dashboard and its orders, products, payments and reviews screens
- **Phone shot:** the product page at 390px, combined with the desktop view into one image.
- **File size:** compressed to under 400 KB each. Screenshots no longer used are deleted.
- **Run order:** screenshots run against the ShowcaseSeeder state, so the basket count and balance match in every shot.

### Recording

- **Format:** each scene is recorded at 1920 × 1080.
- **Pointer:** an injected pointer moves smoothly between targets, because headless recordings show none.
- **Stripe scene:** pays on Stripe's hosted page with the published test card `4242 4242 4242 4242`.
- **Prerequisites:** sandbox Stripe keys in `.env`, plus `stripe listen` and the queue worker running.
- **Refund scene:** refunds a wallet-paid order, so it doesn't depend on a provider.

## 4. The video

### Format

- **Length:** 90 seconds, 16:9, 30 fps.
- **Narration:** a calm, smooth male narrator.
- **Subtitles:** burned in at the bottom, timed to the voice, with each word highlighted as it is spoken.
- **Titles:** a short scene title as a headline at the top of each section.
- **Music:** soft background music that drops under the voice and rises between sections.

### Storyboard and narration

The script is about 180 words, and Amr may edit it before recording.

| Time | Scene | Title | Narration |
|---|---|---|---|
| 0–5s | Logo animates in; pitch | Cartopia | "This is Cartopia, an online store I rebuilt from the ground up." |
| 5–20s | Home, then shop: aisle filter, live search, product gallery zoom | Browse and search | "Shoppers browse by aisle, search as they type, and see every product up close." |
| 20–38s | Add to basket panel, then basket and checkout. The wallet covers part and Stripe pays the rest, then the receipt. | Pay your way | "Checkout is priced on the server. Pay from your wallet, by card or with PayPal, or split one order across them. Stripe confirms the payment, and the receipt arrives by email." |
| 38–48s | Wallet top-up and ledger, order history, leaving a review | Every cent tracked | "Every cent moves through a ledger, so balances always add up. And only real buyers can leave a review." |
| 48–66s | Admin dashboard, editing a product, hiding a review, a one-click refund | A full back office | "Behind the store sits a full back office. The owner tracks sales, manages products and reviews, and refunds an order in one click. The money goes straight back where it came from." |
| 66–78s | Phone frame beside the desktop, then dark mode wipes in | Any screen, light or dark | "It works on any screen, in light or dark." |
| 78–90s | Stack cards (Laravel, Vue, Filament, Stripe, PayPal, test count), then the closing card | Built by Amr | "Built with Laravel, Vue and Filament, and covered by hundreds of automated tests. Like what you see? I build online stores and web apps like this one. Let's talk about yours." |

The closing card reads:
> **Need a store like this?**
> I design and build custom web apps with Laravel and Vue.
> amrcs1992@gmail.com · github.com/amrcs92

### Craft

- **Camera:** eases into the area being used, then pulls back. No static full-screen recording runs longer than about 3 seconds.
- **Cuts:** land on narration pauses and music beats.
- **Brand:** titles and cards use the store's tokens (forest green, marigold, cream), with Young Serif titles and Figtree body text. The stall logo animates in.
- **Phone scene:** the phone sits in a simple device frame. Dark mode arrives as a diagonal wipe.
- **Sound:** the mix is normalised to −14 LUFS, YouTube's loudness target.

### Voice

- **Engine:** Kokoro, an open-source text-to-speech model run locally in a Python 3.12 virtual environment at `showcase/voice/.venv`, which is git-ignored.
- **Download approval:** installing it downloads the model from Hugging Face. That download waits for Amr's OK.
- **Choosing the voice:** the first two lines are rendered in 3–4 male voices, and Amr picks one by ear.
- **Subtitle timing:** Kokoro's word timestamps drive the subtitles and the `.srt`.

### Music

The music is generated by code in `showcase/video/` and rendered to a WAV: soft, warm, about 90 BPM. It has no licensing claims on YouTube.

### Deliverables, in `showcase/out/`

| File | Spec |
|---|---|
| `cartopia-walkthrough-1080p.mp4` | H.264, for YouTube |
| `cartopia-walkthrough-720p.mp4` | Under 10 MB, for the GitHub README |
| `cartopia-thumbnail.png` | 1280 × 720 |
| `cartopia-walkthrough.srt` | Subtitles |
| `youtube.md` | Title, description and tags, ready to paste |

None of these are committed. The render also copies all five to Amr's Downloads folder.

**Amr's steps:**
1. Upload the 1080p video to YouTube.
2. Drag the 720p video into the README in GitHub's web editor.
3. Send both links back. The README link and the repository homepage are then filled in.

## Checkpoints with Amr

1. Approve the stock photo list before downloading.
2. Approve the Kokoro model download, then pick the voice from samples.
3. Approve the final narration wording.
4. Review a draft cut with contact sheets before the final render.
5. Do the uploads in the Deliverables section, then send the links.

## Testing and checks

- **Unit/feature tests:** `DatabaseSeederTest` is updated for the new catalogue and demo login. A new `ShowcaseSeederTest` checks:
  - the demo shopper's basket holds 3 items
  - the balance is 4250 cents and equals the ledger sum
  - there are 4 orders and 2 reviews
  - other customers have orders in the last 7 days
- **Store checks:** the full store gates pass: Pint, Larastan, lint, format, types, build, Pest, and Pest browser.
- **Capture scripts:** they run end to end against a fresh ShowcaseSeeder state, and every expected file appears.
- **Video review:** contact sheets, frames every 2 seconds, are checked for:
  - subtitle sync
  - text that doesn't fit its frame
  - empty or loading states caught on camera
- **README:** checked on GitHub after pushing: the images load, the links resolve and the `<details>` section opens.

## Delivery

- **Branch:** all work goes on `showcase/portfolio-presentation`, committed and pushed as each milestone lands.
- **Merge:** a PR into `main` is merged once CI passes, and the branch is then deleted locally and on GitHub.
- **Video links:** once Amr has uploaded the videos, the README video link and repository homepage follow in a small second PR.
