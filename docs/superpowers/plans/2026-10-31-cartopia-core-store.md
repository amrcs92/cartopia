# Cartopia Core Store Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Rebuild Cartopia's store (catalog, cart, balance checkout, ratings, order history, wallet top-up) as a Laravel 13 + Inertia/Vue app that replaces the legacy plain-PHP code on the `laravel` branch.

**Architecture:** Laravel's official Vue starter kit provides auth and the SPA shell. Business rules live in small domain classes:
- `Money`
- `Cart` with two `CartStore` implementations
- `Wallet`
- the `PlaceOrder`, `RateProduct` and `TopUpWallet` actions

Thin controllers validate input through FormRequests and render Inertia pages from API Resources. All money is integer cents and is formatted only on the server.

**Tech Stack:**
- PHP 8.3 (Laragon) and Laravel 13
- Inertia 2, Vue 3 + TypeScript, Tailwind 4, shadcn-vue
- MySQL 8.4 for the app and SQLite in-memory for tests
- Pest 4, including browser tests
- Pint, Larastan, ESLint, Prettier, vue-tsc
- GitHub Actions

**Spec:** `docs/superpowers/specs/2026-10-31-cartopia-laravel-core-store-design.md`. Read it before starting any task; section numbers below (§n) refer to it.

## Global Constraints

### Environment

- **Paths.** The app lives in `C:\laragon\www\cartopia` (Git Bash path `/c/laragon/www/cartopia`) on branch `laravel`. It is served at `http://cartopia.test`.
- **PHP.** The `php` on PATH is XAMPP's PHP 8.2. **Never call bare `php` or `composer`.** Every command in this plan uses:
  ```bash
  PHP=/c/laragon/bin/php/php-8.3.36-Win32-vs16-x64/php.exe
  COMPOSER="$PHP /c/laragon/bin/composer/composer.phar"
  ```
- **Node.** Node 22 (`node -v` → v22.x).
- **MySQL.** Laragon's MySQL 8.4.3 must be running with user `root` and an empty password. XAMPP's MySQL must be stopped, since both use port 3306.

### Git

- Commit messages are subject + body only. **No `Co-Authored-By` trailer and no AI attribution.**
- Never push. Pushing is the user's call.

### Before every commit

These must all pass:
- `$PHP vendor/bin/pint --test`
- `$PHP vendor/bin/phpstan analyse` (from Task 3 onward)
- `npm run lint`
- `npm run types`
- `$PHP vendor/bin/pest`

### Money

- Money is integer cents everywhere, wrapped in `App\Support\Money`.
- No float arithmetic on money in PHP, and no money arithmetic at all in TypeScript. The client only displays server-formatted strings.

### Front-end conventions (§6.1)

- `<script setup lang="ts">` in every component, one component per file.
- PascalCase component files, camelCase composables, kebab-case directories, no barrel files.
- shadcn-vue lives in `resources/js/lib/shadcn`. Store code imports it **only** through `resources/js/common/**` wrappers.
- Pages end in `Page`.

### Inertia page components

| Page | Component name |
|---|---|
| Welcome | `WelcomePage` |
| Product list | `products/ProductsIndexPage` |
| Product detail | `products/ProductShowPage` |
| Cart | `cart/CartPage` |
| Order list | `orders/OrdersIndexPage` |
| Order detail | `orders/OrderShowPage` |
| Wallet | `wallet/WalletPage` |

### Route names

`home`, `products.index`, `products.show`, `cart.show`, `cart.items.store`, `cart.items.update`, `cart.items.destroy`, `checkout.store`, `products.rating.update`, `orders.index`, `orders.show`, `wallet.show`, `wallet.top-ups.store`

### Fixed copy

| Event | Message |
|---|---|
| Checkout succeeded | `Order placed.` |
| Insufficient balance | `Your balance is too low for this order.` |
| Empty cart | `Your cart is empty.` |
| Added to cart | `Added to cart.` |
| Rating saved | `Thanks for rating!` |
| Top-up succeeded | `Added {amount} to your balance.` (for example `Added $25.50 to your balance.`) |

### Numeric limits

- Cart quantity: 1–99 per product.
- Top-up: $5.00–$500.00, with at most 2 decimal places.
- Signup bonus: $100.00.
- Products per page: 12. Ledger rows per page: 15. Orders per page: 10.
- Featured products: the 4 newest.
- Throttle: `throttle:10,1` on checkout and top-up.

## Review Focus

These are the inputs most likely to break for a real user that the spec implies but doesn't spell out. Each has a pinned test in the task named.

1. **Price changes between adding to the cart and checking out.** The order must charge the *current* DB price. Pinned in Task 11 (`it charges the current price when the price changed after adding`).
2. **Double-submitting checkout** (double-click or a retry). This must produce exactly one order, and the second request must flash `Your cart is empty.`. Pinned in Task 11 (`it places only one order on double submit`).
3. **A guest adds items, then *registers* rather than logging in.** The cart must survive into the new account. Pinned in Task 10 (`it keeps the guest cart when a guest registers`).
4. **A search term containing `%` or `_`.** These must match literally and must not act as SQL wildcards. Pinned in Task 7 (`it treats LIKE wildcards in the search term literally`).
5. **A product deleted while it sits in a guest's session cart.** The cart page, badge count and checkout must silently ignore it rather than crash. Pinned in Task 8 (`it drops products that no longer exist`).

---

## File Structure

```
app/
  Actions/PlaceOrder.php, RateProduct.php, TopUpWallet.php
  Cart/CartStore.php, SessionCartStore.php, DatabaseCartStore.php, Cart.php, CartSummary.php, CartLine.php
  Casts/MoneyCast.php
  Enums/OrderStatus.php, WalletTransactionType.php
  Exceptions/InsufficientBalance.php, EmptyCart.php
  Http/Controllers/HomeController.php, ProductController.php, CartController.php, CartItemController.php,
                   CheckoutController.php, RatingController.php, OrderController.php, WalletController.php, TopUpController.php
  Http/Requests/ProductIndexRequest.php, AddToCartRequest.php, UpdateCartItemRequest.php, CheckoutRequest.php,
                RateProductRequest.php, TopUpRequest.php
  Http/Resources/ProductResource.php, ProductDetailResource.php, CartResource.php, OrderResource.php,
                 OrderItemResource.php, WalletTransactionResource.php, ShippingMethodResource.php
  Http/Middleware/HandleInertiaRequests.php   (kit file, modified)
  Listeners/GrantSignupBonus.php, MergeGuestCart.php
  Models/User.php (kit, modified), Product.php, ProductImage.php, Rating.php, ShippingMethod.php, Cart.php,
         CartItem.php, Order.php, OrderItem.php, WalletTransaction.php
  Policies/OrderPolicy.php
  Support/Money.php
  Wallet/Wallet.php
database/
  migrations/ (kit users migration modified + 9 new)
  factories/ (one per model)
  seeders/DatabaseSeeder.php, images/{apple,heineken_beer,water_bottle,cheese-traingles}.jpg
resources/js/  (layout per Global Constraints)
tests/
  Unit/Support/MoneyTest.php
  Feature/Domain/*.php     (DB-backed domain tests)
  Feature/Http/*.php       (endpoint tests)
  Browser/*.php
.github/workflows/ci.yml
phpstan.neon
```

**Naming collision:** the Eloquent cart model is `App\Models\Cart` and the domain service is `App\Cart\Cart`. Wherever both appear in one file, alias the model: `use App\Models\Cart as CartModel;`.

---

### Task 1: Bootstrap the Laravel app in Laragon

**Files:**
- Create: the whole starter-kit tree in `/c/laragon/www/cartopia`
- Move: legacy `images/{apple.jpg,heineken_beer.jpg,water_bottle.jpg,cheese-traingles.jpg}` → `database/seeders/images/`, and `images/cartopia_logo.png` → `public/images/cartopia_logo.png`
- Delete: every other legacy file at the repo root (`*.php`, `.htaccess`, `css/`, `js/`, `font/`, `include/`, `config/database.php`, `dev/`, `cartopia.sql`, `database tables structures.txt`, unused images)
- Keep: `docs/` and `README.md` (README is rewritten in Task 15)

**Interfaces:**
- Produces: a booting kit app connected to MySQL `cartopia`, with Pest as the test runner.

- [ ] **Step 1: Check the environment.**
  - Laragon must be running ("Start All") with MySQL 8.4.3 selected, and XAMPP's MySQL stopped.
  - Run: `/c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -uroot -e "SELECT VERSION()"`
  - Expected: `8.4.3`.

- [ ] **Step 2: Clone the local repo into Laragon** (the `laravel` branch only exists locally):
  ```bash
  git clone -b laravel /c/xampp/htdocs/cartopia /c/laragon/www/cartopia
  cd /c/laragon/www/cartopia && git remote set-url origin https://github.com/amrcs92/cartopia.git
  ```

- [ ] **Step 3: Scaffold the kit in a temporary folder.**
  - Run: `$COMPOSER create-project laravel/vue-starter-kit /c/laragon/www/_kit`
  - If Composer complains about the `zip` extension, enable `extension=zip` in `/c/laragon/bin/php/php-8.3.36-Win32-vs16-x64/php.ini` and retry.

- [ ] **Step 4: Swap the legacy files for the kit.** In the clone:
  1. `git mv` the 5 images listed above to their new paths.
  2. `git rm -r` the legacy files.
  3. Copy everything from `_kit` except `.git` and `README.md` into the clone (`cp -rn` is fine; nothing collides once the legacy files are gone).
  4. Delete `_kit`.

- [ ] **Step 5: Point the app at MySQL.**
  - Create the database:
    ```bash
    /c/laragon/bin/mysql/mysql-8.4.3-winx64/bin/mysql.exe -uroot -e "CREATE DATABASE IF NOT EXISTS cartopia CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
    ```
  - In `.env` and `.env.example`, set: `APP_NAME=Cartopia`, `APP_URL=http://cartopia.test`, `DB_CONNECTION=mysql`, `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=cartopia`, `DB_USERNAME=root`, `DB_PASSWORD=`.
  - Confirm `phpunit.xml` keeps `DB_CONNECTION=sqlite` and `DB_DATABASE=:memory:`.

- [ ] **Step 6: Make sure Pest is the runner.**
  - If `vendor/bin/pest` doesn't exist:
    ```bash
    $COMPOSER require pestphp/pest pestphp/pest-plugin-laravel --dev -W
    $COMPOSER require pestphp/pest-plugin-drift --dev
    $PHP vendor/bin/pest --drift
    $COMPOSER remove pestphp/pest-plugin-drift --dev
    ```
  - Set `tests/Pest.php` to apply `Tests\TestCase` and `RefreshDatabase` to `Feature` and `Browser`.

- [ ] **Step 7: Install and verify.**
  - Run: `$PHP artisan migrate && npm install && npm run build && $PHP vendor/bin/pest`
  - Expected: migrations run, the build succeeds, and all kit tests pass.
  - Open `http://cartopia.test`: it should show the kit's welcome page, and register/login should work.

- [ ] **Step 8: Commit.**
  - Subject: `Replace legacy PHP app with Laravel 13 Vue starter kit`
  - Body: mention that the images were moved for seeding.

---

### Task 2: Restructure the front end to project conventions

**Files:**
- Move: `resources/js/components/ui/**` → `resources/js/lib/shadcn/**`
- Move: the kit's other `resources/js/components/*.vue` → `resources/js/common/app/`
- Move: the kit's `resources/js/layouts/**` → `resources/js/pages/layouts/**`
- Rename: every kit page to the `*Page.vue` suffix (for example `pages/auth/Login.vue` → `pages/auth/LoginPage.vue`, `pages/settings/Profile.vue` → `pages/settings/ProfilePage.vue`)
- Modify: `components.json` (the `ui` and `components` aliases → `@/lib/shadcn` and `@/common/app`), every import, and every `Inertia::render(...)` or `Inertia` page name in PHP (kit controllers, or the Fortify view callbacks in `app/Providers/FortifyServiceProvider.php` if the kit uses Fortify)
- Modify: `config/inertia.php` `testing.page_paths` (if present) so it resolves `resources/js/pages`
- Test: the existing kit tests

**Interfaces:**
- Produces: the import aliases `@/lib/shadcn/<component>/…` and `@/common/...`, and the page names `auth/LoginPage`, `auth/RegisterPage`, `settings/ProfilePage` and so on.

- [ ] **Step 1: Do the moves and renames with `git mv`,** then fix the imports. Don't edit the shadcn component bodies.

- [ ] **Step 2: Verify.**
  - Run: `npm run types && npm run lint && npm run build && $PHP vendor/bin/pest`
  - Expected: everything passes, and the kit's auth tests still assert the renamed components.
  - Run: `grep -rn "@/components" resources/js`
  - Expected: no output.

- [ ] **Step 3: Commit:** `Restructure front end into lib/common/modules/pages conventions`

---

### Task 3: Static analysis, formatting and CI

**Files:**
- Create: `phpstan.neon` and `.github/workflows/ci.yml`
- Delete: the kit's own workflow files in `.github/workflows/` (replaced by `ci.yml`)
- Modify: `composer.json` (scripts) and `package.json` (scripts, if missing)

**Interfaces:**
- Produces:
  - Composer scripts: `composer lint` (`pint --test`), `composer analyse` (`phpstan analyse`)
  - npm scripts: `npm run lint` (ESLint, no fix), `npm run format:check` (Prettier check), `npm run types` (`vue-tsc --noEmit`)
  - Use the kit's script names if they already exist. Otherwise add exactly these.

- [ ] **Step 1: Install Larastan.**
  - Run: `$COMPOSER require larastan/larastan --dev`
  - Write `phpstan.neon`: include the Larastan extension, set `level: 6` and `paths: [app]`.

- [ ] **Step 2: Run the checks.**
  - Run: `$PHP vendor/bin/phpstan analyse && $PHP vendor/bin/pint --test && npm run lint && npm run format:check && npm run types`
  - Expected: all pass. Fix any kit code that fails rather than lowering the level.

- [ ] **Step 3: Write `.github/workflows/ci.yml`.**
  - Triggers: `push` and `pull_request`.
  - Runner: `ubuntu-latest`, PHP 8.3 via `shivammathur/setup-php` (extensions `mbstring, pdo_sqlite, bcmath, intl`), Node 22.
  - Steps, in order:
    1. `composer install`
    2. `npm ci`
    3. `cp .env.example .env && php artisan key:generate`
    4. `vendor/bin/pint --test`
    5. `vendor/bin/phpstan analyse`
    6. `npm run lint`
    7. `npm run format:check`
    8. `npm run types`
    9. `npm run build`
    10. `vendor/bin/pest`
  - Task 14 adds Playwright.

- [ ] **Step 4: Commit:** `Add Larastan, lint scripts and CI workflow`

---

### Task 4: Money value object

**Files:**
- Create: `app/Support/Money.php`
- Test: `tests/Unit/Support/MoneyTest.php`

**Interfaces:**
- Produces: `final readonly class App\Support\Money`, which is immutable. Every operation returns a new instance.
  - `static fromCents(int $cents): self`
  - `static fromDollars(string $dollars): self` (input is already validated as `decimal:0,2`)
  - `static zero(): self`
  - `cents(): int`
  - `add(Money $o): self`
  - `subtract(Money $o): self`
  - `multiply(int $times): self`
  - `isLessThan(Money $o): bool`
  - `isNegative(): bool`
  - `equals(Money $o): bool`
  - `format(): string`

- [ ] **Step 1: Write the failing tests.**
  ```php
  it('formats cents as dollars', function (int $cents, string $expected) {
      expect(Money::fromCents($cents)->format())->toBe($expected);
  })->with([[0, '$0.00'], [374, '$3.74'], [123456789, '$1,234,567.89'], [-500, '-$5.00']]);

  it('parses dollar strings without float drift', function (string $in, int $cents) {
      expect(Money::fromDollars($in)->cents())->toBe($cents);
  })->with([['12.5', 1250], ['0.30', 30], ['500', 50000], ['4.99', 499], ['0.1', 10]]);

  it('does arithmetic immutably', function () {
      $a = Money::fromCents(374);
      expect($a->multiply(3)->cents())->toBe(1122)
          ->and($a->add(Money::fromCents(26))->cents())->toBe(400)
          ->and($a->cents())->toBe(374)
          ->and($a->subtract(Money::fromCents(400))->isNegative())->toBeTrue()
          ->and(Money::fromCents(1)->isLessThan(Money::fromCents(2)))->toBeTrue()
          ->and(Money::zero()->equals(Money::fromCents(0)))->toBeTrue();
  });
  ```

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Unit/Support/MoneyTest.php`
  - Expected: FAIL (class not found).

- [ ] **Step 3: Implement `Money`.**
  - `fromDollars` must split the string on `.` and pad the fraction to 2 digits. It must not use `(float)`.
  - `format` uses `number_format(abs($cents) / 100, 2)` with a `-` prefix for negatives. Display-only float use is acceptable here.

- [ ] **Step 4: Run the tests.** Same command; expected: PASS.

- [ ] **Step 5: Commit:** `Add Money value object`

---

### Task 5: Schema, enums, models and factories

**Files:**
- Modify: the kit users migration (`database/migrations/0001_01_01_000000_create_users_table.php`): add `phone` (string 100, nullable), `address` (string 200, nullable), `balance` (unsignedInteger, default 0), `is_admin` (boolean, default false)
- Create migrations, in this order: `create_products_table`, `create_product_images_table`, `create_ratings_table`, `create_shipping_methods_table`, `create_carts_table`, `create_cart_items_table`, `create_orders_table`, `create_order_items_table`, `create_wallet_transactions_table`. Columns, indexes and FK actions come exactly from spec §3.2.
- Create: `app/Casts/MoneyCast.php`, `app/Enums/OrderStatus.php`, `app/Enums/WalletTransactionType.php`
- Create models: `app/Models/{Product,ProductImage,Rating,ShippingMethod,Cart,CartItem,Order,OrderItem,WalletTransaction}.php`
- Modify: `app/Models/User.php`
- Create factories: `database/factories/{Product,ProductImage,Rating,ShippingMethod,Cart,CartItem,Order,OrderItem}Factory.php`
- Modify: `database/factories/UserFactory.php`
- Test: `tests/Feature/Domain/SchemaTest.php`

**Interfaces:**
- **`MoneyCast implements CastsAttributes`:** maps int to `Money` on get. On set it accepts `Money|int` and returns int. Null passes through.
- **Enums:**
  - `enum OrderStatus: string { case Placed = 'placed'; }`
  - `enum WalletTransactionType: string { case SignupBonus = 'signup_bonus'; case TopUp = 'top_up'; case Purchase = 'purchase'; case AdminAdjustment = 'admin_adjustment'; }`, with `label(): string` returning `Signup bonus`, `Top-up`, `Purchase` or `Adjustment`.
- **`MoneyCast` columns:**
  - `Product.price`
  - `ShippingMethod.fee`
  - `Order.subtotal`, `shipping_fee`, `total`
  - `OrderItem.unit_price`, `line_total`
  - `User.balance`
  - `WalletTransaction.amount`, `balance_after`
- **Other casts:** `Order.status` → `OrderStatus`, `WalletTransaction.type` → `WalletTransactionType`, `User.is_admin` → bool.
- **`WalletTransaction`:** `const UPDATED_AT = null`.
- **Relationships** exactly as in §3.3, with these method names:
  - `User`: `cart()`, `orders()`, `ratings()`, `walletTransactions()`
  - `Product`: `images()`, `primaryImage()`, `ratings()`, `cartItems()`
  - `Cart`: `user()`, `items()`
  - `CartItem`: `cart()`, `product()`
  - `Order`: `user()`, `shippingMethod()`, `items()`
  - `OrderItem`: `order()`, `product()`
  - `WalletTransaction`: `user()`, `order()`
- `Product::primaryImage()` is `hasOne(ProductImage::class)->oldestOfMany('sort_order')`, and `Product::images()` is ordered by `sort_order`.
- **Route keys:** `Product::getRouteKeyName()` returns `'slug'`.
- **Factory states:**
  - `UserFactory::admin()`
  - `ProductFactory::withImages(int $count = 1)`
  - `ShippingMethodFactory::pickup()` → `['name' => 'Pickup', 'fee' => 0]`
  - `ShippingMethodFactory::ups()` → `['name' => 'UPS', 'fee' => 500]`
  - `ProductFactory` defaults: unique `name`, `slug = Str::slug(name)`, `price` 50–5000, a 14-digit unique `sku`.

- [ ] **Step 1: Write the failing tests** in `SchemaTest.php`:
  - `it casts money columns to Money`: `Product::factory()->create(['price' => 374])->fresh()->price` is a `Money` equal to `fromCents(374)`. Saving `price = Money::fromCents(99)` persists `99`.
  - `it allows one rating per user per product`: a second `Rating::create` with the same pair throws `QueryException`.
  - `it allows one cart line per product`: same check for `cart_items`.
  - `it keeps order item snapshots when a product is deleted`: after `$product->delete()`, `OrderItem` has `product_id` null and `product_name` unchanged.
  - `it picks the lowest sort_order image as primary`: images with sort orders 2 and 0 → `primaryImage->sort_order === 0`.
  - `it defaults new users to zero balance and non-admin`.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Domain/SchemaTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Write the migrations, enums, cast, models and factories.**

- [ ] **Step 4: Run the tests.**
  - Run the same command, then `$PHP artisan migrate:fresh` (it must succeed on MySQL too).
  - Expected: PASS.

- [ ] **Step 5: Commit:** `Add store schema, models, enums and factories`

---

### Task 6: Wallet, signup bonus and profile fields

**Files:**
- Create: `app/Wallet/Wallet.php`, `app/Exceptions/InsufficientBalance.php`, `app/Listeners/GrantSignupBonus.php`
- Modify:
  - `database/factories/UserFactory.php` (add the `withBalance` state)
  - the kit's registration handler (`RegisteredUserController::store` or Fortify's `app/Actions/Fortify/CreateNewUser.php`, whichever the kit uses) and its profile-update handler: add `phone` and `address` (`nullable|string|max:100` and `nullable|string|max:200`)
  - `resources/js/pages/auth/RegisterPage.vue` and `resources/js/pages/settings/ProfilePage.vue` (two optional inputs)
- Test: `tests/Feature/Domain/WalletTest.php`, `tests/Feature/Http/RegistrationTest.php`

**Interfaces:**
- Consumes: `Money`, `User`, `WalletTransaction`, `WalletTransactionType`, `Order` (Task 5).
- Produces:
  - `App\Wallet\Wallet`, resolved from the container:
    - `credit(User $user, Money $amount, WalletTransactionType $type, ?string $description = null): WalletTransaction`
    - `debit(User $user, Money $amount, WalletTransactionType $type, ?string $description = null, ?Order $order = null): WalletTransaction`
    - Both throw `InvalidArgumentException` if `$amount` ≤ 0.
    - `debit` throws `App\Exceptions\InsufficientBalance` (extends `RuntimeException`) when the balance would go negative.
    - Both refresh the passed `$user` model's `balance`.
  - `UserFactory::withBalance(Money $amount)`: an `afterCreating` hook that calls `Wallet::credit($user, $amount, SignupBonus)`, so the invariant holds in tests.
  - `GrantSignupBonus::handle(Registered $event): void` credits `Money::fromCents(10000)` with type `SignupBonus` and description `Welcome to Cartopia`.

- [ ] **Step 1: Write the failing tests.**

  `WalletTest.php`:
  - `it credits and records balance_after`: credit 2550 to a 0 balance → `balance` 2550, one row with `amount` 2550 and `balance_after` 2550.
  - `it debits and links the order`: start from 10000, debit 1274 for an `$order` → balance 8726, row `amount` −1274, `order_id` = `$order->id`.
  - `it refuses to overdraw and changes nothing`: start from 100, debit 101 → `InsufficientBalance`. Balance is still 100 and the ledger count is unchanged.
  - `it rejects non-positive amounts`: credit 0 → `InvalidArgumentException`.
  - `it keeps balance equal to the ledger sum`:
    1. `mt_srand(42)`, then run 50 random credits and debits (1–5000 cents, catching `InsufficientBalance`).
    2. Assert `$user->fresh()->balance->cents() === (int) $user->walletTransactions()->sum('amount')`.
    3. Assert it is ≥ 0 after every step.

  `RegistrationTest.php`:
  - `it grants exactly one signup bonus`: post the kit's register route with name, email, password, confirmation, `phone` `01000000000` and `address` `Alexandria` → balance `$100.00`, exactly 1 row of type `signup_bonus`, phone and address persisted.
  - `it registers without phone and address`.
  - `it updates phone and address from profile settings`.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Domain/WalletTest.php tests/Feature/Http/RegistrationTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement.**
  - `Wallet` methods run in `DB::transaction`, re-read the user with `User::whereKey($id)->lockForUpdate()->firstOrFail()`, compute the new balance with `Money`, then create the row and save the user.
  - Rely on Laravel's listener auto-discovery for `GrantSignupBonus`. Don't register it manually as well; the "exactly one" test catches double registration.

- [ ] **Step 4: Run the tests.** Same command; expected: PASS.

- [ ] **Step 5: Commit:** `Add wallet ledger, signup bonus and profile contact fields`

---

### Task 7: Catalog query scopes and database seeder

**Files:**
- Modify: `app/Models/Product.php` (scopes)
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/Domain/ProductScopesTest.php`, `tests/Feature/Domain/DatabaseSeederTest.php`

**Interfaces:**
- Produces local scopes on `Product`, usable as `Product::query()->search($q)` and so on:
  - `search(?string $term)`: no-op when null or blank. Otherwise matches `name` or `description` with `LIKE ? ESCAPE '!'`. Escape the term's `!`, `%` and `_` with a `!` prefix and wrap it in `%…%`. Using `!` gives identical behavior on SQLite and MySQL.
  - `priceBetween(?int $minCents, ?int $maxCents)`: inclusive, and each bound is optional.
  - `minRating(?int $stars)`: `whereRaw('(select avg(stars) from ratings where ratings.product_id = products.id) >= ?', [$stars])`. Unrated products are excluded.
  - `sortBy(?string $sort)`: `newest` (`latest('id')`), `price_asc`, `price_desc`, `rating` (by the avg-stars subquery, descending, nulls last). Null or unknown values fall back to `newest`.
  - `withRatingStats()`: `withAvg('ratings', 'stars')->withCount('ratings')`, which yields `ratings_avg_stars` and `ratings_count`.
  - `Product::SORTS = ['newest', 'price_asc', 'price_desc', 'rating']`
- **Seeder** (§9). Products, in this id order:

  | Name | Slug | Price (cents) | SKU | Image |
  |---|---|---|---|---|
  | Apple | `apple` | 30 | `63310304118428` | `apple.jpg` |
  | Beer | `beer` | 200 | `73310304118423` | `heineken_beer.jpg` |
  | Water | `water` | 100 | `93312304138923` | `water_bottle.jpg` |
  | Cheese Triangle | `cheese-triangle` | 374 | `47729135118723` | `cheese-traingles.jpg` |

  - Descriptions are copied from the legacy `cartopia.sql` (available via `git show master:cartopia.sql`).
  - Images are copied from `database/seeders/images/` to the `public` disk at `products/<file>`. `ProductImage.path` = `products/<file>`, `sort_order` 0.
  - Shipping methods: `pickup()` and `ups()`.
  - Demo user: name `amrcs92`, email `amrcs1992@gmail.com`, password `12345`. Credited $100.00 `SignupBonus` through `Wallet`. Don't fire `Registered`.
  - Admin: name `Admin`, email `admin@cartopia.test`, password `password`, `is_admin` true, balance 0.

- [ ] **Step 1: Write the failing tests.**

  `ProductScopesTest.php`:
  - `it searches name and description case-insensitively` (`APP` finds Apple, and a description word finds its product).
  - `it treats LIKE wildcards in the search term literally`: products `100% Juice` and `Apple`. `search('%')` → only `100% Juice`. `search('_')` → none.
  - `it filters by inclusive price range` (100 and 300 bounds, with prices 99/100/300/301).
  - `it filters by minimum average rating and excludes unrated` (an avg of 3.5 is excluded by `minRating(4)`).
  - `it sorts by each option and defaults to newest`: a dataset over `Product::SORTS` plus `'bogus'`.
  - `it exposes rating stats` (`ratings_avg_stars` 4.5 and `ratings_count` 2).

  `DatabaseSeederTest.php`:
  - `it seeds the legacy catalog, shipping, demo and admin users`:
    - `Storage::fake('public')`, then `$this->seed()`.
    - Assert the 4 slugs and prices above.
    - Each image file exists on the fake disk.
    - 2 shipping methods.
    - `Hash::check('12345', $demo->password)`, demo balance 10000 with exactly 1 `signup_bonus` row.
    - Admin `is_admin` true.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Domain/ProductScopesTest.php tests/Feature/Domain/DatabaseSeederTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement the scopes and the seeder.**

- [ ] **Step 4: Run the tests.**
  - Run the same command, then `$PHP artisan migrate:fresh --seed && $PHP artisan storage:link`.
  - Expected: PASS, and MySQL is seeded.

- [ ] **Step 5: Commit:** `Add catalog query scopes and seed legacy catalog`

---

### Task 8: Cart domain and guest-cart merge

**Files:**
- Create: `app/Cart/{CartStore,SessionCartStore,DatabaseCartStore,Cart,CartSummary,CartLine}.php`, `app/Listeners/MergeGuestCart.php`
- Modify: `app/Providers/AppServiceProvider.php` (binding)
- Test: `tests/Feature/Domain/CartTest.php`, `tests/Feature/Domain/MergeGuestCartTest.php`

**Interfaces:**
- Consumes: `Product`, `User`, `App\Models\Cart`, `CartItem`, `Money` (Tasks 4–5).
- Produces:
  - **`interface CartStore`:**
    - `lines(): array` (an `array<int, int>` of productId => quantity)
    - `set(int $productId, int $quantity): void`
    - `remove(int $productId): void`
    - `clear(): void`
  - **`SessionCartStore`:** `__construct(Illuminate\Contracts\Session\Session $session)`, session key `cart`.
  - **`DatabaseCartStore`:** `__construct(User $user)`. It lazily `firstOrCreate`s the user's cart on the first write, and `lines()` doesn't create one.
  - **`App\Cart\Cart`:**
    - `__construct(CartStore $store)` and `const MAX_QUANTITY = 99`
    - `static forUser(User $user): self`
    - `add(Product $product, int $quantity): void`: sums with the existing quantity, capped at 99
    - `update(Product $product, int $quantity): void`: sets `min($quantity, 99)`. Throws `InvalidArgumentException` if < 1.
    - `remove(Product $product): void`
    - `has(Product $product): bool`
    - `clear(): void`
    - `count(): int` (equals `summary()->totalQuantity()`)
    - `summary(): CartSummary`
  - **`final readonly class CartLine`:** `Product $product`, `int $quantity`, `Money $unitPrice`, `Money $lineTotal`
  - **`final readonly class CartSummary`:** `array $lines` (`list<CartLine>`), `Money $subtotal`, `isEmpty(): bool`, `totalQuantity(): int`
  - **`summary()`** loads every line's product in one query, `Product::with('primaryImage')->whereKey(array_keys($lines))->get()`, and ignores ids that weren't found.
  - **Container binding** in `AppServiceProvider::register`: `CartStore` → a closure returning `DatabaseCartStore(auth()->user())` when logged in, otherwise `SessionCartStore(app('session.store'))`.
  - **`MergeGuestCart::handle(Login $event): void`:** reads `new SessionCartStore(session())`, calls `Cart::forUser($event->user)->add(...)` for each line, then clears the session store.

- [ ] **Step 1: Write the failing tests.**

  `CartTest.php` runs each test against both stores, using the dataset `['session' => fn () => new SessionCartStore(app('session.store')), 'database' => fn () => new DatabaseCartStore(User::factory()->create())]`:
  - `it adds and accumulates quantity`: add 2 + 3 → 5.
  - `it caps quantity at 99` (add 98 + 5 → 99, `update(150)` → 99).
  - `it rejects updating to less than one`.
  - `it removes and clears lines`.
  - `it prices lines from the current database price`: add Beer (200) ×2, then change the price to 250 → subtotal `$5.00`.
  - `it drops products that no longer exist`: add Apple and Beer, delete Beer. Summary has 1 line, `count()` = Apple's quantity, and nothing throws.
  - `it counts total quantity across lines`.

  `MergeGuestCartTest.php`:
  - `it merges guest lines into the user cart on login`: the session has Apple×3 and Beer×98, the DB cart has Beer×5 → after `event(new Login('web', $user, false))` the DB cart is Apple 3 and Beer 99, and the session `cart` key is empty.
  - `it creates a database cart when the user had none`.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Domain/CartTest.php tests/Feature/Domain/MergeGuestCartTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run the tests.** Same command; expected: PASS.

- [ ] **Step 5: Commit:** `Add cart domain with session and database stores`

---

### Task 9: Catalog pages and shared Inertia props

**Files:**
- Create:
  - `app/Http/Controllers/{HomeController,ProductController}.php`
  - `app/Http/Requests/ProductIndexRequest.php`
  - `app/Http/Resources/{ProductResource,ProductDetailResource}.php`
- Modify:
  - `app/Http/Middleware/HandleInertiaRequests.php`
  - `routes/web.php`
  - the kit's post-auth redirect (wherever the kit defines `dashboard` as home: the auth controllers, `config/fortify.php` `home`, or `bootstrap/app.php`) → `route('products.index')`
- Delete: the kit `dashboard` route, `pages/DashboardPage.vue` and its nav item
- Create (front end):
  - `resources/js/types/{index.d.ts or shared.ts}`, adding `SharedProps`, `Product`, `ProductDetail`
  - `resources/js/pages/layouts/StoreLayout.vue`
  - `resources/js/pages/WelcomePage.vue` (replacing the kit's Welcome)
  - `resources/js/pages/products/{ProductsIndexPage,ProductShowPage}.vue`
  - `resources/js/modules/products/{ProductCard,StarRating,ProductFilters}.vue`
  - `resources/js/modules/auth/useCurrentUser.ts`
  - `common/` wrappers as first needed (for example `common/button/Button.vue`, `common/card/Card.vue`, `common/input/Input.vue`, `common/select/Select.vue`, `common/pagination/Pagination.vue`, `common/flash-toast/FlashToast.vue`)
- Test: `tests/Feature/Http/CatalogTest.php`, `tests/Feature/Http/SharedPropsTest.php`

**Interfaces:**
- Consumes: the Product scopes (Task 7) and `App\Cart\Cart` (Task 8).
- Produces:
  - **Routes:** `GET /` → `home`, `GET /products` → `products.index`, `GET /products/{product}` → `products.show` (route key is the slug).
  - **`ProductIndexRequest`:**
    - Rules: `q` `nullable|string|max:100`; `min_price` and `max_price` `nullable|decimal:0,2|min:0`, with `max_price` also `gte:min_price`; `rating` `nullable|integer|between:1,5`; `sort` `nullable|in:` + `Product::SORTS`.
    - `filters(): array{q: ?string, min_price: ?int, max_price: ?int, rating: ?int, sort: string}`, with prices converted to cents via `Money::fromDollars` and `sort` defaulting to `newest`.
  - **`ProductResource`:** `{ id, name, slug, price: string (formatted), image_url: ?string (Storage::disk('public')->url(path)), rating_average: float (round(avg, 1), 0 when null), rating_count: int }`
  - **`ProductDetailResource`:** extends the above with `{ description, images: string[] (URLs in sort order), my_rating: ?int }`
  - **Page props:**
    - `WelcomePage`: `{ featured: ProductResource[] }` (the 4 newest)
    - `products/ProductsIndexPage`: `{ products: paginated ProductResource collection (12, withQueryString), filters: { q, min_price, max_price, rating, sort } (raw request values for repopulating inputs) }`
    - `products/ProductShowPage`: `{ product: ProductDetailResource }`
  - **Shared props:** `auth.user: { id, name, email, balance: string, is_admin: bool } | null`, `cart: { count: int }` (lazy closure), `flash: { success: ?string, error: ?string }` from `session('success')` and `session('error')`.
  - **`StoreLayout`:**
    - Navbar: the logo (`/images/cartopia_logo.png`) linking to `home`, a search box that submits `q` to `products.index`, a cart link with a badge showing `cart.count`, the balance when logged in, a user menu (Orders, Wallet, Settings, Logout) and Login/Register links for guests.
    - A footer.
    - `FlashToast` showing `flash`.
  - **`ProductsIndexPage`:**
    - `ProductFilters` changes call `router.get(route('products.index'), params, { preserveState: true, preserveScroll: true, only: ['products', 'filters'] })`.
    - Typing in `q` is debounced by 300 ms.

- [ ] **Step 1: Write the failing tests.**

  `CatalogTest.php` uses `assertInertia`:
  - `it shows the four newest products on the welcome page`.
  - `it paginates products twelve per page and keeps the query string`: 13 products → page 1 `products.data` has 12, page 2 (`?q=&page=2`) has 1, and `products.links` URLs contain `sort=price_asc` when it was requested.
  - `it applies filters and echoes them back` (`q`, `min_price=1.00`, `max_price=3.00`, `rating=4`, `sort=price_desc`).
  - `it rejects invalid filters` (`sort=bogus`, `max_price=1&min_price=2`, `rating=6`) → `assertSessionHasErrors`.
  - `it shows a product by slug with images and the viewer's rating`: `my_rating` is null for a guest and 4 for the user who rated 4.
  - `it returns 404 for an unknown slug`.
  - `it never exposes internal columns` (`missing('products.data.0.sku')`, `missing('product.created_at')`).

  `SharedPropsTest.php`:
  - `it shares null user and zero cart for guests`.
  - `it shares the formatted balance for users` (`User::factory()->withBalance(Money::fromCents(10000))` → `auth.user.balance` `$100.00`).
  - `it shares flash messages`.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Http/CatalogTest.php tests/Feature/Http/SharedPropsTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement the routes, request, resources, controllers, middleware props and Vue pages.**

- [ ] **Step 4: Run the tests and the front-end checks.**
  - Run: the same command, then `npm run types && npm run lint && npm run build`.
  - Expected: PASS.
  - Manually: `http://cartopia.test/products` lists the 4 seeded products with images, and the filters update the URL.

- [ ] **Step 5: Commit:** `Add catalog pages with search, filters and shared props`

---

### Task 10: Cart endpoints and cart page

**Files:**
- Create:
  - `app/Http/Controllers/{CartController,CartItemController}.php`
  - `app/Http/Requests/{AddToCartRequest,UpdateCartItemRequest}.php`
  - `app/Http/Resources/{CartResource,ShippingMethodResource}.php`
  - `resources/js/pages/cart/CartPage.vue`
  - `resources/js/modules/cart/{CartLineItem,QuantityStepper,CartSummary}.vue`
- Modify: `routes/web.php`, `ProductCard.vue` (add to cart, qty 1), `ProductShowPage.vue` (quantity stepper + add to cart)
- Test: `tests/Feature/Http/CartTest.php`

**Interfaces:**
- Consumes: `App\Cart\Cart` (Task 8) and `ProductResource` (Task 9).
- Produces:
  - **Routes:** `GET /cart` → `cart.show`, `POST /cart/items` → `cart.items.store`, `PATCH /cart/items/{product}` → `cart.items.update`, `DELETE /cart/items/{product}` → `cart.items.destroy`. These bind by **id**, so use `{product:id}`.
  - **`AddToCartRequest`:** `product_id` `required|integer|exists:products,id`, `quantity` `required|integer|between:1,99`.
  - **`UpdateCartItemRequest`:** `quantity` `required|integer|between:1,99`.
  - **Update and remove** return 404 when `!$cart->has($product)`. Store, update and destroy all `redirect()->back()`. Store flashes `success` `Added to cart.`.
  - **`CartResource`** (wraps `CartSummary`): `{ lines: [{ product: ProductResource, quantity: int, unit_price: string, line_total: string }], subtotal: string, total_quantity: int }`
  - **`ShippingMethodResource`:** `{ id, name, fee: string }`
  - **`cart/CartPage` props:** `{ cart: CartResource, shippingMethods: ShippingMethodResource[] }`
  - **Front-end cart actions** use `router.post`, `router.patch` and `router.delete` with `preserveScroll: true, only: ['cart', 'flash']`. The quantity stepper is clamped to 1–99 in the UI as well.

- [ ] **Step 1: Write the failing tests** in `CartTest.php`:
  - `it adds to a guest session cart`: post Beer ×2 → redirect back, `session('cart')` is `[beerId => 2]`, and the shared `cart.count` is 2 on the next `GET /cart`.
  - `it adds to a user database cart`.
  - `it validates cart input`: a dataset of quantity `0`, `100` and `'x'`, plus a missing product id `999` → errors.
  - `it updates and removes lines` and `it returns 404 when updating a line not in the cart`.
  - `it renders server-computed totals`: Beer ×2 + Water ×2 → `cart.subtotal` `$6.00`, `cart.lines` has 2, `shippingMethods` has 2.
  - `it merges the guest cart on login`: add as a guest, post the kit login route → the DB cart has the line.
  - `it keeps the guest cart when a guest registers`: add as a guest, post the kit register route → the new user's DB cart has the line. The kit's `Auth::login` after registration fires `Login`; if the kit doesn't, add the merge to `Registered` too.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Http/CartTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run the tests and the front-end checks.**
  - Run: the same command, then `npm run types && npm run lint && npm run build`.
  - Expected: PASS.
  - Manually: add to cart from the grid and the detail page, and the badge updates without a full reload.

- [ ] **Step 5: Commit:** `Add cart endpoints and cart page`

---

### Task 11: Checkout and order history

**Files:**
- Create:
  - `app/Actions/PlaceOrder.php`, `app/Exceptions/EmptyCart.php`
  - `app/Http/Controllers/{CheckoutController,OrderController}.php`
  - `app/Http/Requests/CheckoutRequest.php`
  - `app/Http/Resources/{OrderResource,OrderItemResource}.php`
  - `app/Policies/OrderPolicy.php`
  - `resources/js/pages/orders/{OrdersIndexPage,OrderShowPage}.vue`
- Modify: `routes/web.php`, `CartPage.vue` (shipping select + checkout `useForm` with a confirm dialog; shows `flash.error`)
- Test: `tests/Feature/Domain/PlaceOrderTest.php`, `tests/Feature/Http/CheckoutTest.php`, `tests/Feature/Http/OrdersTest.php`

**Interfaces:**
- Consumes: `Cart::forUser`, `CartSummary` (Task 8), `Wallet::debit`, `InsufficientBalance` (Task 6).
- Produces:
  - **`PlaceOrder::handle(User $user, ShippingMethod $method): Order`.** Inside `DB::transaction`:
    1. get the summary (`EmptyCart` if empty)
    2. compute `shipping_fee = $method->fee` and `total = subtotal + fee`
    3. create the `Order` (status `Placed`) and the `OrderItem` snapshots (`product_name`, `unit_price`, `quantity`, `line_total`)
    4. call `Wallet::debit($user, $total, Purchase, "Order #{$order->id}", $order)`
    5. clear the cart

    It returns the order with `items` loaded.
  - **`EmptyCart`** extends `RuntimeException`.
  - **`CheckoutRequest`:** `shipping_method_id` `required|integer|exists:shipping_methods,id`. It must declare no price or total fields.
  - **`CheckoutController@store`:**
    - Success: redirect to `orders.show` with `success` `Order placed.`.
    - On `InsufficientBalance`: redirect to `cart.show` with `error` `Your balance is too low for this order.`.
    - On `EmptyCart`: redirect to `cart.show` with `error` `Your cart is empty.`.
  - **Routes:** `POST /checkout` → `checkout.store` (`auth`, `throttle:10,1`); `GET /orders` → `orders.index`; `GET /orders/{order}` → `orders.show` (`auth`, `can:view,order`).
  - **`OrderPolicy::view(User $user, Order $order): bool`:** true when the order is the user's.
  - **`OrderResource`:** `{ id, status: string, subtotal, shipping_fee, total: string, shipping_method: string (name), placed_at: string (ISO 8601), items: OrderItemResource[] }`
  - **`OrderItemResource`:** `{ product_name, product_slug: ?string, unit_price, quantity: int, line_total }`
  - **Pages:** `orders/OrdersIndexPage` gets `{ orders: paginated OrderResource (10, newest first) }`; `orders/OrderShowPage` gets `{ order: OrderResource }`.
  - **CartPage:** shows `flash.error` above the checkout button. Task 13 adds the link to the wallet.

- [ ] **Step 1: Write the failing tests.**

  `PlaceOrderTest.php` uses a user with `withBalance(Money::fromCents(10000))`, Beer at 200 ×2, Cheese at 374 ×1, and `ups()`:
  - `it places an order priced from the database`: subtotal 774, fee 500, total 1274; balance 8726; one `purchase` ledger row of −1274 linked to the order; 2 items with names and prices snapshotted; the cart is empty.
  - `it charges the current price when the price changed after adding`: add Beer, set the Beer price to 300 → the order subtotal uses 300.
  - `it refuses an empty cart` → `EmptyCart`, no orders.
  - `it rolls everything back when the balance is too low`: balance 1000, total 1274 → `InsufficientBalance`. Order and item counts are 0, the cart still has its lines, and the balance is still 1000.

  `CheckoutTest.php`:
  - `it checks out and redirects to the order`: flash `Order placed.`.
  - `it ignores client-sent prices`: post `shipping_method_id` plus `price=1`, `total=1` and `total_price=0.01` → order total 1274.
  - `it flashes an error on insufficient balance` → redirect `cart.show`, error `Your balance is too low for this order.`.
  - `it redirects guests to login`.
  - `it places only one order on double submit`: two posts in a row → 1 order, and the second redirects to `cart.show` with `Your cart is empty.`.
  - `it throttles checkout`: the 11th post within a minute → 429.
  - `it validates the shipping method` (id 999).

  `OrdersTest.php`:
  - `it lists only the user's orders, newest first`.
  - `it shows the user's own order with items`.
  - `it forbids viewing another user's order` → 403.
  - `it redirects guests to login`.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Domain/PlaceOrderTest.php tests/Feature/Http/CheckoutTest.php tests/Feature/Http/OrdersTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run the tests and the front-end checks.** Same command plus `npm run types && npm run lint && npm run build`; expected: PASS.

- [ ] **Step 5: Commit:** `Add balance checkout and order history`

---

### Task 12: Product ratings

**Files:**
- Create: `app/Actions/RateProduct.php`, `app/Http/Controllers/RatingController.php`, `app/Http/Requests/RateProductRequest.php`, `resources/js/modules/products/RatingInput.vue`
- Modify: `routes/web.php`, `ProductShowPage.vue`
- Test: `tests/Feature/Domain/RateProductTest.php`, `tests/Feature/Http/RatingTest.php`

**Interfaces:**
- Consumes: `Rating` (Task 5) and `ProductDetailResource.my_rating` (Task 9).
- Produces:
  - **`RateProduct::handle(User $user, Product $product, int $stars): Rating`:** `updateOrCreate(['user_id', 'product_id'], ['stars'])`.
  - **Route:** `PUT /products/{product}/rating` → `products.rating.update` (`auth`, slug binding).
  - **`RateProductRequest`:** `stars` `required|integer|between:1,5`.
  - **Response:** redirect back with `success` `Thanks for rating!`.
  - **`RatingInput`:** a 5-star control showing `my_rating`. Clicking a star calls `router.put(..., { stars }, { preserveScroll: true, only: ['product', 'flash'] })`. Guests see a "Log in to rate" link instead.

- [ ] **Step 1: Write the failing tests.**

  `RateProductTest.php`:
  - `it creates then updates a single rating`: rate 3, then 5 → 1 row, stars 5.

  `RatingTest.php`:
  - `it saves the viewer's rating and updates the average`: two users rate 4 and 5 → `product.rating_average` 4.5 and `my_rating` matches.
  - `it validates stars`: a dataset of `0`, `6` and `'abc'`.
  - `it redirects guests to login`.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Domain/RateProductTest.php tests/Feature/Http/RatingTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run the tests and the front-end checks.** Same command plus the front-end checks; expected: PASS.

- [ ] **Step 5: Commit:** `Add product ratings`

---

### Task 13: Wallet page and top-up

**Files:**
- Create:
  - `app/Actions/TopUpWallet.php`
  - `app/Http/Controllers/{WalletController,TopUpController}.php`
  - `app/Http/Requests/TopUpRequest.php`
  - `app/Http/Resources/WalletTransactionResource.php`
  - `resources/js/pages/wallet/WalletPage.vue`
  - `resources/js/modules/wallet/{TopUpForm,LedgerTable}.vue`
- Modify: `routes/web.php`, `StoreLayout.vue` (Wallet link in the user menu), `CartPage.vue` (when `flash.error` is `Your balance is too low for this order.`, show a "Top up your balance" link to `wallet.show`)
- Test: `tests/Feature/Http/WalletTest.php`

**Interfaces:**
- Consumes: `Wallet::credit` (Task 6).
- Produces:
  - **`TopUpWallet`:** `const MIN_CENTS = 500`, `const MAX_CENTS = 50000`. `handle(User $user, Money $amount): WalletTransaction` throws `InvalidArgumentException` outside those limits, then calls `Wallet::credit(..., TopUp, 'Wallet top-up')`.
  - **`TopUpRequest`:** `amount` `required|decimal:0,2|min:5|max:500`, plus `amount(): Money` (via `fromDollars`).
  - **Routes:** `GET /wallet` → `wallet.show`; `POST /wallet/top-ups` → `wallet.top-ups.store` (`auth`, `throttle:10,1`). The top-up redirects to `wallet.show` with `success` `Added {formatted} to your balance.`.
  - **`WalletTransactionResource`:** `{ id, type: string (value), type_label: string, amount: string (a `+` prefix when positive, so `+$25.50` or `-$12.74`), balance_after: string, description: ?string, created_at: string (ISO 8601) }`
  - **`wallet/WalletPage` props:** `{ balance: string, transactions: paginated WalletTransactionResource (15, newest first) }`

- [ ] **Step 1: Write the failing tests** in `WalletTest.php`:
  - `it tops up and records the ledger`: post `amount=25.50` → balance +2550, a `top_up` row, flash `Added $25.50 to your balance.`.
  - `it enforces top-up limits`: a rejected dataset of `4.99`, `500.01`, `5.001`, `abc` and `''`, plus an accepted dataset of `5` and `500`.
  - `it throttles top-ups`: the 11th post within a minute → 429.
  - `it shows the ledger newest first with signed amounts`: a signup bonus followed by an order → first row `-$12.74`, last row `+$100.00`, paginated 15 per page.
  - `it redirects guests to login`.

- [ ] **Step 2: Run the tests.**
  - Run: `$PHP vendor/bin/pest tests/Feature/Http/WalletTest.php`
  - Expected: FAIL.

- [ ] **Step 3: Implement.**

- [ ] **Step 4: Run the tests and the front-end checks.** Same command plus the front-end checks; expected: PASS.

- [ ] **Step 5: Commit:** `Add wallet page and balance top-up`

---

### Task 14: Browser smoke tests

**Files:**
- Create: `tests/Browser/CheckoutFlowTest.php`, `tests/Browser/TopUpTest.php`
- Modify: `composer.json`, `package.json`, `.github/workflows/ci.yml` (Playwright install before `vendor/bin/pest`)

**Interfaces:**
- Consumes: every page, using the fixed copy from Global Constraints for assertions.

- [ ] **Step 1: Install the browser tooling.**
  ```bash
  $COMPOSER require pestphp/pest-plugin-browser --dev
  npm install -D playwright
  npx playwright install chromium
  ```

- [ ] **Step 2: Write the tests.** Each uses factories to set up an existing user with `withBalance(Money::fromCents(10000))`, products with images, and `pickup()`/`ups()`.
  - `it browses, carts, logs in, checks out and sees the order`: `visit('/products')` → click `Add to cart` on a product → visit `/cart` → follow login → log in → back on `/cart`, pick `UPS`, confirm checkout → `assertSee('Order placed.')` and the order total is visible.
  - `it tops up the wallet`: log in → `/wallet` → fill `amount` `20` → submit → `assertSee('Added $20.00 to your balance.')`.

- [ ] **Step 3: Run the tests.**
  - Run: `npm run build && $PHP vendor/bin/pest tests/Browser`
  - Expected: PASS.

- [ ] **Step 4: Add to CI.** Add `npx playwright install --with-deps chromium` to `ci.yml` after `npm ci`.

- [ ] **Step 5: Commit:** `Add browser smoke tests for checkout and top-up`

---

### Task 15: README and definition-of-done check

**Files:**
- Modify: `README.md`
- Create: `docs/screenshots/{products,product,cart,orders,wallet}.png`

- [ ] **Step 1: Run the full check.**
  - Run:
    ```bash
    $PHP vendor/bin/pint --test && $PHP vendor/bin/phpstan analyse && npm run lint && npm run types && npm run build && $PHP vendor/bin/pest
    ```
  - Expected: all green.

- [ ] **Step 2: Walk through the app on a fresh seed.**
  - Run `$PHP artisan migrate:fresh --seed`.
  - At `http://cartopia.test`, check every route in spec §5.1:
    - log in as `amrcs1992@gmail.com` / `12345`
    - buy something
    - rate a product
    - top up
    - view the order
    - log out
    - confirm a guest's cart merges on login
  - Take the 5 screenshots.

- [ ] **Step 3: Rewrite `README.md`.** It should cover:
  - what Cartopia is
  - the stack
  - a CI badge (`https://github.com/amrcs92/cartopia/actions/workflows/ci.yml/badge.svg`)
  - the screenshots
  - Laragon setup:
    1. clone into `C:\laragon\www`
    2. `composer install`
    3. `npm install`
    4. `.env` (DB `cartopia`)
    5. `php artisan key:generate`
    6. `migrate --seed`
    7. `storage:link`
    8. `npm run build`
    9. open `http://cartopia.test`
  - the demo login (`amrcs1992@gmail.com` / `12345`) and the admin login (`admin@cartopia.test` / `password`, used by the Filament panel in the next sub-project)
  - a "2018 → 2026 rewrite" section listing the legacy defects from spec §1 and how each is fixed

- [ ] **Step 4: Commit:** `Rewrite README for the Laravel version`
