# Cartopia: Admin Panel Design

- **Date:** 2026-11-02
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Sub-project:** 4 of 4. The order is: 1 core store (done, amrcs92/cartopia#1), 2 store redesign (done, amrcs92/cartopia#2, #3 and #4), 3 payments with Stripe and PayPal (done, amrcs92/cartopia#5 to #9, with fixes in #16 and #17), **4 Filament admin panel (this spec)**.
- **Design reference:** the canvas [Cartopia Store Redesign](https://claude.ai/artifact/GMbSbdvDMisyhnSpqQUmkq). The panel itself uses Filament's own layout and needs no boards. The refund receipt email is drawn on the canvas's **Emails** page and approved before it is built (section 9).

## 1. Goal and context

Until now everything the shop sells comes from the seeder, and nobody can see orders, payments or wallets except in the database. This sub-project adds a back office at `/admin` where admins manage the catalogue, look into orders and payments, refund orders, adjust wallets and manage who else is an admin.

Stripe and PayPal stay in sandbox mode. Refunds go through the same sandboxes.

Success means:
- An admin can add a product with photos, hide it, feature it and move it between aisles without touching the database.
- An admin can find any order or payment, see exactly how it was paid, and refund a placed order in full: the card or PayPal part through the provider and the wallet part to the wallet, once and only once.
- An admin can see a customer's wallet ledger and credit or debit it with a reason.
- Nobody without admin access gets into the panel, and the panel can never lock out its last admin.

### Scope

**In:**
- A Filament panel at `/admin`, in Cartopia's colours, light and dark.
- Products and aisles: create, edit, up to five photos per product, featured and visible switches.
- Orders and payments: lists, filters, detail pages, a "needs attention" view and a "Check with provider" action.
- Full-order refunds and a refund receipt email.
- Customers: list, detail with orders and wallet ledger, wallet adjustments, granting and removing admin access.
- A small dashboard.

**Out:**
- Partial and per-item refunds, and refunding wallet top-ups. Wallet adjustments cover the rare case.
- Refund webhooks. The provider's answer to the refund call is the record.
- Fulfilment steps (ready for pickup, shipped). Orders stay view-only.
- Roles and permissions beyond "admin or not".
- Deleting products.
- Live payments.

### Decisions taken in brainstorming

- **Filament**, not hand-built Inertia pages. Filament is a standard back office; the money rules live in the app's own action classes, which Filament only calls.
- **Full-order refunds only.** One action returns everything.
- **Filament's layout with Cartopia's colours**, not the store's full stall look.
- **Admins manage admins.** No one can remove their own admin access or the last admin's.
- **Small dashboard:** four stat cards, a 30-day sales chart, the latest orders.
- **Orders are view-only.** No fulfilment workflow.
- **Hide, don't delete.** Products get a "Visible in shop" switch; there is no delete action.
- **Photo rules are enforced strictly** on upload.
- **A refund receipt email**, drawn on the canvas for approval first.

## 2. Data

### 2.1 Enums

- `OrderStatus` gains `Refunded` (`refunded`), labelled "Refunded".
- `WalletTransactionType` gains `Refund` (`refund`), labelled "Refund".
- New `RefundStatus`: `Pending`, `Succeeded`, `Failed`.

### 2.2 New `refunds` table

| Column | Type | Notes |
|---|---|---|
| `id` | id | |
| `order_id` | foreign key, **unique** | One refund per order, since refunds are full |
| `payment_id` | foreign key, nullable | The Stripe or PayPal payment refunded; null for wallet-only orders |
| `admin_id` | foreign key to `users` | Who refunded it |
| `reason` | text | Required, shown only in the panel |
| `provider_amount` | unsigned int cents | The payment's amount, or 0 |
| `wallet_amount` | unsigned int cents | The order's `wallet_amount`, or 0 |
| `status` | string (`RefundStatus`) | |
| `provider_refund_id` | string, nullable | Stripe `re_…` or PayPal refund ID |
| `failure_reason` | string, nullable | The provider's refusal, shown to the admin |
| `attempt` | unsigned small int, default 1 | Goes up each time a failed refund is tried again; part of the idempotency key |
| `completed_at` | timestamp, nullable | |
| timestamps | | |

`provider_amount + wallet_amount` always equals the order's total.

### 2.3 `products` changes

- `is_visible` boolean, default true, indexed. Every existing product stays visible.

### 2.4 `wallet_transactions` changes

- `refund_id` nullable foreign key, **unique**, so a refund credits the wallet once.
- `admin_id` nullable foreign key to `users`, set on admin adjustments.
- The `admin_adjustment` type already exists; the ledger shows it as "Adjustment".

## 3. Admin access

- The panel is at `/admin` and uses the store's own login. A guest is redirected to the store's login page; after logging in an admin lands on the dashboard.
- `User` implements Filament's `FilamentUser`; `canAccessPanel()` returns `is_admin`. A logged-in non-admin gets the store's not-found page (404), so the panel stays invisible to shoppers. Filament's own login page is turned off.
- The store's account menu shows an **Admin** link to admins only (the shared `auth.user.is_admin` prop already exists).
- **Make admin** and **Remove admin** are actions on the customer page, each with a confirmation. `SetAdminAccess` refuses:
  - removing your own admin access ("You can't remove your own admin access."), and the button is hidden on your own page
  - removing the last admin ("Cartopia needs at least one admin.")

## 4. Products and aisles

### 4.1 Product list

- **Columns:** main photo, name, aisle, price, Featured, Visible, last changed.
- **Search:** name and SKU.
- **Filters:** aisle, featured, visible or hidden.
- Sorted by name.

### 4.2 Product form

| Field | Rules |
|---|---|
| Name | required, up to 120 characters |
| Short name | optional, up to 18 characters (the home page's price board) |
| Slug | filled from the name, editable, unique, lowercase letters, digits and dashes |
| SKU | required, unique |
| Aisle | optional, from the aisles |
| Price | required, in dollars, $0.01 to $10,000.00, stored in cents |
| Description | required |
| Featured | switch |
| Visible in shop | switch, on for new products |
| Photos | up to 5, reorderable; the first is the main photo |

**Photo rules**, enforced on every upload, from the redesign spec's section 7:
- JPG or WebP
- under 1 MB
- square, at least 1200 × 1200 px

A refused upload says why, for example "This photo is 900 × 900px. Photos need to be at least 1200 × 1200px." Files are stored on the public disk under `products/`, and each becomes a `product_images` row whose `sort_order` follows the order in the form. Removing a photo deletes its file. Existing seeded photos are not re-checked.

### 4.3 Hidden products

- Hidden products leave the shop list, search, aisle filters, the featured carousel, the home page's price board and the "added to basket" suggestions.
- Their product page returns the store's not-found page.
- They can't be added to a basket.
- A hidden product already in a basket shows as "No longer available", is left out of the total, and checkout refuses the basket with "Remove {name} from your basket to continue." This applies to wallet, Stripe, PayPal and split checkouts alike.
- Orders and receipts keep showing them, from the order's saved name and price.

### 4.4 Aisles

- **List:** tile image, name, number of products.
- **Form:** name (required, unique), slug (filled from the name, unique), tile image (one upload, the same photo rules).
- **Delete** only when the aisle has no products; otherwise "Move its 3 products to another aisle first."

## 5. Orders and payments

### 5.1 Order list

- **Columns:** number, date, customer, items, total, paid with (Wallet, Stripe, PayPal, Wallet + Stripe, Wallet + PayPal), status.
- **Search:** order number, customer name or email.
- **Filters:** status, payment method, date range.
- Newest first.

### 5.2 Order page

- The receipt: items with their saved names and prices, subtotal, delivery, total.
- The customer, linking to their page.
- The payment: method, card brand and last four, wallet part, provider part, the Stripe or PayPal reference, and a link to the payment in the provider's sandbox dashboard.
- The refund, when there is one: admin, date, reason, amounts, status, provider refund ID, and the failure reason when it failed.
- **Refund order** (section 7), shown for placed orders without a succeeded or pending refund. **Retry refund** replaces it when the refund is pending or failed.
- Nothing is editable.

### 5.3 Payment list

- Every Stripe and PayPal payment, for orders and top-ups.
- **Columns:** ID, date, customer, purpose ("Order #14" or "Top-up"), method, amount, status, provider reference.
- **Filters:** status, method, purpose.
- **Needs attention:** a tab listing payments still `pending` more than 30 minutes after they started.

### 5.4 Payment page

- All the details above, the linked order, and links to the provider's dashboard.
- **Check with provider** runs the gateway's read-only `lookup()`, the same call the stale-payment job makes, then:
  - **paid:** `CompletePayment`, so the order is placed or the top-up credited as usual
  - **gone or expired:** `ClosePayment` with the problem email, as the stale job does
  - **still pending:** nothing changes
  - **unreachable:** nothing changes, "Stripe couldn't be reached. Try again in a moment."

  The admin always sees what the provider answered, for example "Still pending at PayPal."
- There is no manual "mark as paid".

## 6. Customers and wallets

### 6.1 Customer list

- **Columns:** name, email, wallet balance, orders, joined, Admin.
- **Search:** name and email.
- **Filter:** admins only.

### 6.2 Customer page

- Details: name, email, phone, address, joined. Read-only.
- **Orders** tab: their orders, linking to each order.
- **Wallet** tab: the ledger, newest first: date, type, description, amount, balance after.
- Actions: **Adjust wallet**, **Make admin** / **Remove admin**.

### 6.3 Adjust wallet

- **Form:** credit or debit, amount ($0.01 to $500.00), reason (required, up to 120 characters).
- `AdjustWallet` locks the customer's row, writes an `admin_adjustment` ledger row with the reason as its description and the admin in `admin_id`, and updates the balance, in one transaction.
- A debit can't take the balance below $0.00: "Ali's balance is $12.00, so you can debit at most $12.00."
- The shopper sees "Adjustment · {reason}" in their ledger. No email.

## 7. Refunds

### 7.1 The gateway method

`PaymentGateway` gains:

```php
public function refund(Payment $payment, Refund $refund): RefundOutcome;
```

- **Stripe:** creates a refund on the payment's payment intent (`provider_payment_id`) for the payment's full amount, with the idempotency key `refund-{refund id}-{created_at unix}-{attempt}`.
- **PayPal:** `POST /v2/payments/captures/{capture id}/refund` for the full amount, with `PayPal-Request-Id` set to the same key.
- **Outcomes:** `RefundAccepted(providerRefundId)` when the provider took the refund, whether the money has arrived or is on its way; `RefundRefused(reason)` for a 4xx answer. An unreachable provider or a 5xx answer throws `PaymentProviderUnavailable`, as the other methods do.
- The fake gateway gets the same method, with queued outcomes, for tests.

### 7.2 `RefundOrder`

`RefundOrder::handle(Order $order, User $admin, string $reason): Refund`

1. **Open the refund.** In a transaction, lock the order. Refuse unless it is `placed` ("Only placed orders can be refunded.") and has no succeeded refund ("This order is already refunded."). A `pending` refund (the provider couldn't be reached last time) is resumed at step 2 with its current `attempt`. Create the `refunds` row with status `pending`, the two amounts, the admin and the reason; or, when the order's refund `failed`, set it back to `pending`, raise its `attempt` and record the new admin and reason. Commit.
2. **Provider part.** When `provider_amount` is above 0, call `refund()` on the payment's gateway, under the payment's row lock as the other provider calls are.
   - **Refused:** mark the refund `failed` with the reason. The order stays placed. The admin sees "Stripe refused the refund: {reason}." A later retry uses the next `attempt`, because Stripe and PayPal replay a key's earlier answer, refusals included.
   - **Unavailable:** leave the refund `pending`. The admin sees "Stripe couldn't be reached. Try again in a moment." and **Retry refund** repeats the call with the same idempotency key, so the provider can't refund twice.
3. **Finish.** In one transaction, locking payment, order, then user (the payments lock order): credit `wallet_amount` to the wallet as a `refund` row ("Refund for order #14", `refund_id` set) when it is above 0, mark the refund `succeeded` with `provider_refund_id` and `completed_at`, set the order to `refunded`, and queue the refund email after commit.
4. **Wallet-only orders** skip step 2.

The unique `refunds.order_id` and `wallet_transactions.refund_id` make a double refund or a double wallet credit impossible, whatever is clicked twice.

### 7.3 The panel

**Refund order** opens a confirmation:
- the breakdown, for example "Visa ending in 4242, through Stripe: $22.74", "Wallet: $100.00", "Total: $122.74"
- a required **Reason**
- a **Refund $122.74** button

### 7.4 What the shopper sees

- The order's status shows **Refunded** on the receipt and in the Orders list.
- The receipt adds "Refunded to Visa ending in 4242 · $22.74" (or "Refunded to PayPal") and "Refunded to your wallet · $100.00".
- The wallet ledger shows "Refund · Order #14 · +$100.00".
- The refund receipt email (section 9).

## 8. Dashboard

- **Sales today**, **Sales this week** (Monday to now) and **Orders this week**: placed orders only, so cancelled and refunded orders don't count. Times are in the app's timezone.
- **Needs attention:** the number of payments pending more than 30 minutes, linking to that tab.
- **Sales, last 30 days:** a line chart of daily placed-order totals.
- **Latest orders:** the five newest orders, linking to each.

## 9. The refund receipt email

- **Subject:** "Your Cartopia Order #14 Has Been Refunded"
- Same layout, colours and dark-mode block as the other three emails, queued with `afterCommit()`, HTML and plain text.
- Shows the order's items and total, and where the money went: the card or PayPal line and the wallet line, with the wallet balance after.
- The wording and design are drawn on the canvas's **Emails** page, light and dark. The user chose on 2026-11-02 to have it built straight from that drawing and reviewed afterwards.

## 10. Look

- Filament's layout with:
  - Cartopia's logo and name
  - green `#1F5A3D` as the primary colour, marigold `#F5B42E` for warnings
  - Figtree as the font
  - light and dark, following the system, with Filament's switch
- The store's components are not reused.

## 11. Security

- Every panel route requires an admin; anyone else gets the store's not-found page. Actions check again on the server, not only by hiding buttons.
- Uploads are validated by type, size and dimensions on the server.
- Refunds and adjustments run only through their action classes, each in a transaction with row locks, and each records the admin.
- No live keys: the existing live-key guard covers refunds too.

## 12. Code structure

- `app/Filament/` for the panel provider, resources, pages and widgets.
- `app/Actions/Admin/`: `RefundOrder`, `AdjustWallet`, `SetAdminAccess`, `CheckPaymentWithProvider`.
- `app/Payments/`: `refund()` on `StripeGateway`, `PayPalGateway` and `FakePaymentGateway`, plus the `RefundOutcome` types.
- `app/Models/Refund.php`.
- `app/Mail/OrderRefundedMail.php` and `resources/views/mail/order-refunded(.blade|-text).php`.
- Migrations for section 2.
- The store's catalogue queries, basket and checkout gain the visibility rule (section 4.3).

## 13. Testing

- **Actions:** every path of `RefundOrder` (wallet-only, provider-only, split, refused, unavailable then retried, double click, non-placed order), `AdjustWallet` (credit, debit, debit below zero), `SetAdminAccess` (grant, remove, self, last admin), `CheckPaymentWithProvider` (each outcome).
- **Gateways:** `refund()` request and response mapping for Stripe and PayPal, with HTTP fakes.
- **Panel:** Filament's Livewire test helpers for each list, form and action, including the photo rules and admin-only access.
- **Shop:** hidden products disappear and checkout refuses them.
- **Browser:** an admin logs in, refunds an order and adjusts a wallet; a non-admin gets the not-found page.
- **Checks:** Pint, Larastan level 7, the suites on SQLite and MySQL, and a sandbox refund walkthrough for both providers at the end.

## 14. Delivery

Four phases, each its own branch and PR, stacked like payments:

1. Panel, access, look, customers, wallet adjustments, admin access.
2. Products and aisles, photos, hidden products in the shop.
3. Orders, payments, "Check with provider", dashboard.
4. Refunds, the gateway method, the refund email (after its canvas approval), the shopper-side refund lines.
