# Cartopia Portfolio Showcase Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Present Cartopia to hiring managers and freelance clients through:
- a showcase README with docs pages behind it
- scripted screenshots
- a fuller seeded catalogue
- a narrated, subtitled 90-second walkthrough video

**Architecture:**
- **Store-side work** stays in Laravel: the seeders and their Pest tests.
- **Media work** lives in a new top-level `showcase/` folder with its own `package.json`:
  - Playwright scripts take the screenshots and record scene clips against https://cartopia.test.
  - A Python Kokoro script renders the narration with word timings.
  - A Python script composes the music.
  - A Remotion project assembles the final video.
- **Isolation:** the store's build, lint and CI never depend on `showcase/`.

**Tech Stack:**
- Laravel 13 and Pest 4
- Node 22 with TypeScript and vitest
- Playwright, already a dev dependency of the store
- Remotion 4
- Python 3.12 with `kokoro`, `soundfile` and `numpy`
- ImageMagick 7, already installed
- Remotion's bundled ffmpeg (`npx remotion ffmpeg`); ffmpeg is not on PATH

**Spec:** `docs/superpowers/specs/2026-11-10-cartopia-portfolio-showcase-design.md`

## Global Constraints

### Git
- **Branch:** all work on `showcase/portfolio-presentation`. Commit and push after every commit.
- **No attribution:** no `Co-Authored-By` or any AI attribution in commits or PR bodies.

### Shell
- **PHP:** `export PATH=/c/laragon/bin/php/php-8.3.36-Win32-vs16-x64:$PATH`.
- **Unit/feature tests:** `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest`.
- **Browser tests:** `npm run build`, then `... vendor/bin/pest tests/Browser`.
- **Database:** MySQL80 on port 3306 with the `.env` credentials. The site is https://cartopia.test.

### Store gates
These must stay green after every store-side task:
- `vendor/bin/pint --test`
- `composer analyse`
- `npm run lint`
- `npm run format:check`
- `npm run types`
- `npm run build`
- both Pest suites

### Demo shopper
- **Name:** `Sam Rivera`
- **Email:** `demo@cartopia.test`
- **Password:** `12345`

### Contact copy (verbatim)

**README "Work with me":**
> Like what you see? I build online stores and custom web applications with Laravel and Vue, from the first idea to launch. If you have a project in mind, email me at [amrcs1992@gmail.com](mailto:amrcs1992@gmail.com) and tell me about it.

**Closing card:**
> **Need a store like this?** / I design and build custom web apps with Laravel and Vue. / amrcs1992@gmail.com · github.com/amrcs92

**Narrator's last line:**
> Like what you see? I build online stores and web apps like this one. Let's talk about yours.

### Video format
- **Picture:** 1920 × 1080 at 30 fps, 2700 frames (90 s), with a tolerance of ±45 frames.
- **Sound:** mix at −14 LUFS integrated, true peak −1.5 dBTP.

### Deliverables, rendered in `showcase/out/` (git-ignored, never committed)
At the end, `npm run render` copies the five deliverables to `SHOWCASE_DELIVER_DIR`, which defaults to `os.homedir()/Downloads`. For Amr that is `C:\Users\engam\Downloads`, as he asked.

- `cartopia-walkthrough-1080p.mp4`
- `cartopia-walkthrough-720p.mp4`, at most 10 MB (10,485,760 bytes)
- `cartopia-thumbnail.png` at 1280 × 720
- `cartopia-walkthrough.srt`
- `youtube.md`

### Images
- **Product photos:** square 1200 × 1200, JPG, at most 250 KB.
- **Screenshots:** at most 400 KB each.

### Checkpoints
Every step marked **CHECKPOINT** stops for Amr's answer, and nothing past it runs until he replies. That covers:
- downloading files
- the voice model
- the voice choice
- the narration wording
- the draft cut

### Pinned versions
Every new npm or pip dependency is pinned to an exact version that is at least 2 weeks old.

## Review Focus

1. **Wrong database.** `npm run screenshots` or `npm run clips` run against a `.env` that isn't the local dev database must refuse to reset it. The guard is pinned by a test in Task 5.
2. **Half-loaded pages.** A screenshot or clip frame taken before images, fonts or Inertia transitions settle must not be saved. Task 5's `settle()` fails on any image with `naturalWidth === 0`.
3. **Stripe not settling.** If the Stripe webhook listener isn't running during the payment scene, the clip must fail loudly instead of recording a stuck "awaiting payment" receipt. Task 7 asserts "Paid" within 20 s.
4. **Date edges.** If ShowcaseSeeder runs at 23:59 or on a week boundary, every showcase order must still fall inside the last 7 days and none in the future. Task 4 has a test at a frozen 23:59.
5. **GitHub size limit.** A 720p render over 10 MB can't be uploaded to GitHub. Task 11's render script exits non-zero above the limit.

---

### Task 1: Demo shopper login

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`, the demo `User::factory()->create([...])` block
- Test: `tests/Feature/Domain/DatabaseSeederTest.php`

- [ ] **Step 1: Update the test's demo assertions**

```php
$demo = User::query()->where('email', 'demo@cartopia.test')->firstOrFail();
expect($demo->name)->toBe('Sam Rivera')
    ->and(Hash::check('12345', $demo->password))->toBeTrue()
    ->and($demo->balance->cents())->toBe(2000);
expect(User::query()->where('email', 'amrcs1992@gmail.com')->exists())->toBeFalse();
```

- [ ] **Step 2:** Run `... vendor/bin/pest tests/Feature/Domain/DatabaseSeederTest.php`. Expected: FAIL.
- [ ] **Step 3:** Change the seeder's demo user to the constants in Global Constraints.
- [ ] **Step 4:** Grep for `amrcs1992` outside `docs/superpowers/` and the README. Expected: only the README demo-login row, which Task 6 removes. Rerun the test. Expected: PASS.
- [ ] **Step 5:** Commit "Seed a neutral demo shopper" and push.

### Task 2: Stock photos

**Files:**
- Create:
  - `showcase/photos/candidates.md`
  - `showcase/photos/process.sh`
  - `database/seeders/images/<slug>.jpg`, 20 new files
  - `docs/photo-credits.md`
- Modify: `.gitignore` (add `/showcase/photos/raw`)

**Interfaces:**
- **Produces:** `database/seeders/images/<slug>.jpg` for each slug in Task 3's table, plus optional replacements for the 4 legacy photos.

**The 20 new products** (slug, aisle, name, price in cents):

| Aisle | Products |
|---|---|
| `fruit-and-veg` | `bananas` Bananas 129, `avocados` Avocados (pair) 249, `vine-tomatoes` Vine Tomatoes 189 |
| `drinks` | `orange-juice` Orange Juice 1L 279, `sparkling-water` Sparkling Water 6-pack 349 |
| `dairy` | `whole-milk` Whole Milk 2L 159, `greek-yogurt` Greek Yogurt 229, `free-range-eggs` Free-range Eggs (12) 319 |
| `bakery` (Bakery) | `sourdough-loaf` Sourdough Loaf 349, `croissants` Croissants (4) 299, `bagels` Bagels (6) 259, `blueberry-muffins` Blueberry Muffins (4) 279 |
| `pantry` (Pantry) | `olive-oil` Extra Virgin Olive Oil 649, `penne-pasta` Penne Pasta 149, `basmati-rice` Basmati Rice 1kg 299, `honey` Wildflower Honey 429 |
| `snacks` (Snacks) | `dark-chocolate` Dark Chocolate 199, `sea-salt-crisps` Sea Salt Crisps 179, `mixed-nuts` Mixed Nuts 449, `granola-bars` Granola Bars (6) 329 |

- [ ] **Step 1: Write `showcase/photos/candidates.md`.** One row per slug with:
  - the photo page URL on Unsplash or Pexels
  - the photographer
  - the licence: "Unsplash License" or "Pexels License"
  - a one-line description

  **Choosing photos:** pick a single product on a plain light background, shot from the front or at a three-quarter angle, so the set looks consistent. Add a second section that proposes replacements for `apple.jpg`, `monster-energy-drink.png`, `water_bottle.jpg` and `cheese-traingles.jpg`, each marked "optional".
- [ ] **Step 2: CHECKPOINT.** Show Amr the list:
  - the number of files
  - the sources
  - an estimate of the total download size

  Ask him to approve the downloads and to say yes or no to the legacy replacements. Swap any rows he rejects, then ask again.
- [ ] **Step 3: Download** the approved originals into `showcase/photos/raw/`, a new empty directory.
- [ ] **Step 4: Write `process.sh <raw> <out>`.** For each file it runs ImageMagick:
  - trim
  - fit inside 1080 × 1080
  - centre on a 1200 × 1200 canvas filled with the photo's own corner colour
  - strip metadata
  - save as JPG at quality 82, stepping the quality down to 70 if needed to reach 250 KB or less

  Run it into `database/seeders/images/`. Expected: 20 files, plus any approved replacements, each 1200 × 1200 and at most 250 KB. Check with `magick identify`.
- [ ] **Step 5: Write `docs/photo-credits.md`.** A table of product, photographer, source link and licence.
- [ ] **Step 6:** Commit "Add stock photos for the showcase catalogue" and push.

### Task 3: Fuller catalogue in DatabaseSeeder

**Files:**
- Modify: `database/seeders/DatabaseSeeder.php`:
  - extend `PRODUCTS`
  - extend the aisle map in `seedCatalog()`
  - apply the approved legacy photo replacements
- Test: `tests/Feature/Domain/DatabaseSeederTest.php`

**Interfaces:**
- **Consumes:** Task 2's images.
- **Produces:** 24 products in 6 aisles, in this order: `fruit-and-veg`, `drinks`, `dairy`, `bakery`, `pantry`, `snacks`. Each aisle has exactly 4 products.

- [ ] **Step 1: Update the test.**
  - The four legacy slugs keep their prices.
  - Their `image` assertion follows Task 2's decision.
  - Add these assertions:

```php
expect(Product::query()->count())->toBe(24)
    ->and(Category::query()->orderBy('id')->pluck('slug')->all())
    ->toBe(['fruit-and-veg', 'drinks', 'dairy', 'bakery', 'pantry', 'snacks']);
Category::query()->withCount('products')->get()
    ->each(fn ($c) => expect($c->products_count)->toBe(4));
Product::query()->with('images')->get()->each(function ($p) {
    expect($p->images)->toHaveCount(1)
        ->and($p->description)->toMatch('/\w+.*\.\s\w+/')   // at least two sentences
        ->and(strlen($p->sku))->toBe(14);
    Storage::disk('public')->assertExists($p->images[0]->path);
});
expect(Product::query()->distinct()->count('sku'))->toBe(24)
    ->and(Product::query()->where('slug', 'olive-oil')->first()->price->cents())->toBe(649);
```

  Replace the old `$aisles` `toBe([...])` block with this per-aisle check.
- [ ] **Step 2:** Run the test. Expected: FAIL (count 4).
- [ ] **Step 3: Add the 20 rows** from Task 2's table to `PRODUCTS`.
  - **Descriptions:** two or three plain sentences each.
  - **SKUs:** 14 digits and unique.
  - **Short names:** set `short_name` only where the name would wrap on a card, for example `Extra Virgin Olive Oil` → `Olive Oil`.
  - **Aisles:** add the three new aisles to the map.
- [ ] **Step 4:** Run the test. Expected: PASS. Then run the full unit/feature suite, because other tests may count products.
- [ ] **Step 5:** Run `php artisan migrate:fresh --seed` on the dev database, then open https://cartopia.test/products in the built-in browser. Expected: a full 4-column shop with no broken images.
- [ ] **Step 6:** Commit "Seed a fuller catalogue across six aisles" and push.

### Task 4: ShowcaseSeeder

**Files:**
- Create: `database/seeders/ShowcaseSeeder.php`
- Test: `tests/Feature/Domain/ShowcaseSeederTest.php`

**Interfaces:**
- **Consumes:**
  - `DatabaseSeeder`
  - `App\Cart\Cart::forUser(User)->add(Product, int)`
  - `App\Actions\PlaceOrder::handle(User, ShippingMethod): Order`
  - `App\Actions\SaveReview::handle(User, Product, int $stars, ?string $body): Review`
  - `App\Wallet\Wallet::credit(...)`
  - `PaymentFactory::forOrder()->stripe()->succeeded()`
- **Produces:** `php artisan migrate:fresh --seed --seeder=ShowcaseSeeder`. Tasks 5 and 7 run this command.

- [ ] **Step 1: Write the tests**

```php
it('builds the showcase state', function () {
    fakePublicDisk();
    $this->seed(ShowcaseSeeder::class);

    $sam = User::query()->where('email', 'demo@cartopia.test')->firstOrFail();
    expect(Cart::forUser($sam)->items())->toHaveCount(3)          // use the Cart API's item list
        ->and($sam->balance->cents())->toBe(4250)
        ->and((int) $sam->walletTransactions()->sum('amount'))->toBe(4250)
        ->and($sam->orders()->count())->toBe(4)
        ->and($sam->orders()->where('payment_method', 'wallet')->exists())->toBeTrue()
        ->and(Order::query()->whereBelongsTo($sam)->get()->filter->isSplit())->toHaveCount(1)
        ->and(Review::query()->whereBelongsTo($sam)->count())->toBe(2);

    $others = User::query()->where('is_admin', false)->whereKeyNot($sam->id)->get();
    expect($others)->toHaveCount(8);
    expect(Order::query()->whereNot('user_id', $sam->id)->count())->toBeGreaterThanOrEqual(12)
        ->and(Review::query()->count())->toBeGreaterThanOrEqual(10);
});

it('keeps every showcase order inside the last seven days', function () {
    $this->travelTo(now()->setTime(23, 59, 30));
    fakePublicDisk();
    $this->seed(ShowcaseSeeder::class);

    expect(Order::query()->where('created_at', '>', now())->count())->toBe(0)
        ->and(Order::query()->where('created_at', '<', now()->subDays(7))->count())->toBe(0)
        ->and(Order::query()->selectRaw('date(created_at) d')->distinct()->count())->toBe(7);
});
```

  If the API names in Step 1 differ, such as `orders()` or the column used to sum the ledger, adjust them to the existing models. Keep the asserted values.
- [ ] **Step 2:** Run it. Expected: FAIL (class not found).
- [ ] **Step 3: Implement `ShowcaseSeeder::run(): void`.**
  - **Base data:** call `DatabaseSeeder` first.
  - **Dates:** freeze the clock with `Carbon::setTestNow()` for each dated step, then restore it in a `finally`.
  - **Order amounts:** every order is placed through `PlaceOrder`, so amounts are real.
  - **Sam's history:** in order, from 6 days ago to 1 day ago:
    1. a $100 top-up
    2. three wallet orders
    3. one split order: a wallet debit plus a succeeded Stripe `Payment`, with `wallet_amount` set to part of the total
  - **Sam's reviews:** 5 stars with a body, and 4 stars without a body, on products he bought.
  - **Sam's balance:** finish with one `TopUp` credit that brings it to exactly 4250 cents. Every ledger row must be a real type: signup bonus, top-up or purchase.
  - **Sam's basket:** finish with 3 different products in the basket.
  - **Other customers:** 8 named customers with realistic names. Each gets a top-up and 1–3 wallet orders, spread so that every one of the last 7 days has an order. Add 10 or more reviews in total, stars mostly 4–5, on products they bought.
  - **Randomness:** use fixed data, not random, so runs are identical apart from dates.
- [ ] **Step 4:** Run the tests. Expected: PASS. Then run all the store gates.
- [ ] **Step 5:** Run the command on the dev database. Log in as Sam and as the admin in the built-in browser. Expected:
  - the basket shows 3 items
  - the wallet shows $42.50
  - the dashboard chart has a bar for every day of the week
- [ ] **Step 6:** Commit "Add a showcase seeder with a fixed demo state" and push.

### Task 5: Showcase scaffold and screenshots

**Files:**
- Create:
  - `showcase/package.json`, `showcase/tsconfig.json`, `showcase/README.md`
  - `showcase/capture/lib/env.ts`, `showcase/capture/lib/browser.ts`, `showcase/capture/lib/settle.ts`
  - `showcase/capture/screenshots.ts`
  - `showcase/capture/lib/env.test.ts`
- Modify:
  - `.gitignore`: add `/showcase/node_modules`, `/showcase/out`, `/showcase/video/public/clips`, `/showcase/video/public/voice`, `/showcase/video/public/music`, `/showcase/voice/.venv`
  - `vite.config.ts`: add `showcase/**` to the `lint` and `fmt` `ignorePatterns`, so the store gates stay independent
  - `docs/screenshots/`: replace its contents

**Interfaces:**
- **Produces, for Task 7:**
  - `assertLocalDatabase(envFile: string): { database: string }`. It throws unless the `.env` has `APP_ENV=local` and `DB_DATABASE` is neither empty nor ending in `_testing`.
  - `resetShowcase(): void`. It runs the guard, then `php artisan migrate:fresh --seed --seeder=ShowcaseSeeder --force` in the repo root, using the `PHP_BIN` environment variable with a default of `php`. It also clears any lingering session state.
  - `launch(opts: { width: number; height: number; scale: number; theme: 'light' | 'dark' }): Promise<{ browser; context; page }>`. It uses Playwright Chromium with `ignoreHTTPSErrors: true`.
  - `login(page, who: 'sam' | 'admin'): Promise<void>`
  - `settle(page): Promise<void>`. It waits for network idle and `document.fonts.ready`, then throws if any `img` has `complete === false` or `naturalWidth === 0`, then waits 300 ms.

- [ ] **Step 1: Set up `showcase/package.json`.**
  - **Dependencies:** pin `playwright` to the version in the root `package-lock.json`. Pin `tsx`, `vitest` and `typescript` to exact versions.
  - **Scripts:** `screenshots` (`tsx capture/screenshots.ts`), `clips`, `voice`, `voice:samples`, `music`, `render`, `test` (`vitest run`).
- [ ] **Step 2: Write the failing guard tests in `env.test.ts`.** They write temporary `.env` files and check:
  - `APP_ENV=production` throws
  - `DB_DATABASE=cartopia_testing` throws
  - `APP_ENV=local` with `DB_DATABASE=cartopia` returns `{ database: 'cartopia' }`

  Run `npm test` in `showcase/`. Expected: FAIL.
- [ ] **Step 3: Implement `env.ts`, `browser.ts` and `settle.ts`.** Run `npm test`. Expected: PASS.
- [ ] **Step 4: Write `screenshots.ts`.**
  - **Reset:** call `resetShowcase()` once.
  - **Light and dark, at 1440 × 900, scale 2:**
    - `home`, `shop`, `product` (the olive oil product), `basket`, `checkout`, `wallet`, `orders`, `receipt` (Sam's split order)
    - `reviews`: the product page scrolled to its reviews section
  - **Light only, at 1440 × 900:** `admin-dashboard`, `admin-orders`, `admin-products`, `admin-payments`, `admin-reviews`.
  - **Phone:** `product-phone` at 390 × 844, scale 2.
  - **Combined shot:** `responsive.png`. Use ImageMagick to place the desktop product shot and the phone shot side by side on the cream `--paper` colour, with the phone overlapping its bottom-right corner.
  - **Before every shot:** call `settle()`.
  - **After every shot:** use ImageMagick to resize to 1440 px wide and compress, keeping the file at 400 KB or less.
  - **Naming:** files are named `<name>-<theme>.png`.
- [ ] **Step 5: Run it.**

```bash
cd showcase && npm install && npx playwright install chromium && npm run screenshots
```

  Expected:
  - 25 files: 9 light and 9 dark store shots, 5 admin shots, the phone shot and `responsive.png`
  - every file at 400 KB or less

  Open each one and check it. Delete any old screenshot the new set doesn't use.
- [ ] **Step 6:** Run the store gates `npm run lint` and `npm run format:check` from the root. Expected: PASS. Then commit "Add the showcase capture scripts and new screenshots" and push.

### Task 6: README, docs pages and repository details

**Files:**
- Create: `docs/setup.md`, `docs/payments-sandbox.md`, `docs/admin-panel.md`, `docs/rewrite-2018-2026.md`
- Modify: `README.md`

- [ ] **Step 1: Move the README sections, mostly as they are.**
  - "Setup" and "Demo logins" go to `setup.md`. Remove `git checkout laravel` and use the new demo login. Also move the sockets and Playwright notes from "Quality checks and tests".
  - "Payments in sandbox mode" goes to `payments-sandbox.md`.
  - "Admin panel" goes to `admin-panel.md`.
  - "2018 → 2026 rewrite" and the money note go to `rewrite-2018-2026.md`.
  - Fix the relative links in each moved page.
- [ ] **Step 2: Get the real test counts.** Run both Pest suites and add up the passed counts.
- [ ] **Step 3: Rewrite `README.md`** in the order of spec §1:
  - **Pitch:** the spec's one-line pitch.
  - **Video:** an HTML comment `<!-- walkthrough video: added after upload -->` holds the video's place.
  - **Screenshot grid:** a 2×2 table of `shop-light`, `checkout-light`, `admin-dashboard-light` and `responsive`.
  - **More screenshots:** a `<details><summary>More screenshots</summary>` block with a light/dark table.
  - **Work with me:** verbatim from Global Constraints.
  - **What it does:** 6 bullets.
  - **What I built:** 6 bullets, using the real count from Step 2, rounded down to the nearest ten with a "+", for example "270+ automated tests".
  - **Stack and quality:** with the CI badge.
  - **Run it locally:** two lines and a link to `docs/setup.md`.

  **Copy rules:** plain language, active voice, no ALL-CAPS labels, no "→" appended to links.
- [ ] **Step 4: Check the links.**

```bash
grep -oE '\]\(([^)#]+)' README.md docs/*.md | sed 's/.*](//' | grep -v '^http' | sort -u
```

  Every path it prints must exist. Then run `npm run format:check`. Expected: PASS.
- [ ] **Step 5:** Commit "Rewrite the README as a showcase and move the guides to docs" and push. Open the branch's README on GitHub. Expected:
  - the images render
  - the `<details>` section opens
  - the docs links resolve
- [ ] **Step 6: Set the repository details.**

```bash
gh repo edit amrcs92/cartopia --description "Online store with wallet, Stripe/PayPal checkout and a Filament admin panel. Laravel 13, Vue 3, Inertia." --add-topic laravel,vue,inertiajs,typescript,tailwindcss,filament,stripe,paypal,pest,ecommerce
```

  Check with `gh repo view --json description,repositoryTopics`. The homepage is set in Task 13.

### Task 7: Scene clips

**Files:**
- Create:
  - `showcase/capture/lib/recorder.ts`
  - `showcase/capture/lib/pointer.ts`
  - `showcase/capture/scenes/{browse,checkout,ledger,admin,responsive}.ts`
  - `showcase/capture/clips.ts`
  - `showcase/capture/lib/frames.test.ts`

**Interfaces:**
- **Consumes:** Task 5's `resetShowcase`, `launch`, `login` and `settle`.
- **Produces:**
  - `showcase/video/public/clips/<scene>.mp4`: H.264, CRF 16, 30 fps, at the recorded size of 2880 × 1620
  - `showcase/video/public/clips/manifest.json`, with this shape:

```ts
type Manifest = Record<SceneId, { file: string; durationMs: number; width: 2880; height: 1620;
  marks: { name: string; atMs: number; box: { x: number; y: number; w: number; h: number } }[] }>;
type SceneId = 'browse' | 'checkout' | 'ledger' | 'admin' | 'responsive-desktop' | 'responsive-phone' | 'dark';
```

  Mark boxes are in recorded pixels.

- [ ] **Step 1: Write the failing test for `toConstantRate()`.**
  - **Signature:** `toConstantRate(frames: {tMs: number; file: string}[], fps: number, endMs: number): {file: string; durationS: number}[]`
  - **Output:** an ffmpeg concat list where each frame lasts until the next frame or `endMs`.
  - **Test cases:**
    - frames at 0, 100 and 1000 ms with `endMs` 1500 give durations 0.1, 0.9 and 0.5
    - an empty list throws
- [ ] **Step 2: Implement `recorder.ts`.**
  - **Screencast:** `start(page)` opens a CDP session and calls `Page.startScreencast` with `format: 'jpeg'`, `quality: 92` and max size 2880 × 1620. The viewport is 1920 × 1080 at `deviceScaleFactor` 1.5.
  - **Frames:** each frame and its timestamp is written to a temporary directory, and each frame is acknowledged.
  - **Stop:** `stop(): Promise<{ durationMs }>` encodes the frames with `npx remotion ffmpeg -f concat ... -vf fps=30 -c:v libx264 -crf 16 -pix_fmt yuv420p`.
  - **Marks:** `mark(name, locator)` records the elapsed time and the bounding box scaled by 1.5.
  - Run the vitest suite. Expected: PASS.
- [ ] **Step 3: Implement `pointer.ts`.**
  - **Pointer:** `install(page)` injects a fixed-position SVG arrow, 28 px, ink outline on a cream fill, with a soft shadow. It is re-injected on each Inertia navigation.
  - **Clicking:** `clickWith(page, locator)` moves the pointer to the locator's centre over 600 ms with the `--ease-stall` curve, waits 150 ms, shows a press ripple, then clicks.
  - **Typing:** `typeWith(page, locator, text)` types at 60 ms per character.
- [ ] **Step 4: Write the scenes.** Each scene calls `settle()` before its first action, and pauses about 700 ms after each visible change so the edit has room.

| Scene | Actions | Marks |
|---|---|---|
| `browse` | Home, pause on the carousel, then Shop. Click the Bakery aisle chip, type "honey" in live search and open the result. Hover the gallery zoom. | `aisle-chip`, `search`, `gallery` |
| `checkout` | Log in as Sam beforehand, off camera. Open the product, add it to the basket and let the panel appear, then open the basket and check out. Choose "Use wallet balance" and pay the rest with Stripe. On Stripe's page enter `4242 4242 4242 4242`, `12/34`, `123` and the name `Sam Rivera`, then pay. Wait for the receipt. | `add-to-basket`, `pay-with`, `stripe-pay`, `paid-badge` |
| `ledger` | Wallet page, top up $25 from the wallet quick amount, then the ledger rows. Open the order history, open a delivered product and leave a 5-star review. | `top-up`, `ledger`, `review` |
| `admin` | Log in as the admin beforehand, off camera. Dashboard, then edit a product's price and save. On Reviews, hide a review. On Orders, open one of Sam's wallet orders and refund it. | `chart`, `product-save`, `hide-review`, `refund` |
| `responsive-desktop` | The shop at desktop size. | none |
| `responsive-phone` | Viewport 390 × 844 at scale 3 (recorded at 1170 × 2532): scroll the home page and open a product. | none |
| `dark` | The shop in light mode. Click the header theme toggle to switch to dark, then hold. | `toggle` |

  **Picking orders and products:**
  - In `checkout`, choose the "Pay with" option that puts the wallet balance towards the order, using the label the page actually shows.
  - In `ledger`, review a product Sam bought but hasn't reviewed yet.
  - In `admin`, refund a wallet order of Sam's that contains no reviewed product, because a refund deletes the reviews on that order's products.

  **Assertion in `checkout`:** after paying, wait at most 20 s for the receipt to show `Paid`. If it doesn't, throw `"Stripe did not settle: is 'stripe listen' running and the queue worker started?"`.
- [ ] **Step 5: Write `clips.ts`.** It calls `resetShowcase()` once, runs every scene in order, and writes `manifest.json`.
- [ ] **Step 6: Run it.** Prerequisites:
  - Stripe sandbox keys in `.env`
  - `stripe listen --forward-to https://cartopia.test/webhooks/stripe --skip-verify` running
  - `php artisan queue:work` running

  Run `npm run clips`. Expected:
  - 7 mp4 files and a manifest whose marks are all non-empty
  - each clip 8–40 s long
  - no loading spinners and no unloaded images

  Make a contact sheet of each clip (`npx remotion ffmpeg -i clip.mp4 -vf "fps=1/2,scale=480:-1,tile=6x4" sheet.png`) and look at it.
- [ ] **Step 7:** Commit "Record the walkthrough scenes" and push. The code only: clips are git-ignored.

### Task 8: Narration

**Files:**
- Create:
  - `showcase/voice/requirements.txt`, with exact pins
  - `showcase/voice/script.json`
  - `showcase/voice/narrate.py`
  - `showcase/voice/config.json`

**Interfaces:**
- **Produces:**
  - `showcase/video/public/voice/narration.wav`: 24 kHz mono.
  - `showcase/video/public/voice/timings.json`, with this shape:

```ts
type Timings = { durationMs: number; voice: string;
  segments: { id: SegmentId; text: string; startMs: number; endMs: number;
    words: { text: string; startMs: number; endMs: number }[] }[] };
type SegmentId = 'intro' | 'browse' | 'pay' | 'ledger' | 'admin' | 'screens' | 'close';
```

  **`script.json`:** `{ segments: { id, text, pauseAfterMs }[] }`. The texts are the seven narration lines from the spec's storyboard. `pauseAfterMs` is 900 at scene boundaries and 500 inside a scene.

- [ ] **Step 1: CHECKPOINT.** Tell Amr that installing Kokoro downloads about 1–2 GB: PyTorch on CPU, plus the model weights from Hugging Face. Ask for his OK. Then:

```bash
py -3.12 -m venv showcase/voice/.venv && showcase/voice/.venv/Scripts/pip install -r showcase/voice/requirements.txt
```

  `requirements.txt` pins `kokoro`, `soundfile`, `numpy` and `misaki[en]`.
- [ ] **Step 2: Write `narrate.py`.** It takes `--samples` or `--final`.
  - **`--samples`:** renders the `intro` and `browse` lines with each of `am_michael`, `am_onyx`, `am_fenrir` and `bm_george` into `showcase/out/voice-samples/<voice>.wav`.
  - **`--final`:**
    - reads `config.json` (`{ "voice": "...", "speed": 1.0 }`)
    - renders each segment with `KPipeline(lang_code='a' or 'b' by voice prefix)`
    - takes word times from each result's tokens (`start_ts` and `end_ts`)
    - joins the segments with their pauses, adding 400 ms of lead-in silence
    - writes the WAV and `timings.json`
  - **Length check:** `--final` exits non-zero with the measured length if `durationMs` falls outside 80,000–88,000. That leaves 2–10 s for the title, music and closing card.

  Wire up `npm run voice:samples` and `npm run voice`.
- [ ] **Step 3: CHECKPOINT.** Run `npm run voice:samples` and send Amr the four sample files with SendUserFile. He picks one, then record it in `config.json`.
- [ ] **Step 4: CHECKPOINT.** Show Amr the seven lines from `script.json` and ask him to approve or edit the wording. Apply his edits.
- [ ] **Step 5:** Run `npm run voice`. If it's too long, lower the speed in steps of 0.05, down to 0.9 at the lowest. If it's too short, raise it, up to 1.1 at the most. Send Amr `narration.wav` with SendUserFile.
- [ ] **Step 6:** Commit "Add the narration script and generator" and push.

### Task 9: Music

**Files:**
- Create: `showcase/video/music/compose.py`

**Interfaces:**
- **Produces:** `showcase/video/public/music/music.wav`, 48 kHz stereo and exactly 92 s long.
- **Runs in:** the Task 8 virtual environment, with numpy and soundfile.

- [ ] **Step 1: Write `compose.py`.** It generates a deterministic, seeded track:
  - **Tempo and key:** 90 BPM in D major, with the progression I–vi–IV–V every 2 bars.
  - **Pad:** detuned saw or sine voices through a low-pass filter around 1.2 kHz, with slow attack.
  - **Pluck:** a soft Karplus–Strong pluck plays an arpeggio on beats 1 and 3.
  - **Kick:** a light sine kick on the beat. It enters at bar 3 and drops out for the last 4 bars.
  - **Ending:** a 3 s fade-out.
  - **Level:** peaks normalised to −3 dBFS.

  `npm run music` runs it.
- [ ] **Step 2: Run it.** Expected: the file exists and is 92.0 s ± 0.05, checked with `soundfile.info`. Listen to it, or send it to Amr together with the draft in Task 10.
- [ ] **Step 3:** Commit "Compose the walkthrough music" and push.

### Task 10: Remotion composition and draft cut

**Files:**
- Create:
  - `showcase/video/remotion.config.ts`, `showcase/video/src/index.ts`, `showcase/video/src/Root.tsx`
  - `showcase/video/src/timeline.ts`
  - `showcase/video/src/timeline.test.ts`
  - `showcase/video/src/theme.ts`
  - `showcase/video/src/components/`: `TitleCard.tsx`, `SceneTitle.tsx`, `Subtitles.tsx`, `CameraClip.tsx`, `PhoneFrame.tsx`, `DarkWipe.tsx`, `StackCards.tsx`, `ClosingCard.tsx`, `Logo.tsx`
  - `showcase/video/src/Walkthrough.tsx`
  - `showcase/video/src/Thumbnail.tsx`

**Interfaces:**
- **Consumes:**
  - Task 7's `manifest.json`
  - Task 8's `timings.json` and `narration.wav`
  - Task 9's `music.wav`
- **Produces:** the compositions `Walkthrough` (1920 × 1080, 30 fps, `durationInFrames` from the timeline) and `Thumbnail` (1280 × 720 still).

- [ ] **Step 1: Write the failing tests for `buildTimeline(timings: Timings, manifest: Manifest, fps = 30): Timeline`.**

```ts
type Timeline = { totalFrames: number;
  scenes: { id: SegmentId; from: number; frames: number }[];
  captions: { from: number; frames: number; words: { text: string; from: number; to: number }[] }[];
  musicDuck: { from: number; to: number }[] };
```

  The tests use fixture timings and a fixture manifest, and check that:
  - `totalFrames` is between 2655 and 2745
  - scenes are contiguous, with no gaps or overlaps, start at 0 and sum to `totalFrames`
  - the intro takes at least 120 frames and the close takes at least 240 frames, so the closing card holds for at least 3 s after the last word
  - every caption word lies inside its caption, and every caption lies inside its segment's scene
  - each caption chunk is at most 42 characters per line and at most 2 lines
  - `musicDuck` covers every segment, from its start minus 6 frames to its end plus 9 frames

  Run `npm test`. Expected: FAIL.
- [ ] **Step 2: Implement `timeline.ts`.**
  - **Scenes:** each scene's start is its segment's `startMs` minus 300 ms. The `close` scene runs to the end.
  - **Captions:** words are grouped into chunks that break at punctuation or at the 42-character limit.
  - Run `npm test`. Expected: PASS.
- [ ] **Step 3: Write `theme.ts`.**
  - **Colours:** copy the hex values of `--paper`, `--ink`, `--green`, `--green-deep`, `--marigold` and `--cream` from the light `:root` in `resources/css/app.css`.
  - **Fonts:** load `@fontsource/young-serif` and `@fontsource/figtree`, pinned to the store's versions.
  - **Logo:** `Logo.tsx` ports the SVG of the store's `AppLogo` mark. Find it with `grep -rl "AppLogo" resources/js`. The awning stripes animate in left to right over 18 frames.
- [ ] **Step 4: Build the components.**

| Component | Behaviour |
|---|---|
| `CameraClip` | Plays a clip with `OffthreadVideo`. Its `focus` prop is a list of `{ mark, from, hold }`, in frames. It eases (spring, damping 200) from the full frame to a 1.35–1.6× crop centred on the mark's box over 18 frames, holds, then eases back out. It never shows a static full frame for more than 90 frames. Clip `playbackRate` is between 0.85 and 1.6, chosen so the clip fills its scene. |
| `SceneTitle` | Top-left, in Young Serif at 64 px, cream on a green pill with a marigold underline. It slides in over 12 frames and stays through the scene. |
| `Subtitles` | Bottom-centre, Figtree 600 at 44 px, cream text on `rgba(ink, 0.78)` with a 14 px radius. The spoken word is highlighted marigold. It shows one chunk at a time, with a 6-frame fade. |
| `TitleCard` | The logo animates in, then "Cartopia", then the pitch line, over a paper background with a subtle stall-stripe band. |
| `PhoneFrame` | A rounded device frame at 44 px radius with a 12 px ink bezel, holding the phone clip, beside the desktop clip at 70% size. |
| `DarkWipe` | A diagonal mask reveals the `dark` clip's dark half, synced to the `toggle` mark. |
| `StackCards` | Six cards: Laravel 13, Vue 3, Filament, Stripe, PayPal, and the test count from Task 6 Step 2. They pop in, staggered by 4 frames, using the store's hard-shadow card style (`5px 5px 0` green). |
| `ClosingCard` | The verbatim closing copy, with the logo above it. It holds until the end. |

  **`Walkthrough`:** maps the scenes as follows:

| Scene | Content |
|---|---|
| intro | `TitleCard` |
| browse | `CameraClip(browse)` |
| pay | `CameraClip(checkout)` |
| ledger | `CameraClip(ledger)` |
| admin | `CameraClip(admin)` |
| screens | `PhoneFrame`, then `DarkWipe` |
| close | `StackCards`, then `ClosingCard` |

  It overlays `SceneTitle` (except on the intro and close scenes) and `Subtitles`.

  **Audio:** narration at volume 1. Music runs at 0.55 between lines and drops to 0.16 while the narrator speaks, with 6-frame interpolated ramps.

  **Cuts:** scenes cross-fade over 8 frames, except intro → browse, which is a 12-frame slide.
- [ ] **Step 5: Build the `Thumbnail` composition.** The `checkout` clip frame at the `paid-badge` mark, blurred and darkened 40%, with the logo, "Cartopia" in Young Serif at 120 px, and the line "A full online store, built in Laravel & Vue" on a marigold band.
- [ ] **Step 6: Render the draft.**

```bash
npx remotion render Walkthrough ../out/draft.mp4 --crf 20
```

  Make a contact sheet with a frame every 2 s: `fps=1/2,scale=480:-1,tile=5x9`. Check it for:
  - subtitles in sync with the voice
  - text that doesn't fit its frame
  - blank or loading frames
  - zooms that land on their targets

  Fix what you find and render again.
- [ ] **Step 7: CHECKPOINT.** Send Amr `draft.mp4` and the contact sheet with SendUserFile, and ask for his notes. Apply them, re-render, and ask again until he approves.
- [ ] **Step 8:** Commit "Compose the walkthrough video" and push.

### Task 11: Final renders and delivery files

**Files:**
- Create:
  - `showcase/video/scripts/render.ts`
  - `showcase/video/scripts/srt.ts`
  - `showcase/video/scripts/srt.test.ts`
  - `showcase/video/scripts/youtube.ts`

**Interfaces:**
- **Consumes:** `buildTimeline` and `timings.json`.
- **Produces:** the five deliverables listed in Global Constraints.

- [ ] **Step 1: Write the failing `toSrt(timeline, fps): string` test.** For fixture captions, check that:
  - cue numbers start at 1
  - times use the `HH:MM:SS,mmm` format
  - cues follow the caption chunks
  - no cue is shorter than 700 ms; a short cue is extended into the gap after it but never overlaps the next cue

  Then implement `toSrt`. Expected: PASS.
- [ ] **Step 2: Write `render.ts`.** It runs these steps in order:
  1. Render `Walkthrough` at 1080p with `--crf 18 --audio-bitrate 192k` to a temporary file.
  2. Normalise loudness in two passes with `npx remotion ffmpeg ... -af loudnorm=I=-14:TP=-1.5:LRA=11`, measuring first and then applying linear normalisation. Write `cartopia-walkthrough-1080p.mp4`.
  3. Encode the 720p copy in two passes with x264 at `-b:v 780k`, AAC at 96k, `-movflags +faststart`.
  4. Render the still: `npx remotion still Thumbnail ../out/cartopia-thumbnail.png`.
  5. Write `cartopia-walkthrough.srt` with `toSrt`.
  6. Write `youtube.md` with `youtube.ts`.

  **Size check:** the script exits non-zero if `cartopia-walkthrough-720p.mp4` is larger than 10,485,760 bytes.

  **Copy:** once every check passes, copy the five deliverables to `process.env.SHOWCASE_DELIVER_DIR ?? path.join(os.homedir(), 'Downloads')`, overwriting earlier copies, and print each destination path.

  `npm run render` runs it.
- [ ] **Step 3: Write `youtube.ts`.** It produces `youtube.md` with:
  - **Title:** "Cartopia: an online store built with Laravel, Vue and Filament (90-second tour)"
  - **Description:**
    - two sentences
    - chapters, from each scene's start, in `0:00` format
    - the GitHub link
    - the "Work with me" text with the email address
  - **Tags:** the repository topics, plus "portfolio" and "web developer".
- [ ] **Step 4: Run `npm run render`, then check the result.**

```bash
npx remotion ffprobe -v error -show_entries format=duration:stream=width,height,r_frame_rate ../out/cartopia-walkthrough-1080p.mp4
npx remotion ffmpeg -i ../out/cartopia-walkthrough-1080p.mp4 -af ebur128 -f null - 2>&1 | grep -A2 Summary
```

  Expected:
  - 1920 × 1080 at 30 fps, length 88.5–91.5 s
  - integrated loudness −14 ± 1 LUFS
  - the 720p file at most 10 MB
  - the thumbnail at 1280 × 720
  - all five files present in `C:\Users\engam\Downloads`
- [ ] **Step 5:** Commit "Add the final render and delivery scripts" and push. Tell Amr the five files are in `C:\Users\engam\Downloads`, and tell him:
  1. Upload the 1080p video to YouTube, with the thumbnail, the `.srt` and `youtube.md`.
  2. Drag the 720p video into the README in GitHub's web editor.
  3. Send back both links.

### Task 12: Merge the showcase branch

- [ ] **Step 1:** Run every store gate and both Pest suites, and run `npm test` in `showcase/`. Expected: all green.
- [ ] **Step 2: Open the PR.**

```bash
gh pr create --base main --head showcase/portfolio-presentation --title "Portfolio showcase: README, screenshots, catalogue and walkthrough tooling"
```

  The PR body summarises the four parts and the test counts, with no attribution.
- [ ] **Step 3:** Once CI passes, merge with a merge commit. Confirm with `git merge-base --is-ancestor origin/showcase/portfolio-presentation origin/main`, then delete the branch locally and on GitHub.

### Task 13: Video links (after Amr's uploads)

- [ ] **Step 1:** From `main`, create the branch `showcase/video-links`. Replace the README's `<!-- walkthrough video ... -->` placeholder with:
  - the GitHub `user-attachments` video URL Amr sends, on its own line so GitHub plays it in the page
  - a line underneath: `[Watch on YouTube](<url>)`

  Commit and push.
- [ ] **Step 2:** Run `gh repo edit amrcs92/cartopia --homepage <youtube-url>`, then check with `gh repo view --json homepageUrl`.
- [ ] **Step 3:** Open a PR. Once CI passes, merge it with a merge commit, then delete the branch locally and on GitHub. Open the README on GitHub. Expected: the video plays in the page.
