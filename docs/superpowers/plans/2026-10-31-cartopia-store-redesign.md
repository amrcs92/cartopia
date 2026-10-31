# Cartopia Store Redesign Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Give the Cartopia store its "market stall" identity in light and dark mode on every page and screen size, and add the aisles, featured products and filters the new design needs.

**Architecture:** Backend first: categories and a featured flag, then the props each page needs (home, shop, product, added-to-basket flash, low-balance numbers, 404 page). Then the front end, bottom-up: design tokens and fonts, shared building blocks, the store layout, then each page rebuilt against its canvas mockup. Colours live only as CSS custom properties with a light and a `.dark` value, so every component gets both themes for free.

**Tech Stack:** Laravel 13, Inertia 3, Vue 3 + TypeScript, Tailwind 4, shadcn-vue, Wayfinder, Pest 4 + pest-plugin-browser, `@fontsource` fonts.

**Spec:** `docs/superpowers/specs/2026-10-31-cartopia-store-redesign-design.md`
**Visual reference:** canvas https://claude.ai/artifact/GMbSbdvDMisyhnSpqQUmkq. The "Final store" page has each mockup named as the spec names it (Home, Shop, Product…), with its dark twin directly below. The "Brand" page has the design system. Match the mockups visually; never copy their HTML.

## Global Constraints

- Work in `C:\laragon\www\cartopia` on branch `store-redesign`. Commit once per task. Never push.
- Commit messages: a subject line and a body only. **No `Co-Authored-By` line or any AI attribution.**
- Every shell starts with `export PATH=/c/laragon/bin/php/php-8.3.36-Win32-vs16-x64:$PATH` (Wayfinder runs bare `php` during builds).
- Run tests with `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest` (add a path or `--filter` to narrow).
- Gates before every commit: `vendor/bin/pint --test`, `composer analyse` (Larastan level 7), `npm run lint`, `npm run format:check`, `npm run types`, `npm run build`.
- Front-end layout follows the existing convention: `resources/js/common` (shared building blocks), `resources/js/modules/<feature>` (feature code), `resources/js/pages` (Inertia pages), `resources/js/lib/shadcn` (third-party; don't edit, wrap in `common`).
- Components use only the design tokens from Task 5 (Tailwind classes such as `bg-paper text-ink border-outline`). **No raw hex values in `.vue` files.**
- Visible copy uses the spec's words: **basket** (not cart), **top up**, **aisle**, **log in**. Routes, route names and URLs stay unchanged (`/cart`, `cart.show`…).
- Money values in props stay formatted strings (`'$4.34'`), as today. Integer `*_cents` fields are added only where the client must compare amounts (Task 4).
- Minimum touch target 44 × 44px. Visible focus everywhere: a 3px marigold outline. Respect `prefers-reduced-motion`.
- Breakpoints: phone < 640px, tablet 640–979px, desktop ≥ 980px. Tailwind's `lg` is overridden to `980px` (Task 5). No page may scroll sideways at 360px wide.
- Checkout stays wallet-only and top-ups stay free credit in this sub-project (payments are sub-project 3).

## Review Focus

1. **Fewer featured products than fit in view (0, 1 or 2):** with 0 the section isn't rendered. With fewer than the visible count, arrows and dots are hidden and the cards aren't stretched to fill the row. Pinned in Task 8.
2. **Bad aisle filter input** (unknown slug, the same slug twice, more than 20, a non-array `aisle=fruit`): the request is rejected with a validation error, never a 500; duplicates are harmless. Pinned in Task 2.
3. **Aisles and products with no photos:** `image_url` is `null` and the tile shows a placeholder, never a broken image. Pinned in Tasks 1 and 8.
4. **Every featured product is already in the basket:** "Goes well with" gets an empty list and the panel hides that block. Pinned in Task 3.
5. **Dark mode on first load:** a request with the `appearance=dark` cookie returns HTML whose `<html>` element already has the `dark` class (no light flash). Pinned in Task 5.

---

### Task 1: Aisles and featured products in the data layer

**Files:**
- Create: `database/migrations/2026_11_01_000001_create_categories_table.php`, `database/migrations/2026_11_01_000002_add_category_and_featured_to_products_table.php`, `app/Models/Category.php`, `database/factories/CategoryFactory.php`, `tests/Feature/Domain/CategoryTest.php`
- Modify: `app/Models/Product.php`, `database/factories/ProductFactory.php`, `database/seeders/DatabaseSeeder.php`, `tests/Feature/Domain/ProductScopesTest.php`, `tests/Feature/Domain/DatabaseSeederTest.php`, `tests/Feature/Domain/SchemaTest.php`

**Interfaces:**
- Produces:
  - `categories` table: `id`, `name` (unique), `slug` (unique), `image_path` (nullable string), timestamps.
  - `products.category_id`: nullable foreign key, `nullOnDelete`, indexed. `products.is_featured`: boolean, default `false`, indexed.
  - `Category` model: `products(): HasMany`, `imageUrl(): ?string` (a method, not a stored column).
  - `Product` model: `category(): BelongsTo`, `is_featured` cast to `bool`, and new scopes `featured()`, `inAisles(?array $slugs)` and `rated()`.
  - `CategoryFactory`. `ProductFactory` gains the states `featured()` and `inCategory(Category $category)`.

- [ ] **Step 1: Write the failing tests** in `tests/Feature/Domain/CategoryTest.php`:

```php
it('keeps products when their aisle is deleted', function () {
    $aisle = Category::factory()->create();
    $product = Product::factory()->inCategory($aisle)->create();

    $aisle->delete();

    expect($product->fresh()->category_id)->toBeNull();
});

it('uses the aisle image, then the newest product photo, then nothing', function () {
    Storage::fake('public');
    $aisle = Category::factory()->create(['image_path' => null]);
    expect($aisle->imageUrl())->toBeNull();

    Product::factory()->inCategory($aisle)->withImages()->create();
    $newest = Product::factory()->inCategory($aisle)->withImages()->create();
    expect($aisle->fresh()->imageUrl())
        ->toBe(Storage::disk('public')->url($newest->primaryImage->path));

    $aisle->update(['image_path' => 'categories/drinks.jpg']);
    expect($aisle->fresh()->imageUrl())->toBe(Storage::disk('public')->url('categories/drinks.jpg'));
});
```

Add to `ProductScopesTest.php`:
- `featured()` returns only featured products, newest (highest id) first.
- `inAisles(['drinks', 'dairy'])` returns products in either aisle; `inAisles(null)` and `inAisles([])` leave the query unfiltered.
- `rated()` returns only products with at least one rating.

Update `DatabaseSeederTest.php`. The seeder must create exactly these aisles: Fruit and veg (`fruit-and-veg`) with Apple, Drinks (`drinks`) with Water and Beer, and Dairy (`dairy`) with Cheese Triangle. All four products must be featured. Update `SchemaTest.php` for the new table and columns.

- [ ] **Step 2: Run them and see them fail**

Run: `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest tests/Feature/Domain`
Expected: FAIL (`Category` class not found).

- [ ] **Step 3: Implement**
- **Migrations and models:** as in the Interfaces block. `imageUrl()` uses `image_path` when set; otherwise the `primaryImage` of the category's newest product (highest id) that has one; otherwise `null`. URLs come from `Storage::disk('public')->url()`, as `ProductResource` already does.
- **Scopes:** follow the existing `#[Scope] protected function` style in `Product`. For `inAisles`, use `whereHas('category', fn ($q) => $q->whereIn('slug', $slugs))`. For `rated`, use `whereHas('ratings')`.
- **Seeder:** create the three aisles before the products, and set `category_id` and `is_featured => true` on each product in `PRODUCTS`.

- [ ] **Step 4: Run the domain tests and see them pass**

Run: `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest tests/Feature/Domain`
Expected: PASS.

- [ ] **Step 5: Run all the gates, then commit**

```bash
git add app/Models database tests/Feature/Domain
git commit -m "Add aisles and featured products to the catalogue" -m "Categories with a photo fallback, a featured flag on products, and scopes for aisle, rated and featured filtering. The seeder files the four products into three aisles and features them all."
```

### Task 2: Catalogue props for home, shop and product pages

**Files:**
- Create: `app/Catalog/FeaturedProducts.php`, `app/Catalog/Aisles.php`, `app/Http/Resources/AisleResource.php`
- Modify: `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/ProductController.php`, `app/Http/Requests/ProductIndexRequest.php`, `app/Http/Resources/ProductResource.php`, `app/Http/Resources/ProductDetailResource.php`, `tests/Feature/Http/CatalogTest.php`

**Interfaces:**
- Consumes (Task 1): `Category::imageUrl()`, and the `featured`, `inAisles` and `rated` scopes.
- Produces:
  - `FeaturedProducts::take(int $limit = 8, ?int $exceptId = null): Collection<int, Product>`. Results are featured, newest first, and loaded with `primaryImage`, `category` and `withRatingStats()`.
  - `Aisles::forHome(): Collection<int, Category>`. At most 6 categories that have products, ordered by product count (descending) and then name, with `products_count`.
  - `Aisles::all(): Collection<int, Category>`. Every category that has products, ordered by name, with `products_count`.
  - `AisleResource` returns `{id, name, slug, product_count, image_url: ?string}`.
  - `ProductResource` gains `aisle: ?string` (the category name, only when `category` is loaded).
  - Home page (`WelcomePage`) props:
    - `counter`: the 4 newest products (renamed from the old `featured`).
    - `featured`: `FeaturedProducts::take()`.
    - `aisles`: `Aisles::forHome()`.
  - Shop page (`products/ProductsIndexPage`) props:
    - `products` and `filters`, as today, with `filters` also carrying `aisle` (array), `rated` and `featured`.
    - `aisles`: `Aisles::all()`.
    - `featured`.
  - Product page (`products/ProductShowPage`) props:
    - `product` gains `category: {name, slug}|null`.
    - `featured`: `FeaturedProducts::take(8, $product->id)`.
  - New query parameters on the shop page: `aisle[]`, `rated=1` and `featured=1`.

- [ ] **Step 1: Write the failing tests** in `CatalogTest.php`. Rename `featured` to `counter` in the existing welcome-page test, then add:

```php
it('shows at most six aisles with products, most stocked first', function () {
    $big = Category::factory()->create(['name' => 'Big']);
    Product::factory()->count(3)->inCategory($big)->create();
    Category::factory()->count(6)->create()->each(fn ($c) => Product::factory()->inCategory($c)->create());
    Category::factory()->create(['name' => 'Empty']);

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('aisles', 6)
        ->where('aisles.0.name', 'Big')
        ->where('aisles.0.product_count', 3)
    );
});

it('shows up to eight featured products, newest first, and nothing when none are featured', function () {
    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page->has('featured', 0));

    $products = Product::factory()->count(9)->featured()->create();
    Product::factory()->create();

    $this->get(route('home'))->assertInertia(fn (Assert $page) => $page
        ->has('featured', 8)
        ->where('featured.0.id', $products[8]->id)
    );
});

it('filters the shop by one or more aisles', function () { /* products in drinks, dairy and none; ?aisle[]=drinks returns 1; ?aisle[]=drinks&aisle[]=dairy returns 2 */ });
it('filters the shop to rated products and to featured products', function () { /* ?rated=1, ?featured=1 */ });
it('combines aisle with search and price filters', function () { /* ?aisle[]=drinks&q=wat&max_price=1.50 */ });
it('rejects bad aisle input without a server error', function (array|string $aisle) {
    $this->get(route('products.index', ['aisle' => $aisle]))->assertSessionHasErrors('aisle');
})->with([
    'unknown slug' => [['nope']],
    'not an array' => ['drinks'],
    'too many' => [array_fill(0, 21, 'drinks')],
]);
it('treats a repeated aisle as one', function () { /* ?aisle[]=drinks&aisle[]=drinks → same result as once, status 200 */ });
it('shows the breadcrumb aisle on the product page, or none', function () { /* product.category.slug / product.category null */ });
it('leaves the current product out of its featured carousel', function () { /* featured excludes product.id */ });
```

Fill in the commented bodies using the same `assertInertia` style.

- [ ] **Step 2: Run the catalogue tests and see them fail**

Run: `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest tests/Feature/Http/CatalogTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**

Add these rules to `ProductIndexRequest`:
- `aisle => ['nullable', 'array', 'max:20']`
- `aisle.* => ['string', 'exists:categories,slug']`
- `rated => ['nullable', 'boolean']`
- `featured => ['nullable', 'boolean']`

Don't use a `distinct` rule; repeats are allowed. `filters()` removes repeated aisles with `array_values(array_unique(...))` (or returns `null`), and returns `rated` and `featured` as booleans. `rawFilters()` echoes all three.

The controller applies `->inAisles()`, plus `->rated()` and `->featured()` only when their filter is true.

Move the home page's 4-newest query into a `counter` prop.

- [ ] **Step 4: Run the whole suite and see it pass**

Run: `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest`
Expected: PASS. Fix anything else that read the old `featured` prop.

- [ ] **Step 5: Run the gates, then commit**: "Serve aisles, featured products and the new shop filters".

### Task 3: "Added to basket" payload

**Files:**
- Modify: `app/Http/Controllers/CartItemController.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `tests/Feature/Http/CartTest.php`, `tests/Feature/Http/SharedPropsTest.php`

**Interfaces:**
- Consumes: `FeaturedProducts::take()` (Task 2), `Cart::summary()`.
- Produces: the shared prop `flash.addedToBasket`, which is `null` or:
  - `product`: `{id, name, image_url}`
  - `quantity`: the quantity just added
  - `line_total`: that quantity multiplied by the unit price, formatted
  - `basket`: `{count, subtotal}`
  - `suggestions`: up to 2 `ProductResource` items
- `store()` no longer flashes `success`.

- [ ] **Step 1: Write the failing tests** in `CartTest.php`:

```php
it('flashes what was just added, with the basket summary', function () {
    $apple = Product::factory()->create(['price' => 30]);

    $this->post(route('cart.items.store'), ['product_id' => $apple->id, 'quantity' => 2]);

    $this->get(route('products.index'))->assertInertia(fn (Assert $page) => $page
        ->where('flash.addedToBasket.product.id', $apple->id)
        ->where('flash.addedToBasket.quantity', 2)
        ->where('flash.addedToBasket.line_total', '$0.60')
        ->where('flash.addedToBasket.basket.count', 2)
        ->where('flash.addedToBasket.basket.subtotal', '$0.60')
    );
});

it('suggests at most two featured products that are not already in the basket', function () { /* 3 featured, one added → 2 suggestions, neither the added one */ });
it('suggests nothing when every featured product is already in the basket', function () { /* suggestions has 0 */ });
it('does not repeat the panel on the next page', function () { /* second GET → flash.addedToBasket null */ });
```

Update any existing assertion on `'Added to cart.'`.

- [ ] **Step 2: Run them and see them fail**

Run: `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest tests/Feature/Http/CartTest.php`
Expected: FAIL.

- [ ] **Step 3: Implement**
- `store()` flashes `addedToBasket` as a plain array: resolve the resources with `->resolve()` before flashing.
- For suggestions, take featured products and drop any whose id is already in the basket's lines.
- `HandleInertiaRequests` adds `'addedToBasket' => $request->session()->get('addedToBasket')` inside the existing `flash` closure.

- [ ] **Step 4: Run the suite and see it pass**

Run: `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest`
Expected: PASS.

- [ ] **Step 5: Run the gates, then commit**: "Flash the added-to-basket details and suggestions".

### Task 4: Low-balance numbers and the not-found page

**Files:**
- Modify: `app/Http/Resources/CartResource.php`, `app/Http/Resources/ShippingMethodResource.php`, `app/Http/Middleware/HandleInertiaRequests.php`, `bootstrap/app.php`, `tests/Feature/Http/CheckoutTest.php`, `tests/Feature/Http/SharedPropsTest.php`
- Create: `resources/js/pages/errors/NotFoundPage.vue` (a temporary minimal page; Task 12 styles it), `tests/Feature/Http/NotFoundTest.php`

**Interfaces:**
- Produces:
  - `CartResource` gains `subtotal_cents: int`.
  - `ShippingMethodResource` gains `fee_cents: int`.
  - `auth.user` gains `balance_cents: int`.
  - Non-JSON 404s render the Inertia component `errors/NotFoundPage` with status 404.

- [ ] **Step 1: Write the failing tests:**
  - **Shared props:** `auth.user.balance_cents` equals the balance in cents.
  - **Cart page:** `cart.subtotal_cents` and `shippingMethods.*.fee_cents` are integers matching the formatted values.
  - **Checkout:** a balance below the total still refuses the order, as today. Keep or extend the existing test.
  - **Not found:**

```php
it('renders the not-found page for unknown pages and missing products', function (string $url) {
    $this->get($url)->assertNotFound()->assertInertia(fn (Assert $page) => $page->component('errors/NotFoundPage'));
})->with(['/no-such-page', '/products/no-such-product']);

it('keeps JSON 404s as JSON', function () {
    $this->getJson('/no-such-page')->assertNotFound()->assertJsonStructure(['message']);
});
```

- [ ] **Step 2: Run them and see them fail.**

- [ ] **Step 3: Implement.** In `bootstrap/app.php`, add `$exceptions->respond(...)`. When the response status is 404 and `! $request->expectsJson()`, return `Inertia::render('errors/NotFoundPage')->toResponse($request)->setStatusCode(404)`; otherwise return the response unchanged. `NotFoundPage.vue` is a minimal page with an `<h1>` "We can't find that page".

- [ ] **Step 4: Run the suite and see it pass.**

- [ ] **Step 5: Run the gates, then commit**: "Expose amounts in cents for the low-balance check and render a not-found page".

### Task 5: Design tokens, fonts, theme and icons

**Files:**
- Modify: `resources/css/app.css`, `package.json` (add `@fontsource/young-serif`, `@fontsource/figtree`, `@fontsource/caveat`), `resources/js/app.ts` (import the fonts), `resources/views/app.blade.php` (favicon links)
- Create: `public/favicon.svg` (replace the existing one), `public/favicon-32.png`, `public/favicon-16.png`, `public/apple-touch-icon.png` (180px, replace), `public/icon-512.png`, `tests/Feature/Http/AppearanceTest.php`
- Delete: `public/images/cartopia_logo.png`, after checking with grep that nothing references it.

**Interfaces:**
- Produces:
  - CSS custom properties for every colour in spec §2.1, under `:root` (light) and `.dark` (dark), with exactly the spec's values.
  - Tailwind colour keys (`@theme inline`): `paper surface field well ink outline body subtle line line-strong green green-deep marigold on-marigold shadow-ink shadow-green cream selected chip chip-ink danger link footer announce announce-ink chalkboard wood chalk star`.
    - Spec `muted` is exposed as `subtle`, to avoid clashing with shadcn's `muted`.
    - Spec `shadow` is exposed as `shadow-ink`, and `shadow-panel` as `shadow-green`.
  - Aisle tints as `--tint-1`, `--tint-2` and `--tint-3`, with light and dark values from spec §2.1.
  - shadcn's own variables (`--background`, `--foreground`, `--primary`, `--border`, `--input`, `--ring`, `--muted`, `--muted-foreground`, `--card`, `--popover`, `--destructive`…) are re-pointed at the new tokens, so the existing shadcn components follow the theme.
  - **Fonts:** `--font-sans` Figtree, `--font-serif` "Young Serif", `--font-chalk` Caveat. Only the weights in spec §2.2.
  - **Shadows:** `--shadow-press` (3px 3px 0 shadow-ink), `--shadow-press-lg` (4px), `--shadow-card` (5px 5px 0 shadow-green), `--shadow-panel` (8px 8px 0 shadow-green). All have zero blur.
  - **Breakpoint:** `--breakpoint-lg: 980px`.
  - **Easing:** `--ease-stall: cubic-bezier(0.22, 0.8, 0.24, 1)`.
  - **Global rules:** `:focus-visible` gets a 3px marigold outline with a 3px offset, and a `prefers-reduced-motion: reduce` rule sets transition and animation durations to 0.

- [ ] **Step 1: Write the failing test** for Review Focus 5, in `AppearanceTest.php`:

```php
it('renders dark mode on the first paint when the cookie says dark', function () {
    $this->withUnencryptedCookie('appearance', 'dark')->get(route('home'))
        ->assertOk()->assertSee('class="dark"', false);
});
```

Adjust the assertion to match how `app.blade.php` actually writes the class. If the kit already passes, keep the test as a guard and note that in the report.

- [ ] **Step 2: Run it** and see it fail, or pass for the reason above.

- [ ] **Step 3: Implement the tokens, fonts, favicons and blade links.** Draw the favicon and icon files from the Brand page, "Motion and icons" sheet: the logo mark on stall green, with rounded corners. The 16px and 32px versions are simplified: a solid marigold roof, a white crate and two wheels, with no stripes or outlines.

- [ ] **Step 4: Verify.** The test passes, and so do `npm run build`, `npm run types` and `npm run lint`. Load `https://cartopia.test` once in each mode and confirm the page background is `#EDF2E4` in light and `#111915` in dark.

- [ ] **Step 5: Run the gates, then commit**: "Add the market-stall design tokens, fonts and icons".

### Task 6: Shared building blocks

**Files:**
- Create in `resources/js/common/`:
  - `brand/AppLogo.vue`, `icon/Icon.vue`
  - `stall/StallButton.vue`, `stall/IconButton.vue`, `stall/PriceTag.vue`, `stall/Chalkboard.vue`, `stall/SectionHeading.vue`
  - `stall/OptionCard.vue`, `stall/Chip.vue`, `stall/StatusChip.vue`, `stall/QuantityBasketBar.vue`
  - `sheet/Sheet.vue`, `theme/ThemeToggle.vue`
- Modify: `common/input/Input.vue`, `common/select/Select.vue`, `common/pagination/Pagination.vue`, `common/flash-toast/FlashToast.vue`, `common/dialog/DialogContent.vue`, `common/button/Button.vue`. Restyle these to the tokens; their props stay the same.
- Delete: `common/app/AppLogoIcon.vue`. Update its importers to use `AppLogo`.

**Interfaces (props, all typed with `defineProps<…>()`):**
- `AppLogo { variant?: 'full' | 'mark'; size?: 'header' | 'phone' }`. The colours follow the theme tokens. Drawn from the Brand page, "Logo" sheet.
- `Icon { name: 'basket-add' | 'basket' | 'search' | 'menu' | 'close' | 'moon' | 'sun' | 'remove' | 'filters' | 'pickup' | 'delivery' | 'chevron-left' | 'chevron-right' | 'check'; size?: number }`. Always `aria-hidden`.
- `StallButton { variant: 'marigold' | 'green' | 'outline' | 'quiet'; size?: 'md' | 'lg'; as?: 'button' | 'link'; href?: string; disabled?: boolean }`. Marigold and green have the 2.5px outline, a hard shadow and the press motion (120ms, `ease-stall`).
- `IconButton { label: string; icon: IconName; variant?: 'surface' | 'green' | 'marigold' }`. 44 × 44px, with `aria-label` set to `label`.
- `PriceTag { price: string; size?: 'sm' | 'lg' }`
- `Chalkboard`: a slot with an optional `title` prop. It uses the fixed chalkboard, wood and chalk tokens and the Caveat title.
- `SectionHeading { title: string; linkLabel?: string; href?: string }`
- `OptionCard { selected: boolean; disabled?: boolean }`: a slot, wrapping a native radio supplied by the caller.
- `Chip { tone?: 'tint-1' | 'tint-2' | 'tint-3' | 'selected' | 'plain'; removable?: boolean }`, which emits `remove`.
- `StatusChip`: a slot.
- `QuantityBasketBar { productName: string; modelValue: number; min?: number; max?: number; compact?: boolean }`. It emits `update:modelValue` and `add`. `compact` renders the phone's full-width "Add" button instead of the joined bar.
- `Sheet { open: boolean; side: 'right' | 'left' | 'bottom'; label: string }`. It emits `close`; traps focus; closes on Escape or a scrim click; returns focus to the element that opened it; slides in over 300ms. It uses shadcn's dialog primitives under the hood.
- `ThemeToggle { variant: 'button' | 'segmented' }`. `button` toggles between light and dark through `useAppearance` and shows a moon or a sun; `segmented` shows Light / Dark / Device.

- [ ] **Step 1:** Build each component against the Brand page, "Components and details" sheet.
- [ ] **Step 2:** Verify: `npm run types`, `npm run lint` and `npm run build` pass, and `rg "#[0-9A-Fa-f]{6}" resources/js/common` finds nothing.
- [ ] **Step 3: Run the gates, then commit**: "Add the stall building blocks and restyle the shared wrappers".

### Task 7: Store layout: header, phone header and menu, footer

**Files:**
- Create in `resources/js/modules/layout/`: `StoreHeader.vue`, `PhoneHeader.vue`, `PhoneMenu.vue`, `StoreFooter.vue`, `Awning.vue`
- Modify: `resources/js/pages/layouts/StoreLayout.vue`, `resources/js/types/shared.ts` (add `balance_cents`, and the `addedToBasket` flash type from Task 3)
- Create: `tests/Browser/RedesignTest.php`

**Interfaces:**
- Consumes: the shared props `auth.user`, `cart.count` and `flash`; `ThemeToggle`, `AppLogo`, `Sheet` and `IconButton` (Task 6).
- Produces: `StoreLayout` renders the announcement bar, then `StoreHeader` (desktop) or `PhoneHeader` (below 640px), then the awning, the page slot and `StoreFooter`.

Match the [Header], [Phone header], [Phone: menu] and [Footer] mockups in both themes.

**The menu's aisle chips:** `HandleInertiaRequests` shares `menuAisles` as `Inertia::optional(fn () => AisleResource::collection(Aisles::all())->resolve())`. It's sent only when asked for. `PhoneMenu` calls `router.reload({ only: ['menuAisles'] })` the first time it opens. Each chip links to `products.index` with `aisle[]=<slug>`.

Add a `SharedPropsTest` assertion that `menuAisles` is absent from a normal page load.

- [ ] **Step 1: Write the failing browser tests** in `RedesignTest.php`:

```php
it('switches to dark mode and keeps it after a reload', function () {
    visit('/')->click('Switch to dark mode')
        ->assertScript('document.documentElement.classList.contains("dark")', true)
        ->refresh()
        ->assertScript('document.documentElement.classList.contains("dark")', true);
});

it('opens the phone menu and never scrolls sideways at 360px', function () {
    visit('/')->resize(360, 800)
        ->assertScript('document.documentElement.scrollWidth <= window.innerWidth', true)
        ->click('Open menu')->assertSee('Log in');
});
```

Use the plugin's equivalent helpers if these names differ.

- [ ] **Step 2: Run them and see them fail.**
- [ ] **Step 3: Build the layout.**
- [ ] **Step 4: Run the browser tests and see them pass**, then run the full suite.
- [ ] **Step 5: Run the gates, then commit**: "Rebuild the store header, phone menu and footer".

### Task 8: Product card, featured carousel and the home page

**Files:**
- Modify: `resources/js/modules/products/ProductCard.vue`, `resources/js/pages/WelcomePage.vue`, `resources/js/types/catalog.ts` (`Aisle` type, `aisle` on products)
- Create: `resources/js/modules/products/FeaturedCarousel.vue`, `resources/js/modules/products/AisleTile.vue`, `resources/js/modules/home/HowItWorks.vue`, `resources/js/modules/home/DeliveryOptions.vue`, `resources/js/modules/home/FaqList.vue`
- Test: `tests/Browser/RedesignTest.php`

**Interfaces:**
- Consumes: the home page props `counter`, `featured` and `aisles` (Task 2); `PriceTag`, `QuantityBasketBar`, `Chalkboard`, `SectionHeading` and `StallButton` (Task 6).
- Produces:
  - `FeaturedCarousel { products: Product[] }`:
    - Renders nothing when empty.
    - Shows 3, 2 or 1 cards depending on the breakpoint.
    - The arrows (`IconButton`, labelled "Previous featured products" and "Next featured products") and the dots slide the row with `transform` over 500ms using `ease-stall`, and wrap at the ends.
    - It is `aria-roledescription="carousel"`; cards out of view are `aria-hidden` with `tabindex=-1`, and a live region announces "Showing products X to Y of N".
    - When there are no more products than the visible count, it hides the arrows and dots and keeps the normal card width.
    - On phones it is a swipeable `scroll-snap` row instead.
  - `AisleTile { aisle: Aisle; tint: 1 | 2 | 3 }`: the tint is the aisle's index modulo 3, plus 1. With no `image_url` it shows a basket icon on `well`.
  - `ProductCard { product: Product }`: the joined bar on desktop, the compact "Add" on phones. Adding posts to `cart.items.store` with `preserveScroll`.

The home page follows spec §4.2 and the [Home] mockup. The order is: hero and chalkboard (from `counter`), carousel, aisles, counter grid, how it works, wallet band, delivery, FAQ.
- **Delivery options:** read from a `shippingMethods` prop. Add it to `HomeController` using the existing `ShippingMethodResource`, and extend Task 2's catalogue test with a single assertion that it's present.
- **FAQ copy:** exactly as in the mockup, with "What if my balance is too low?" answered as "Top up your wallet first, then place the order again. Your basket stays as it was." (wallet-only this sub-project).

- [ ] **Step 1: Write the failing browser tests:**

```php
it('slides the featured carousel when Next is pressed', function () {
    Product::factory()->count(5)->featured()->create();
    visit('/')->assertScript('document.querySelector("[aria-roledescription=carousel] [aria-live]").textContent.includes("1 to 3")', true)
        ->click('Next featured products')
        ->assertScript('document.querySelector("[aria-roledescription=carousel] [aria-live]").textContent.includes("2 to 4")', true);
});

it('hides carousel arrows when every featured product already fits', function () {
    Product::factory()->count(2)->featured()->create();
    visit('/')->assertDontSee('Next featured products');
});
```

- [ ] **Step 2: Run them and see them fail.**
- [ ] **Step 3: Build the components and the page.**
- [ ] **Step 4: Run the browser tests, then the full suite, and see them pass.**
- [ ] **Step 5: Run the gates, then commit**: "Rebuild the home page with the featured carousel and aisles".

### Task 9: Shop and product pages

**Files:**
- Modify: `resources/js/pages/products/ProductsIndexPage.vue`, `resources/js/modules/products/ProductFilters.vue`, `resources/js/pages/products/ProductShowPage.vue`, `resources/js/modules/products/RatingInput.vue`, `resources/js/modules/products/StarRating.vue`
- Create: `resources/js/modules/products/FilterSheet.vue`, `resources/js/modules/products/ActiveFilterChips.vue`, `resources/js/modules/products/NoResults.vue`
- Test: `tests/Browser/RedesignTest.php`

**Interfaces:**
- Consumes: the shop page props (Task 2), `FeaturedCarousel` and `ProductCard` (Task 8), and `Sheet` and `Chip` (Task 6).
- Produces: filter changes navigate with `router.get(…, { preserveScroll: true, preserveState: true })`. The query keys are exactly `q`, `aisle[]`, `min_price`, `max_price`, `rating`, `rated`, `featured` and `sort`.
  - **Price shortcuts** set `min_price` and `max_price` to `''`/`'1'`, `'1'`/`'2'` and `'2'`/`'5'`.
  - **Phones:** filters move into `FilterSheet` (`side="bottom"`), whose apply button reads "Show N products", where N is the current `products.meta.total`.
  - **No results:** when `products.data` is empty, show `NoResults`: the active filters as removable chips with "Clear all", the tips, aisle shortcuts, and the carousel.

Match [Shop], [Shop: no results], [Phone: shop] and [Product]. On the product page:
- The breadcrumb reads Shop / aisle / product; the aisle part is left out when `product.category` is `null`.
- The add button is `StallButton` marigold with the label "Add to basket".
- The carousel uses the `featured` prop.

- [ ] **Step 1: Write the failing browser test:**

```php
it('narrows the grid when an aisle is ticked', function () {
    $drinks = Category::factory()->create(['name' => 'Drinks', 'slug' => 'drinks']);
    Product::factory()->inCategory($drinks)->create(['name' => 'Water']);
    Product::factory()->create(['name' => 'Apple']);
    visit('/products')->check('Drinks')->waitForText('Water')->assertDontSee('Apple');
});
```

- [ ] **Step 2: Run it and see it fail.**
- [ ] **Step 3: Build the pages.**
- [ ] **Step 4: Run the browser tests, then the full suite, and see them pass.**
- [ ] **Step 5: Run the gates, then commit**: "Rebuild the shop and product pages with aisle filters".

### Task 10: Added-to-basket panel and the basket page

**Files:**
- Create: `resources/js/modules/cart/AddedToBasketPanel.vue`, `resources/js/modules/cart/PayWithOptions.vue`
- Modify:
  - `resources/js/pages/layouts/StoreLayout.vue` (mount the panel once)
  - `resources/js/pages/cart/CartPage.vue`, `resources/js/modules/cart/CartLineItem.vue`, `resources/js/modules/cart/CartSummary.vue`, `resources/js/modules/cart/CheckoutPanel.vue`, `resources/js/modules/cart/QuantityStepper.vue`
  - `resources/js/lib/flashToast.ts` (skip the toast when `addedToBasket` is present)
  - `tests/Browser/CheckoutFlowTest.php`
- Test: `tests/Browser/RedesignTest.php`

**Interfaces:**
- Consumes: `flash.addedToBasket` (Task 3); `subtotal_cents`, `fee_cents` and `auth.user.balance_cents` (Task 4); `Sheet` and `OptionCard` (Task 6).
- Produces:
  - `AddedToBasketPanel` opens whenever `flash.addedToBasket` is not null. It's a right-hand `Sheet` (full width on phones) containing:
    - the heading "Added to your basket", the item with quantity × unit and its line total, and the basket count and subtotal
    - "View basket and check out" (green), linking to `cart.show`
    - "Keep shopping", which closes it
    - "Goes well with", hidden when `suggestions` is empty
  - `PayWithOptions { balance: string; balanceCents: number; totalCents: number }`. This sub-project renders one wallet `OptionCard`. When `balanceCents < totalCents` the card is disabled and reads "Balance $X. You need $Y more.", with a "Top up your wallet" link to `wallet.show`; the parent then disables "Place order".
  - The basket page follows [Basket], [Basket: wallet too low], [Basket: empty] and [Phone: basket]:
    - **Shipping choice:** `OptionCard`s ("How do you want it?").
    - **Total:** subtotal plus the chosen fee, computed from the `*_cents` fields and formatted on the client the same way as the server (`$` + two decimals).
    - **Button:** "Place order and pay $X".
    - **Phones:** a total and "Place order" bar pinned to the bottom of the screen.
    - **Guests:** see "Log in to check out" in place of the pay section, as today.

- [ ] **Step 1: Write the failing browser tests and update the existing one:**

```php
it('opens the added-to-basket panel and closes it with Escape', function () {
    Product::factory()->create(['name' => 'Apple']);
    visit('/products')->click('Add Apple to basket')
        ->waitForText('Added to your basket')
        ->keys('body', 'Escape')
        ->assertDontSee('Added to your basket');
});
```

Update `CheckoutFlowTest.php` to the new labels: "Add … to basket", the panel's "View basket and check out", the shipping option cards, and "Place order and pay $X". Keep its final assertion on the placed order.

- [ ] **Step 2: Run them and see them fail.**
- [ ] **Step 3: Build the panel and the basket page.**
- [ ] **Step 4: Run all the browser tests, then the full suite, and see them pass.**
- [ ] **Step 5: Run the gates, then commit**: "Add the added-to-basket panel and rebuild the basket".

### Task 11: Orders, receipt and wallet pages

**Files:**
- Modify: `resources/js/pages/orders/OrdersIndexPage.vue`, `resources/js/pages/orders/OrderShowPage.vue`, `resources/js/pages/wallet/WalletPage.vue`, `resources/js/modules/wallet/LedgerTable.vue`, `resources/js/modules/wallet/TopUpForm.vue`, `tests/Browser/TopUpTest.php`

Match [Orders] (including its empty state), [Receipt: wallet] and [Wallet], in both themes.
- **Orders list:** each row shows its thumbnails, a "Paid with wallet" tag and a `StatusChip`. The thumbnails need the first two product images per order: add `thumbnails: string[]` (at most 2 URLs) to `OrderResource`, plus a feature assertion in `tests/Feature/Http/OrdersTest.php`.
- **Receipt:** a paper slip with a torn edge (a CSS `mask` zig-zag) and the success banner when the flash says "Order placed".
- **Wallet:**
  - The balance sits on a `Chalkboard`.
  - The quick amounts $10, $25, $50 and $100 fill the amount field.
  - The button reads "Add $X to my wallet" (no "Pay with" choice in this sub-project).
  - The history table scrolls sideways inside its own box on phones.

- [ ] **Step 1: Write the failing test.** Add the `thumbnails` assertion to `OrdersTest.php`, update `TopUpTest.php` to the new labels, and see them fail.
- [ ] **Step 2: Build the pages.**
- [ ] **Step 3: Run the suite and the browser tests and see them pass.**
- [ ] **Step 4: Run the gates, then commit**: "Rebuild the orders, receipt and wallet pages".

### Task 12: Log in, settings, not-found page, copy pass and README

**Files:**
- Modify:
  - every page in `resources/js/pages/auth/`
  - `resources/js/pages/layouts/AuthLayout.vue` and the files in `pages/layouts/auth/`
  - `resources/js/pages/settings/*.vue` and `resources/js/pages/layouts/settings/Layout.vue`
  - `resources/js/pages/errors/NotFoundPage.vue`
  - `README.md`, `docs/screenshots/*`
- Possibly modify: any `.vue` file still showing "cart", "Add to cart" or "Sign in" as visible text.

The auth pages match [Log in]: the form card beside the "Why sign up?" chalkboard. Register, forgot password, reset password, the two-factor challenge and confirm password use the same layout. The settings pages use the store layout with the tokens. The not-found page matches [Page not found]: the tilted "Closed" chalkboard reading "This stall has packed up for the day", the heading "We can't find that page", and links to the home page and the shop.

- [ ] **Step 1: Copy pass.** Run `rg -n "Add to cart|Added to cart|[^/]cart\b|Sign in" resources/js --glob '*.vue'` and change only visible text to the spec's words. Update any feature or browser test that asserts the old text.
- [ ] **Step 2: Build the auth, settings and not-found pages.**
- [ ] **Step 3: Run everything.** Run all the gates and the full Pest suite, including the browser tests. Then run the suite against MySQL as well: `DB_CONNECTION=mysql DB_DATABASE=cartopia_testing`.
- [ ] **Step 4: README.** Retake the screenshots from `https://cartopia.test` after `php artisan migrate:fresh --seed`: home, shop, product, basket and wallet, each in light and dark, saved under `docs/screenshots/` as `<page>-light.png` and `<page>-dark.png`. Update the README's feature list (aisles, featured, themes) and its screenshot section.
- [ ] **Step 5: Commit**: "Restyle the account pages, add the not-found page and refresh the README".
