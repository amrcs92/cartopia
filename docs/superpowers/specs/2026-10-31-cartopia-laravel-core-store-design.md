# Cartopia → Laravel: Core Store Design

- **Date:** 2026-10-31
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Sub-project:** 1 of 2 (Core store). Sub-project 2 (Filament admin panel) gets its own spec.

## 1. Goal and context

Cartopia is a 2018 plain-PHP shopping cart (PDO, jQuery, Bootstrap 4, a localStorage cart) with a virtual balance used to pay at checkout. It is being rebuilt in Laravel as a **portfolio showcase**: modern Laravel idioms, a current front-end stack, real tests and CI.

The rewrite also fixes defects in the legacy app:

- Checkout trusted client-side prices and totals.
- Balance updates had no transaction and no lock.
- Orders were not linked to users.
- All passwords used SHA-512 with one shared fixed salt.
- Output was never escaped (XSS) and there was no CSRF protection.
- Ratings were unvalidated and could be duplicated.
- There were N+1 queries on every listing page.

### Scope of this sub-project

**Ported from the legacy app:** product catalog, product detail page, cart, checkout paid from balance, star ratings, login and registration.

**Added in v1:**
- Order history
- Search, filters, sorting and pagination
- Balance top-up with a wallet transaction ledger
- `is_admin` flag and the Filament panel gate (the panel itself is sub-project 2)

### Out of scope

- The Filament admin panel UI (sub-project 2)
- Real payment gateways, since top-up is virtual credit
- Email verification (off by default; a one-line change to enable)
- Order cancellation and refunds
- Scout or full-text search
- Migrating legacy users or passwords (we reseed; see §9)

## 2. Stack and environment

| Concern | Choice |
|---|---|
| Framework | Laravel 13 |
| Runtime | Laragon: PHP 8.3.36, MySQL 8.4.3, Node 22 |
| Front end | Official Laravel **Vue starter kit**: Inertia, Vue 3, TypeScript, Tailwind 4, shadcn-vue |
| Auth | The starter kit's built-in auth (login, register, password reset, profile settings) |
| Admin | Filament, using its current Laravel 13-compatible release (sub-project 2) |
| Tests | Pest, including Pest browser tests |
| Quality | Pint, Larastan level 6, ESLint, Prettier, vue-tsc |
| CI | GitHub Actions |

- **Location:** this repo, cloned into `C:\laragon\www\cartopia` and served as `http://cartopia.test`.
- **Branch:** `laravel`, which replaces the legacy code. The legacy code stays in git history on `master` and `main`.

## 3. Data model

### 3.1 Money

All monetary values are stored as **unsigned integer cents**. `App\Support\Money` is an immutable value object with these methods:

- `fromCents`
- `add`
- `subtract`
- `multiply(int)`
- `isLessThan`
- `isNegative`
- `cents()`
- `format()`, returning a string such as `$4.00`

`App\Casts\MoneyCast` maps integer columns to `Money`. No float arithmetic on money anywhere, and none in the front end.

### 3.2 Tables

| Table | Columns | Constraints and notes |
|---|---|---|
| `users` | starter-kit columns + `phone` (nullable), `address` (nullable), `balance` (uint cents, default 0), `is_admin` (bool, default false) | `email` unique. Legacy `username` maps to `name`. |
| `products` | `name`, `slug`, `description` (text), `price` (uint cents), `sku`, timestamps | `slug` unique, `sku` unique, index on `name` |
| `product_images` | `product_id`, `path`, `sort_order` (uint, default 0), timestamps | FK cascade on delete. The lowest `sort_order` is the primary image. |
| `ratings` | `user_id`, `product_id`, `stars` (tinyint 1–5), timestamps | FKs cascade. **Unique (`user_id`, `product_id`)**. |
| `shipping_methods` | `name`, `fee` (uint cents), timestamps | Seeded: Pickup $0.00, UPS $5.00 |
| `carts` | `user_id`, timestamps | `user_id` unique, FK cascade. Logged-in users only. |
| `cart_items` | `cart_id`, `product_id`, `quantity` (uint 1–99), timestamps | Unique (`cart_id`, `product_id`). FKs cascade. **No stored price.** |
| `orders` | `user_id`, `shipping_method_id`, `subtotal`, `shipping_fee`, `total` (uint cents), `status`, timestamps | `status` is backed by the `OrderStatus` enum; v1 has only `placed`. FK to user cascades. FK to shipping method restricts deletion. |
| `order_items` | `order_id`, `product_id` (nullable), `product_name`, `unit_price`, `quantity`, `line_total` | FK to order cascades. FK to product is set to null when the product is deleted. Name and price are snapshotted at purchase time. |
| `wallet_transactions` | `user_id`, `type`, `amount` (**signed** int cents: + credit / − debit), `balance_after` (uint cents), `order_id` (nullable), `description` (nullable), `created_at` | `type` is the `WalletTransactionType` enum: `signup_bonus`, `top_up`, `purchase`, `admin_adjustment`. Append-only: no `updated_at`, never updated or deleted. Index on (`user_id`, `created_at`). |

### 3.3 Relationships

- `User`: hasOne `Cart`; hasMany `Order`, `Rating`, `WalletTransaction`
- `Product`: hasMany `ProductImage` (ordered by `sort_order`), `Rating`, `CartItem`; hasOne `primaryImage` (oldestOfMany by `sort_order`)
- `Cart`: belongsTo `User`; hasMany `CartItem`
- `CartItem`: belongsTo `Cart`, `Product`
- `Order`: belongsTo `User`, `ShippingMethod`; hasMany `OrderItem`
- `OrderItem`: belongsTo `Order`, `Product` (nullable)
- `WalletTransaction`: belongsTo `User`, `Order` (nullable)

### 3.4 Balance invariant

`users.balance` is a cached running total. **Every** change to it goes through `Wallet` (§4.2), which writes the `wallet_transactions` row and updates `users.balance` in the same DB transaction, with the user row locked (`lockForUpdate`).

The invariant is: `users.balance == SUM(wallet_transactions.amount)` for that user, and `balance >= 0`.

## 4. Domain layer

Business rules live in plain classes and are unit-tested without HTTP. Controllers only orchestrate.

### 4.1 Cart (`app/Cart/`)

- **`CartStore` interface:** `lines(): array<productId, quantity>`, `set(productId, qty)`, `remove(productId)`, `clear()`.
  - `SessionCartStore` is for guests and stores the data under the `cart` session key.
  - `DatabaseCartStore` is for logged-in users and uses `carts` and `cart_items`, creating the cart on first write.
- **`Cart` service** resolves the store from the auth state. It provides:
  - `add(Product, int qty)`, which adds to any existing quantity and caps the total at 99
  - `update(Product, int qty)`
  - `remove(Product)`
  - `clear()`
  - `count()`, the total quantity
  - `summary()`, which returns a `CartSummary`
- **`CartSummary`** holds line items (product, quantity, unit price, line total) and the subtotal. It is built from **current DB prices** with one eager-loaded query. Products that no longer exist are dropped silently.
- **`MergeGuestCart` listener** on `Illuminate\Auth\Events\Login`. It adds each session line into the user's DB cart (sum, capped at 99), then clears the session cart.

### 4.2 Wallet (`app/Wallet/Wallet.php`)

- `credit(User, Money, WalletTransactionType, ?string description): WalletTransaction`
- `debit(User, Money, WalletTransactionType, ?string description, ?Order): WalletTransaction`. Throws `InsufficientBalance` if the result would be negative, and leaves state unchanged when it does.
- Each operation:
  1. runs inside `DB::transaction`
  2. re-reads the user with `lockForUpdate`
  3. writes the ledger row with `balance_after`
  4. updates `users.balance`
- **`GrantSignupBonus` listener** on `Illuminate\Auth\Events\Registered` credits $100.00 as `signup_bonus`.

### 4.3 Actions (`app/Actions/`)

- **`PlaceOrder::handle(User, ShippingMethod): Order`.** Inside one `DB::transaction` it:
  1. builds the `CartSummary`, throwing `EmptyCart` if there are no lines
  2. computes subtotal + shipping fee = total
  3. creates the `Order` and its `OrderItem` snapshots
  4. calls `Wallet::debit(total, purchase, order)`
  5. clears the cart

  Any exception rolls back all of it, and the cart is preserved. **No price input is accepted from the request.**
- **`RateProduct::handle(User, Product, int stars): Rating`** uses `updateOrCreate` on (`user_id`, `product_id`). Any logged-in user may rate any product.
- **`TopUpWallet::handle(User, Money): WalletTransaction`** requires an amount between $5.00 and $500.00 (also enforced by the form request) and calls `Wallet::credit(top_up)`.

### 4.4 Catalog query scopes (`Product`)

- `search(?string)`: `LIKE` on `name` and `description`
- `priceBetween(?int minCents, ?int maxCents)`
- `minRating(?int stars)`: a correlated `where` subquery (`(select avg(stars) from ratings where product_id = products.id) >= ?`). It does **not** use `HAVING` on the `withAvg` alias, which would break pagination counts and behaves differently on SQLite and MySQL.
- `sortBy(?string)`: `newest` (the default), `price_asc`, `price_desc`, `rating`
- `withRatingStats()`: `withAvg('ratings', 'stars')` plus `withCount('ratings')`

The listing query is `Product::with('primaryImage')->withRatingStats()->search()->priceBetween()->minRating()->sortBy()->paginate(12)->withQueryString()`.

### 4.5 Domain exceptions

`App\Exceptions\InsufficientBalance` and `App\Exceptions\EmptyCart`. `CheckoutController` catches them and redirects back to `/cart` with `flash.error`. They never produce a 500.

## 5. HTTP layer

### 5.1 Routes

| Method & URL | Controller | Form request | Access |
|---|---|---|---|
| `GET /` | `HomeController` (featured products: the 4 newest) | — | public |
| `GET /products` | `ProductController@index` | `ProductIndexRequest`: `q` (string ≤100), `min_price` and `max_price` (dollars, `decimal:0,2`, ≥0, `max_price` ≥ `min_price`, converted to cents), `rating` (int 1–5), `sort` (in the list above) | public |
| `GET /products/{product:slug}` | `ProductController@show` | — | public |
| `GET /cart` | `CartController@show` | — | public |
| `POST /cart/items` | `CartItemController@store` | `AddToCartRequest`: `product_id` exists, `quantity` int 1–99 | public |
| `PATCH /cart/items/{product}` | `CartItemController@update` | `UpdateCartItemRequest`: `quantity` int 1–99 | public |
| `DELETE /cart/items/{product}` | `CartItemController@destroy` | — | public |
| `POST /checkout` | `CheckoutController@store` | `CheckoutRequest`: `shipping_method_id` exists | `auth`, `throttle:10,1` |
| `PUT /products/{product}/rating` | `RatingController@update` | `RateProductRequest`: `stars` int 1–5 | `auth` |
| `GET /orders` | `OrderController@index` (paginated) | — | `auth` |
| `GET /orders/{order}` | `OrderController@show` | — | `auth`, `OrderPolicy@view` (owner only) |
| `GET /wallet` | `WalletController@show` (balance + paginated ledger) | — | `auth` |
| `POST /wallet/top-ups` | `TopUpController@store` | `TopUpRequest`: `amount` in dollars, `decimal:0,2`, 5–500, converted to cents | `auth`, `throttle:10,1` |
| Starter-kit auth and settings routes | kit controllers | kit requests + `phone` and `address` (nullable, string ≤100 / ≤200) | — |

`User` implements `FilamentUser`, and `canAccessPanel()` returns `is_admin`. The panel itself comes in sub-project 2.

### 5.2 Shared Inertia props (`HandleInertiaRequests`)

- `auth.user`: `{ id, name, email, balance (formatted), is_admin }` or `null`
- `cart.count`: an int
- `flash`: `{ success?: string, error?: string }`

### 5.3 API resources

- `ProductResource`: id, name, slug, formatted price, primary image URL, average rating (1 decimal place; `0` when unrated), rating count
- `ProductDetailResource`: adds description, images, and the current user's own rating
- `CartResource`
- `OrderResource` and `OrderItemResource`
- `WalletTransactionResource`

All amounts reach the client pre-formatted, and no internal columns are exposed.

### 5.4 Flow outcomes

- **Checkout succeeds:** redirect to `orders.show` with `flash.success`.
- **Checkout fails** (`InsufficientBalance` or `EmptyCart`): redirect back to `/cart` with `flash.error`. The UI links to `/wallet`.
- **A guest posts to checkout:** the `auth` middleware sends them to login, then back to the intended `/cart`. The cart is merged on login.
- **Cart changes and ratings:** redirect back, and the client does a partial reload of the props that changed.
- **CSRF:** Laravel middleware and Inertia handle it automatically.

## 6. Front end (`resources/js/`)

### 6.1 Structure

```
lib/shadcn/          generated shadcn-vue components (components.json aliases point here)
common/              project wrappers: Button, Card, Input, Select, Pagination, FlashToast, …
modules/
  products/          ProductCard.vue, StarRating.vue, RatingInput.vue, ProductFilters.vue
  cart/              CartLineItem.vue, QuantityStepper.vue, CartSummary.vue
  wallet/            TopUpForm.vue, LedgerTable.vue
  auth/              useCurrentUser.ts
pages/
  layouts/StoreLayout.vue
  WelcomePage.vue
  products/ProductsIndexPage.vue, ProductShowPage.vue
  cart/CartPage.vue
  orders/OrdersIndexPage.vue, OrderShowPage.vue
  wallet/WalletPage.vue
  auth/…, settings/…   starter-kit pages, renamed with the *Page suffix at setup
types/               SharedProps, Product, ProductDetail, CartLine, Order, WalletTransaction
```

**Conventions:**
- `<script setup lang="ts">` in every component
- one component per file
- PascalCase component files and camelCase composables
- kebab-case directories
- no barrel files
- generated code in `lib/` is never edited directly; it is wrapped in `common/`

### 6.2 Pages

- **StoreLayout:** a navbar with search, a cart badge (`cart.count`), the user's balance and a user menu, a footer, and flash toasts. Dark mode comes from the kit.
- **ProductsIndexPage:** a filter sidebar (price range, minimum stars, sort), the product grid and pagination.
  - Filters are kept in the URL query string.
  - Search is debounced at 300 ms and uses `router.get` with `preserveState`, `preserveScroll` and `only: ['products']`.
- **ProductShowPage:** an image gallery, a quantity stepper, add to cart, the average rating, and the current user's `RatingInput`, which saves when clicked.
- **CartPage:**
  - line items with a stepper and a remove button
  - a shipping-method select
  - server-computed totals
  - checkout through `useForm` with a confirmation step
  - an insufficient-balance error that links to the wallet
- **OrdersIndexPage / OrderShowPage:** the order list and the order detail with item snapshots.
- **WalletPage:** the balance, the top-up form and a paginated ledger.

### 6.3 Data flow rules

- No money arithmetic in JS. Only server-formatted values are displayed.
- Every form uses `useForm`. No raw `fetch` or `axios`.
- Partial reloads (`only: […]`) after cart changes and ratings.
- `<Link>` for internal navigation, with URLs built by whichever route helper the installed starter kit ships (Laravel Wayfinder at the time of writing; confirmed at setup). No hand-written URL strings.
- Page props are typed per page, and shared data comes through `usePage<SharedProps>()`.

### 6.4 Assets and branding

- The seeder copies the four legacy product images into `storage/app/public/products`, served via `storage:link`.
- The Cartopia logo is kept, and everything else uses the starter kit's styling.

## 7. Testing

### 7.1 Unit tests (Pest)

- **Money:** arithmetic, comparison and formatting, including $0 and large values.
- **Wallet:**
  - credit and debit write the ledger and balance together
  - an overdraw throws `InsufficientBalance` and leaves state unchanged
  - property-style test: after random operations, the balance equals the ledger sum and is never negative
- **PlaceOrder:**
  - totals come from DB prices plus the shipping fee
  - item snapshots are written
  - the cart is cleared
  - `EmptyCart` is thrown on an empty cart
  - an insufficient balance rolls back everything (no order, no items, the cart is intact)
- **MergeGuestCart:** quantities are summed, the 99 cap holds, and the session is cleared.
- **Product scopes:** `search`, `priceBetween`, `minRating`, every `sortBy` option, and `withRatingStats`.

### 7.2 Feature tests

- **Checkout:**
  - happy path
  - insufficient balance flashes an error
  - client-sent `price`, `total` or `total_price` fields are ignored
  - a guest is redirected to login
  - the throttle applies
- **Cart:** add, update and remove for both a guest and a user; quantity validation; the merge after login works end to end.
- **Ratings:** only 1–5 accepted; rating again updates rather than duplicates; a guest is refused.
- **Orders:** viewing another user's order returns 403.
- **Wallet:** top-up limits are enforced; a top-up appears in the ledger.
- **Registration:** the signup bonus is credited exactly once; `phone` and `address` persist.
- **Inertia assertions:** every page renders the right component with the right props, and no internal columns appear.

### 7.3 Browser tests (Pest 4)

1. Browse, add to cart, log in, check out, and see the order page.
2. Wallet top-up.

### 7.4 Environment

- Tests use an SQLite in-memory database. Row locks are a no-op there; MySQL enforces them in production.
- Factories exist for every model, with states including `admin()`, `withBalance(Money)` and `withImages(n)`.

## 8. Tooling and CI

- **Pint** with the Laravel preset
- **Larastan** level 6
- The kit's **ESLint and Prettier** config, plus `vue-tsc --noEmit`
- **GitHub Actions** workflow on push and pull request:
  1. Composer and npm install
  2. Pint `--test`
  3. Larastan
  4. ESLint and `vue-tsc`
  5. `npm run build`
  6. Pest, including browser tests

  The README shows its status badge.

## 9. Seed data

- The four legacy products (names, descriptions, prices and SKUs from `cartopia.sql`, converted to cents), each with its image.
- Shipping methods: Pickup $0.00 and UPS $5.00.
- The demo user from the README: `amrcs1992@gmail.com` with password `12345`, bcrypt-hashed, and a $100 `signup_bonus` ledger entry.
- One admin user (`is_admin = true`) for sub-project 2.
- Legacy users, orders and ratings are **not** migrated. They were demo data, and the legacy password hashes are not portable.

## 10. Definition of done

- Every route in §5.1 works at `http://cartopia.test`, and the seeded demo login works.
- Pint, Larastan, ESLint, vue-tsc and Pest (unit, feature and browser) all pass locally and in CI.
- The README is rewritten to cover:
  - what Cartopia is
  - the stack
  - screenshots
  - Laragon setup steps
  - demo credentials
  - a short "2018 → 2026 rewrite" section describing the legacy defects fixed (§1)
