# Cartopia Admin Panel Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A Filament back office at `/admin` for products and aisles, orders and payments, full-order refunds, customers, wallets, admin access and a small dashboard.

**Architecture:** Filament 5.11 (already installed on `admin/design`, with `User implements FilamentUser`) supplies the screens. Every change to money or access goes through an action class in `app/Actions/Admin/`, called by Filament and covered by Pest tests. Refunds add a `refund()` method to the existing `PaymentGateway` contract.

**Tech Stack:** Laravel 13, Filament 5.11 + Livewire 4, Inertia 3 + Vue 3 (store side), Pest 4 (+ browser plugin), Larastan level 7, Pint, Oxlint, vue-tsc.

**Spec:** `docs/superpowers/specs/2026-11-02-cartopia-admin-panel-design.md` (sections are cited as "spec §N").

## Global Constraints

- PHP is Laragon's: `export PATH=/c/laragon/bin/php/php-8.3.36-Win32-vs16-x64:$PATH`. Run Pest as `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest …`. Composer: `php -d extension=sockets "$(dirname $(which composer))/composer.phar" …`.
- Repo: `C:\laragon\www\cartopia`. Never read, print or edit `.env`.
- Money is `App\Support\Money` (integer cents). Every balance change goes through `App\Wallet\Wallet` (`credit()` / `debit()`), which locks the user row.
- Lock order is payments → orders → users, as in the payments code.
- Copy rules: the store says "basket", "wallet", "aisle", "Stripe" (never "card" as the method name), "PayPal". Messages are exact strings from this plan or the spec.
- Every commit: subject line plus body, no AI attribution or `Co-Authored-By`. Push right after each commit. No force-push.
- Branches, stacked: `admin/phase-1-panel` from `admin/design`; `admin/phase-2-catalogue` from phase 1's tip; `admin/phase-3-orders` from phase 2's; `admin/phase-4-refunds` from phase 3's. One PR per phase, each based on the previous branch (phase 1 on `main`). Nothing is merged without the user.
- Each task ends with Pint, Larastan and the relevant Pest tests green; each phase ends with the full unit+feature suite, the browser suite, `npm run lint`, `npm run format:check` and `npx vue-tsc --noEmit`.
- Filament tests use `Livewire::test(...)` with Filament's helpers (`fillForm`, `callAction`, `assertHasFormErrors`, `assertCanSeeTableRecords`), acting as an admin made with `User::factory()->admin()`.

## Review Focus

1. **A double refund** (two clicks, two tabs, a retry after a timeout) must never refund the provider twice or credit the wallet twice — Task 13 tests both unique keys and the resumed pending refund.
2. **A hidden product in a basket** must never be charged, on any checkout path (wallet, Stripe, PayPal, split) — Task 5 tests each path.
3. **Removing admin access** must never leave zero admins or lock out the admin doing it — Task 3 tests self-removal and last-admin removal, including when the button is bypassed.
4. **A debit adjustment** must never push a balance below $0.00, even when two adjustments race — Task 3 relies on `Wallet::debit`'s row lock and tests the refusal.
5. **A shopper at `/admin`** sees the store's not-found page, not Filament and not a 403 — Task 1 tests shopper, guest and admin.

---

## Phase 1 — Panel, access, look, customers (`admin/phase-1-panel`)

### Task 1: Panel look, store login and shopper access

**Files:**
- Modify: `app/Providers/Filament/AdminPanelProvider.php`
- Create: `app/Http/Middleware/AuthenticateAdmin.php`
- Modify: `resources/js/modules/layout/AccountMenu.vue`, `resources/js/modules/layout/PhoneMenu.vue`
- Test: `tests/Feature/Admin/PanelAccessTest.php` (replace), `tests/Browser/AdminPanelTest.php` (create)

**Interfaces:**
- Produces: the panel id `admin`, path `admin`; later tasks register resources and widgets by discovery in `app/Filament/{Resources,Pages,Widgets}`.

- [ ] **Step 1: Write the failing tests**

```php
it('lets an admin into the panel', fn () => $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk());
it('shows a shopper the not-found page', fn () => $this->actingAs(User::factory()->create())->get('/admin')->assertNotFound());
it('sends a guest to the store login', fn () => $this->get('/admin')->assertRedirect(route('login')));
it('has no Filament login page', fn () => $this->get('/admin/login')->assertNotFound());
```

Browser: an admin sees an "Admin" link in the account menu that opens `/admin`; a shopper sees no such link.

- [ ] **Step 2: Run them, expect the shopper (403 today), guest and login-page tests to fail**

- [ ] **Step 3: Implement**
  - Panel: `->brandName('Cartopia')`, `->brandLogo(asset('favicon.svg'))` with `->brandLogoHeight('2rem')`, `->favicon(asset('favicon.svg'))`, `->colors(['primary' => Color::hex('#1F5A3D'), 'warning' => Color::hex('#F5B42E')])`, `->font('Figtree')`, dark mode left on (follows the system). Remove `->login()` and the `FilamentInfoWidget` (keep `AccountWidget` until Task 11 replaces the dashboard).
  - `AuthenticateAdmin` extends Filament's `Filament\Http\Middleware\Authenticate`: a guest is redirected to `route('login')` (set `redirectTo`), a logged-in non-admin gets `abort(404)` instead of Filament's 403. Use it in `->authMiddleware([AuthenticateAdmin::class])`.
  - After the store login, an admin who came from `/admin` lands back on `/admin` (Fortify's intended redirect already does this; the test proves it).
  - Account menu and phone menu: an "Admin" link to `/admin` when `page.props.auth.user?.is_admin`, placed before "Log out". It is a plain `<a href>` (Filament is not an Inertia page).

- [ ] **Step 4: Run tests, expect pass**
- [ ] **Step 5: Commit** — "Give the admin panel Cartopia's look and hide it from shoppers"

### Task 2: Wallet adjustments and admin access actions

**Files:**
- Create: migration `…_add_admin_id_to_wallet_transactions_table.php`
- Modify: `app/Models/WalletTransaction.php` (fillable/property `admin_id`, `admin()` belongsTo User), `app/Wallet/Wallet.php`
- Create: `app/Actions/Admin/AdjustWallet.php`, `app/Actions/Admin/SetAdminAccess.php`, `app/Exceptions/AdminActionRefused.php`
- Test: `tests/Feature/Admin/AdjustWalletTest.php`, `tests/Feature/Admin/SetAdminAccessTest.php`

**Interfaces:**
- `Wallet::credit(..., ?User $admin = null)` and `Wallet::debit(..., ?User $admin = null)` — new trailing optional parameter written to `admin_id`. Existing callers unchanged.
- `AdjustWallet::handle(User $customer, User $admin, Money $amount, bool $credit, string $reason): WalletTransaction` — throws `AdminActionRefused` with the spec's message when a debit exceeds the balance.
- `SetAdminAccess::handle(User $target, User $admin, bool $isAdmin): User` — throws `AdminActionRefused`.
- `AdminActionRefused` extends `RuntimeException`; its message is shown to the admin verbatim.

- [ ] **Step 1: Write the failing tests**
  - credit adds a `admin_adjustment` row with the reason as description, `admin_id` set, balance up.
  - debit of $5.00 from $12.00 leaves $7.00.
  - debit of $12.01 from $12.00 throws `AdminActionRefused` with "Ali's balance is $12.00, so you can debit at most $12.00." (customer name "Ali"), nothing written.
  - amount below $0.01 or above $500.00, or a reason over 120 characters or empty, throws `InvalidArgumentException`.
  - grant sets `is_admin`; removing another admin clears it.
  - removing yourself throws "You can't remove your own admin access."
  - with two admins A and B, B removes A fine; then a stale call that removes B on behalf of A (A's access already gone, as in a race between two tabs) throws "Cartopia needs at least one admin." and B stays admin.
  - the acting user must be an admin: a non-admin actor throws "Only admins can change admin access."
- [ ] **Step 2: Run, expect failures**
- [ ] **Step 3: Implement.** `SetAdminAccess` locks the admin rows (`User::where('is_admin', true)->lockForUpdate()`) before counting, so two simultaneous removals can't both pass. `AdjustWallet` maps `InsufficientBalance` to `AdminActionRefused` with the message above.
- [ ] **Step 4: Run, expect pass**
- [ ] **Step 5: Commit** — "Add wallet adjustments and admin access actions"

### Task 3: Customers resource

**Files:**
- Create: `app/Filament/Resources/Customers/CustomerResource.php` (model `User`, label "Customer"), its `Pages/ListCustomers.php`, `Pages/ViewCustomer.php`, `RelationManagers/OrdersRelationManager.php`, `RelationManagers/WalletTransactionsRelationManager.php`
- Test: `tests/Feature/Admin/CustomerResourceTest.php`, extend `tests/Browser/AdminPanelTest.php`

**Interfaces:**
- Consumes: `AdjustWallet`, `SetAdminAccess`, `AdminActionRefused` (Task 2).

- [ ] **Step 1: Write the failing tests**
  - list shows name, email, formatted balance, order count, joined date, Admin badge; search by name and by email; "Admins only" filter.
  - view shows details read-only; Orders tab lists the customer's orders; Wallet tab lists ledger rows newest first with type label, description, amount, balance after.
  - "Adjust wallet" action: credit $10.00 "Goodwill" → balance +$10.00; debit too large → action fails with the exact refusal message shown as a danger notification; missing reason → form error.
  - "Make admin" / "Remove admin" with confirmation; the Remove button is hidden on your own page, and calling it anyway is refused with the message.
  - no create, edit or delete pages exist for customers.
  - browser: admin opens a customer and credits $5.00; the ledger shows "Adjustment · {reason}".
- [ ] **Step 2: Run, expect failures**
- [ ] **Step 3: Implement** with Filament tables/infolists. Amount field takes dollars ("12.50"), converted with `Money::fromDollars`. Navigation: "Customers", icon `heroicon-o-users`, sort 40.
- [ ] **Step 4: Run, expect pass**
- [ ] **Step 5: Commit** — "Add the customers screen with wallet adjustments and admin access"

**Phase 1 close:** full checks, push, open PR "Admin panel, phase 1: look, access and customers" against `main`.

---

## Phase 2 — Products and aisles (`admin/phase-2-catalogue`)

### Task 4: Visible products in the store

**Files:**
- Create: migration `…_add_is_visible_to_products_table.php`
- Modify: `app/Models/Product.php` (cast, fillable, `scopeVisible()`), `app/Catalog/FeaturedProducts.php`, `app/Catalog/Aisles.php`, `app/Http/Controllers/HomeController.php`, `app/Http/Controllers/ProductController.php`, `app/Http/Controllers/CartItemController.php`, `app/Listeners/MergeGuestCart.php`, `database/factories/ProductFactory.php` (`hidden()` state)
- Test: `tests/Feature/Http/HiddenProductsTest.php`

- [ ] **Step 1: Failing tests:** a hidden product is absent from `/products` (also with `q` and aisle filters), the featured carousel, the home price board, and aisle counts; `/products/{slug}` is 404; adding it to the basket is refused (404); a guest basket merged at login drops it.
- [ ] **Step 2: Run, expect failures**
- [ ] **Step 3: Implement** with `Product::visible()` on each query. The product page uses `abort_unless($product->is_visible, 404)`.
- [ ] **Step 4: Run, expect pass**
- [ ] **Step 5: Commit** — "Hide products marked invisible from the shop"

### Task 5: Hidden products in baskets and checkout

**Files:**
- Modify: `app/Cart/CartLine.php` (an `available` flag), the cart store(s) that build lines, `app/Cart/PricedBasket.php`, `app/Cart/CartSummary.php`, the checkout guard used by `PlaceOrder` and `StartOrderPayment`, the basket page component and its line component
- Create: `app/Exceptions/UnavailableProduct.php`
- Test: `tests/Feature/Http/HiddenProductCheckoutTest.php`, a browser test in `tests/Browser/CheckoutFlowTest.php`

- [ ] **Step 1: Failing tests:** a basket with a hidden product shows that line marked "No longer available", excludes it from the subtotal and item count; wallet checkout, Stripe checkout, PayPal checkout and split checkout each refuse with "Remove {name} from your basket to continue." and create no order or payment; removing the line lets checkout go through.
- [ ] **Step 2: Run, expect failures**
- [ ] **Step 3: Implement.** The refusal is a validation-style error flashed back to the basket (same channel as other checkout errors). Store copy is exactly the spec's.
- [ ] **Step 4: Run, expect pass**
- [ ] **Step 5: Commit** — "Keep hidden products out of baskets and checkout"

### Task 6: Products resource with photos

**Files:**
- Create: `app/Filament/Resources/Products/ProductResource.php`, `Pages/ListProducts.php`, `Pages/CreateProduct.php`, `Pages/EditProduct.php`, `app/Rules/ProductPhoto.php`
- Test: `tests/Feature/Admin/ProductResourceTest.php`

- [ ] **Step 1: Failing tests:** list columns, search by name and SKU, filters (aisle, featured, visible); create with every field from spec §4.2 and three photos stored in order as `product_images` (sort 0,1,2) on the `public` disk under `products/`; edit reorders photos and the sort order follows; removing a photo deletes its file; validation messages: name required/120, short name 18, slug unique and `^[a-z0-9-]+$`, SKU unique, price $0.01–$10,000.00, more than 5 photos refused; photo rules: PNG refused ("Photos need to be JPG or WebP."), 1.2 MB refused ("Photos need to be under 1 MB."), 1200×900 refused ("Photos need to be square."), 900×900 refused ("This photo is 900 × 900px. Photos need to be at least 1200 × 1200px."); there is no delete action. Use `UploadedFile::fake()->image(...)` with `Storage::fake('public')`.
- [ ] **Step 2: Run, expect failures**
- [ ] **Step 3: Implement.** Photos: `FileUpload::make('photos')->multiple()->reorderable()->maxFiles(5)->disk('public')->directory('products')->image()->rules([new ProductPhoto])`, synced to `product_images` in the create/edit page hooks (`afterCreate`/`afterSave`). Price field in dollars, stored in cents. Slug auto-filled from the name on create. Navigation "Products", icon `heroicon-o-shopping-bag`, sort 10.
- [ ] **Step 4: Run, expect pass**
- [ ] **Step 5: Commit** — "Add the products screen with photo uploads"

### Task 7: Aisles resource

**Files:**
- Create: `app/Filament/Resources/Aisles/AisleResource.php` (model `Category`, label "Aisle"), pages
- Test: `tests/Feature/Admin/AisleResourceTest.php`

- [ ] **Step 1: Failing tests:** list (tile, name, product count); create/edit name, slug, tile image (same `ProductPhoto` rule) stored as `image_path`; delete works for an empty aisle and is refused for one with 3 products with "Move its 3 products to another aisle first."
- [ ] **Step 2–4:** run, implement (navigation "Aisles", `heroicon-o-squares-2x2`, sort 20), pass
- [ ] **Step 5: Commit** — "Add the aisles screen"

**Phase 2 close:** full checks, push, PR "Admin panel, phase 2: products and aisles" against `admin/phase-1-panel`.

---

## Phase 3 — Orders, payments, dashboard (`admin/phase-3-orders`)

### Task 8: Orders resource (view only)

**Files:**
- Create: `app/Filament/Resources/Orders/OrderResource.php`, `Pages/ListOrders.php`, `Pages/ViewOrder.php`, `app/Payments/ProviderLinks.php`
- Test: `tests/Feature/Admin/OrderResourceTest.php`, `tests/Unit/ProviderLinksTest.php`

**Interfaces:**
- `ProviderLinks::for(Payment $payment): ?string` — Stripe: `https://dashboard.stripe.com/test/payments/{provider_payment_id}`; PayPal: `https://www.sandbox.paypal.com/activity/payment/{provider_payment_id}`; null without an id.

- [ ] **Step 1: Failing tests:** list columns (number, date, customer, items, total, paid with via `PaymentWording::tag`, status label), search by number/name/email, filters (status, method, date range), newest first; view shows items, totals, customer link, payment details and provider link; no create/edit/delete.
- [ ] **Step 2–4:** run, implement (navigation "Orders", `heroicon-o-receipt-percent`, sort 30, group none), pass
- [ ] **Step 5: Commit** — "Add the orders screen"

### Task 9: Check with provider

**Files:**
- Create: `app/Actions/Admin/CheckPaymentWithProvider.php`, `app/Payments/ProviderCheck.php` (result value: `answer` string for the admin)
- Test: `tests/Feature/Admin/CheckPaymentWithProviderTest.php`

**Interfaces:**
- `CheckPaymentWithProvider::handle(Payment $payment): string` — returns the admin message: "Paid at Stripe. The order is placed." / "Paid at Stripe. The top-up is credited." / "Stripe no longer has this payment. It's closed." / "Still pending at PayPal." / "This payment is already {status}." Throws `PaymentProviderUnavailable` (message shown as "Stripe couldn't be reached. Try again in a moment.").
- Reuses `ExpireStalePayment`'s logic (lock, lookup, `CompletePayment` / `ClosePayment(Expired, notify: true)`), except that a still-pending answer changes nothing.

- [ ] **Step 1: Failing tests** for each outcome with `PaymentGateways::fake()->willLookUp(...)`, including a settled payment (no provider call) and a provider exception.
- [ ] **Step 2–4:** run, implement, pass
- [ ] **Step 5: Commit** — "Let admins check a payment with Stripe or PayPal"

### Task 10: Payments resource

**Files:**
- Create: `app/Filament/Resources/Payments/PaymentResource.php`, `Pages/ListPayments.php` (tabs "All" and "Needs attention"), `Pages/ViewPayment.php`
- Test: `tests/Feature/Admin/PaymentResourceTest.php`

- [ ] **Step 1: Failing tests:** list columns (ID, date, customer, purpose "Order #14"/"Top-up", method, amount, status, reference), filters; "Needs attention" shows only `pending` payments created more than 30 minutes ago; view shows details, linked order, provider link; "Check with provider" action shows the returned message as a notification (danger for unavailable).
- [ ] **Step 2–4:** run, implement (navigation "Payments", `heroicon-o-credit-card`, sort 35), pass
- [ ] **Step 5: Commit** — "Add the payments screen with a needs-attention tab"

### Task 11: Dashboard

**Files:**
- Create: `app/Filament/Widgets/SalesOverview.php` (stats), `app/Filament/Widgets/SalesChart.php`, `app/Filament/Widgets/LatestOrders.php`, `app/Admin/SalesFigures.php`
- Modify: `AdminPanelProvider` (widgets: these three, drop `AccountWidget`)
- Test: `tests/Feature/Admin/DashboardTest.php`

**Interfaces:**
- `SalesFigures` (queries only placed orders): `today(): Money`, `thisWeek(): Money` (from Monday 00:00 app timezone), `ordersThisWeek(): int`, `needsAttention(): int` (pending payments older than 30 minutes), `daily(int $days = 30): array<string, int>` (date → cents, zero-filled).

- [ ] **Step 1: Failing tests:** figures exclude cancelled, refunded and awaiting-payment orders; week starts Monday (freeze time with `$this->travelTo`); the daily series has 30 zero-filled days; the dashboard renders the four stat labels "Sales today", "Sales this week", "Orders this week", "Needs attention", the chart heading "Sales, last 30 days" and the five latest orders.
- [ ] **Step 2–4:** run, implement, pass
- [ ] **Step 5: Commit** — "Add the admin dashboard"

**Phase 3 close:** full checks, push, PR "Admin panel, phase 3: orders, payments and dashboard" against `admin/phase-2-catalogue`.

---

## Phase 4 — Refunds (`admin/phase-4-refunds`)

### Task 12: Refund data and the gateway method

**Files:**
- Create: migrations for `refunds` (spec §2.2, including `attempt`) and `wallet_transactions.refund_id` (nullable, unique, FK); `app/Enums/RefundStatus.php`; `app/Models/Refund.php` (+ factory); `app/Payments/Outcomes/RefundOutcome.php` (interface), `RefundAccepted.php` (`providerRefundId`), `RefundRefused.php` (`reason`)
- Modify: `app/Enums/OrderStatus.php` (+`Refunded`, label "Refunded"), `app/Enums/WalletTransactionType.php` (+`Refund`, label "Refund"), `app/Models/Order.php` (`refund()` hasOne), `app/Wallet/Wallet.php` (`credit(..., ?Refund $refund = null)` writes `refund_id`), `app/Payments/PaymentGateway.php`, `StripeGateway.php`, `PayPalGateway.php`, `FakePaymentGateway.php` (`willRefund(RefundOutcome|Throwable ...)`, public `$refunded`)
- Test: `tests/Feature/Payments/StripeGatewayTest.php`, `PayPalGatewayTest.php` (extend), `tests/Feature/Domain/RefundModelTest.php`

**Interfaces:**
- `PaymentGateway::refund(Payment $payment, Refund $refund): RefundOutcome`
- `Refund::idempotencyKey(): string` → `refund-{id}-{created_at unix}-{attempt}`

- [ ] **Step 1: Failing tests:** Stripe sends `POST /v1/refunds` with `payment_intent` = `provider_payment_id`, `amount` = payment cents, idempotency key; maps a refund object to `RefundAccepted('re_…')`, a 4xx to `RefundRefused(message)`, 5xx/connection to `PaymentProviderUnavailable`. PayPal sends `POST /v2/payments/captures/{id}/refund` with `amount {currency_code USD, value "22.74"}` and `PayPal-Request-Id`; maps `COMPLETED`/`PENDING` to accepted with the refund id, 4xx to refused with `details.0.issue`, 5xx to unavailable. The unique `refund_id` rejects a second wallet credit for the same refund.
- [ ] **Step 2–4:** run, implement, pass
- [ ] **Step 5: Commit** — "Add refund records and the gateway refund call"

### Task 13: RefundOrder

**Files:**
- Create: `app/Actions/Admin/RefundOrder.php`
- Test: `tests/Feature/Admin/RefundOrderTest.php`

**Interfaces:**
- `RefundOrder::handle(Order $order, User $admin, string $reason): Refund` — follows spec §7.2 exactly. Throws `AdminActionRefused` ("Only placed orders can be refunded.", "This order is already refunded.", "Stripe refused the refund: {reason}." / "PayPal refused the refund: {reason}.") and lets `PaymentProviderUnavailable` through after leaving the refund pending.

- [ ] **Step 1: Failing tests:** wallet-only order → wallet credited, order refunded, refund succeeded, no gateway call; Stripe-only → gateway called once, no wallet row; split → both, wallet row "Refund for order #N" with `refund_id`; refused → refund failed with reason, order still placed, nothing credited; retry after refused → `attempt` 2 and a new key; unavailable → refund pending; retry after unavailable → same `attempt` and same key, success; calling twice after success → refused "already refunded", gateway called once in total; awaiting-payment, cancelled orders refused; reason required (`InvalidArgumentException`); the refund email is queued once after commit and not on failure (`Mail::fake()`, assert queued `OrderRefundedMail` — the class is created in Task 15; until then assert nothing queued and add the assertion in Task 15).
- [ ] **Step 2–4:** run, implement, pass
- [ ] **Step 5: Commit** — "Add full-order refunds"

### Task 14: Refunds in the panel and for the shopper

**Files:**
- Modify: `OrderResource` view page (actions "Refund order" / "Retry refund", refund section), `app/Payments/PaymentWording.php` (refund lines and the "Refunded" tag), the order resource/presenter sent to `OrderShowPage.vue`, `OrderShowPage.vue`, `OrderRow.vue`, the wallet ledger row labels
- Test: `tests/Feature/Admin/RefundActionTest.php`, `tests/Feature/Http/RefundedOrderTest.php`, browser test in `tests/Browser/AdminPanelTest.php`

- [ ] **Step 1: Failing tests:** the confirmation lists the breakdown lines exactly as spec §7.3 and a required Reason; the button reads "Refund $122.74"; success notification "Order #14 is refunded."; refused/unavailable show the action's message as a danger notification; "Retry refund" appears for pending or failed refunds; the action is hidden for non-placed and refunded orders. Store: a refunded order shows status "Refunded", "Refunded to Visa ending in 4242 · $22.74" (PayPal: "Refunded to PayPal · $X"), "Refunded to your wallet · $100.00"; Orders list shows the Refunded status; the ledger shows "Refund · Order #14 · +$100.00". Browser: an admin refunds a split order and the shopper's receipt shows both refund lines.
- [ ] **Step 2–4:** run, implement, pass
- [ ] **Step 5: Commit** — "Let admins refund orders and show refunds to shoppers"

### Task 15: Refund receipt email

Before dispatch, the controller draws "Email: order refunded" (light and dark) on the canvas's Emails page in the style of the other three emails, using the copy below.

**Files:**
- Create: `app/Mail/OrderRefundedMail.php`, `resources/views/mail/order-refunded.blade.php`, `resources/views/mail/order-refunded-text.blade.php`
- Modify: `RefundOrder` (queue it after commit), `tests/Feature/Admin/RefundOrderTest.php`
- Test: `tests/Feature/Mail/OrderRefundedMailTest.php`

**Copy:**
- Subject: "Your Cartopia Order #14 Has Been Refunded"
- Title: "Your order has been refunded"
- Intro: "We've refunded your order in full. Here's where the money went."
- Rows: "Refunded to Visa ending in 4242" / "Refunded to PayPal" with the provider amount; "Refunded to your wallet" with the wallet amount; "Wallet balance after"
- Note (provider part only): "Card and PayPal refunds can take 5–10 business days to show."
- Button: "View your order"
- Badge glyph: "↩" (U+21A9), written as the character, not an entity.

- [ ] **Step 1: Failing tests:** subject, title, intro, the items and total, each row per payment shape (wallet-only, Stripe, PayPal, split), the note only with a provider part, the order link, plain-text twin, no double escaping (`&amp;#` absent); `RefundOrder` queues exactly one after a successful refund.
- [ ] **Step 2–4:** run, implement with the existing layout and partials, pass
- [ ] **Step 5: Commit** — "Email shoppers a refund receipt"

### Task 16: Docs

**Files:**
- Modify: `README.md` (an "Admin panel" section: `/admin`, the seeded admin, what each screen does, refunds in sandbox), the spec's status line ("Implemented")

- [ ] **Step 1:** write the section; **Step 2:** commit — "Document the admin panel"

**Phase 4 close:** full checks, push, PR "Admin panel, phase 4: refunds" against `admin/phase-3-orders`. Then the whole-branch final review and one fix wave on the phase 4 branch.
