# Cartopia: Payments with Stripe and PayPal Design

- **Date:** 2026-11-01
- **Status:** Approved in brainstorming, awaiting written-spec review
- **Sub-project:** 3 of 4. The order is: 1 core store (done, amrcs92/cartopia#1), 2 store redesign (done, amrcs92/cartopia#2, #3 and #4), **3 payments with Stripe and PayPal (this spec)**, 4 Filament admin panel.
- **Design reference:** the canvas [Cartopia Store Redesign](https://claude.ai/artifact/GMbSbdvDMisyhnSpqQUmkq), **Final store** page.
  - **Drawn already:**
    - Basket and Basket: wallet too low, for the "Pay with" cards
    - Wallet, for the top-up "Pay with" choice
    - Receipt: card and Receipt: PayPal

    Each has its dark twin.
  - **Drawn during this sub-project, before the UI that uses them** (section 13):
    - Basket: split and Receipt: split
    - the three emails

## 1. Goal and context

Today the wallet is the only way to pay, and wallet top-ups are free credit. This sub-project lets shoppers pay by card through Stripe or with PayPal, both at checkout and when topping up the wallet. A shopper whose wallet is short can put their balance towards an order and pay the rest by card or PayPal. Every placed order, every paid top-up and every payment that ends badly while the shopper is away is confirmed by email.

Both providers run in sandbox (test) mode only. Cartopia is a portfolio project and no real money moves.

Success means:
- A shopper can pay for a basket or a top-up with a Stripe test card or a PayPal sandbox account, alone or together with their wallet balance.
- They land on the right receipt or wallet page and get the right email.
- They're never charged, credited, refunded or emailed twice, whatever order the return page and the webhooks arrive in.

### Scope

**In:**
- Stripe and PayPal as extra option cards in the basket's "Pay with" panel, with no layout changes.
- Splitting an order between the wallet and a card or PayPal, when the wallet is short.
- A "Pay with" choice for wallet top-ups. Top-ups stop being free credit.
- Hosted payment pages: Stripe Checkout and PayPal's approval page. The shopper leaves Cartopia to pay and comes back.
- Confirming payments when the shopper returns, plus Stripe and PayPal webhooks.
- The card, PayPal and split receipts, and the matching Orders list tags and ledger rows.
- Emails: an order receipt, a top-up receipt and a payment problem notice.
- Cancelling, declines, expiry of abandoned payments, and a guard against live keys.

**Out:**
- Refunds and admin views of payments belong to the admin panel, sub-project 4. Refunding a split order there must return both parts: the card or PayPal share and the wallet share.
- Saved cards and currencies other than US dollars. Neither is planned.

### Decisions taken in brainstorming

| Question | Decision |
|---|---|
| How does the shopper pay? | Hosted redirect: Stripe Checkout and PayPal's approval page. No card fields on Cartopia, matching the mockups. |
| How is a payment confirmed? | On return, the server asks the provider directly. Stripe and PayPal webhooks also settle payments for shoppers who never come back, and PayPal captures that finish later. |
| When does a card or PayPal order exist? | Straight away, as "Awaiting payment", before the redirect. It becomes "Placed" when the payment succeeds. |
| How is the code structured? | One small `PaymentGateway` contract with a Stripe and a PayPal implementation, shared by checkout and top-ups. |
| What is the method called? | `stripe` and `paypal`, after the provider. The interface names the method "Stripe" too (changed during the phase 3 design review); only the card itself is called a card ("Credit or debit card", "Visa ending in 4242"). |
| When can the wallet and a card share a total? | Only when the balance is above zero but below the total. |
| Which emails? | An order receipt for every method, a top-up receipt, and a notice when a payment ends badly while the shopper is away. |
| Where do emails go locally? | Laragon's Mailpit. `.env.example` and CI keep the `log` mailer. |
| How are the new screens designed? | The split screens and the emails are drawn on the canvas and approved before they're built. |

## 2. Data

### 2.1 Enums

- **`App\Enums\PaymentMethod`:**
  - cases: `Wallet` (`wallet`), `Stripe` (`stripe`) and `PayPal` (`paypal`)
  - `label()` returns "Wallet", "Stripe" and "PayPal".
  - `isHosted()` is true for `Stripe` and `PayPal`.
- **`App\Enums\PaymentPurpose`:** `Order` (`order`) and `TopUp` (`top_up`).
- **`App\Enums\PaymentStatus`:** `Pending`, `Succeeded`, `Cancelled`, `Failed` and `Expired`.
- **`OrderStatus`** gains `AwaitingPayment` (`awaiting_payment`) and `Cancelled` (`cancelled`) alongside `Placed`.
- **`WalletTransactionType`** gains `Release` (`release`), labelled "Returned", for a wallet share given back when a split order isn't paid.

### 2.2 New `payments` table

One row per attempt at a hosted payment, for an order or a top-up.

| Column | Type | Notes |
|---|---|---|
| `id` | bigint | |
| `user_id` | foreign key | `cascadeOnDelete` |
| `purpose` | string | `PaymentPurpose` |
| `order_id` | foreign key, nullable | set when the purpose is `order`; `nullOnDelete` |
| `method` | string | `PaymentMethod`, only `stripe` or `paypal` |
| `status` | string | `PaymentStatus` |
| `amount` | unsigned integer | cents, `MoneyCast`; the hosted part only, fixed when the shopper clicks |
| `currency` | string(3) | always `usd` |
| `provider_reference` | string, nullable | Stripe Checkout Session id or PayPal order id; set once the provider answers |
| `provider_payment_id` | string, nullable | Stripe PaymentIntent id or PayPal capture id; set when known |
| `card_brand` | string, nullable | for example `visa`; Stripe only |
| `card_last4` | string(4), nullable | Stripe only |
| `completed_at` | timestamp, nullable | when it left `pending` |
| timestamps | | |

Indexes:
- unique (`method`, `provider_reference`)
- (`status`, `created_at`) for the stale-expiry job
- (`user_id`)

`Payment` model:
- casts for the enums and the money
- `belongsTo(User)` and `belongsTo(Order)`
- `isPending()`
- a `pending()` scope

`Order` gains `hasMany(Payment)`. `User` gains `hasMany(Payment)`.

### 2.3 `orders` changes

- **`payment_method`** (string, `PaymentMethod`). The migration adds it with the default `wallet`, so existing orders read as wallet orders. The actions always set it explicitly. For a split order, it holds the hosted method.
- **`wallet_amount`** (unsigned integer, cents, `MoneyCast`, default 0). This is the part the wallet paid. It equals `total` for a wallet order, 0 for a pure card or PayPal order, and something in between for a split.
- `isSplit()` is true when `payment_method` is hosted and `wallet_amount` is above 0.
- A `placed()` scope.
- The Orders list shows only placed orders.
- The receipt page answers 404 for an order that isn't placed. `OrderPolicy::view` denies it as not found, the same way it treats someone else's order today.

### 2.4 `wallet_transactions` changes

- A new nullable, unique `payment_id` foreign key (`nullOnDelete`). A paid top-up can be credited only once, even if a code path slipped past the row lock.
- A paid top-up's description is "Top-up with Stripe" or "Top-up with PayPal".
- A release reads "Order #5 not paid, balance returned" and carries the order id.
- `Wallet::credit` takes an optional `?Payment $payment` and an optional `?Order $order`.
- The signup bonus and admin adjustments don't change.

### 2.5 Wallet orders

Wallet orders keep working as they do today: `PlaceOrder` is synchronous, with no `payments` row. It sets `payment_method` to `wallet` and `wallet_amount` to the total, and now also queues the order receipt email (section 8).

The free-credit `TopUpWallet` action is removed. A top-up can only be credited by a succeeded payment.

## 3. Payment gateways

### 3.1 The contract

`App\Payments\PaymentGateway`, implemented once per hosted method:

```php
interface PaymentGateway
{
    /** Creates the hosted payment. Returns the provider's reference and the URL to send the shopper to. */
    public function start(Payment $payment, PaymentRequest $request): StartedPayment;

    /** Asks the provider whether the payment went through (capturing it, for PayPal). Safe to call again. */
    public function confirm(Payment $payment): PaymentOutcome;

    /** Asks the provider what became of the payment without changing anything: no capture, no expiry. */
    public function lookup(Payment $payment): PaymentOutcome;

    /** Stops the hosted payment from being paid. Reports if it was already paid. */
    public function cancel(Payment $payment): PaymentOutcome;
}
```

The value objects:
- **`PaymentRequest`:**
  - the lines to show (`PaymentLine`: name, unit price in cents, quantity) and the delivery fee
  - a description, the return URL and the cancel URL

  The lines plus the delivery fee always add up to `payment.amount`. For a split order, Stripe can't show items that add up to more than it charges, so the request holds a single line, "Cartopia order #5 (after $2.10 from your wallet)", and no delivery fee.
- **`StartedPayment`:** `reference` and `redirectUrl`.
- **`PaymentOutcome`** is one of:
  - `Succeeded`, with `providerPaymentId`, `amountCents`, `currency`, and the optional `cardBrand` and `cardLast4`
  - `StillPending`, with an optional `providerPaymentId`
  - `Declined`, with a reason
  - `Cancelled`

Two exceptions:
- **`PaymentProviderUnavailable`:** connection errors, timeouts and 5xx answers.
- **`PaymentProviderRejected`:** a 4xx answer that isn't a decline, such as bad credentials or a bad request. It is logged and shown to the shopper as the "couldn't reach" message.

`App\Payments\PaymentGateways`, bound as a singleton:
- `for(PaymentMethod): PaymentGateway`
- `available(): list<PaymentMethod>` lists the hosted methods whose credentials are configured.

Tests and browser tests bind a `FakePaymentGateway` in place of the real gateways (section 12).

**Minimum charge:** Stripe won't charge less than $0.50, and Cartopia applies the same minimum to PayPal so both behave alike. `PaymentGateways::MIN_CENTS = 50`.

### 3.2 `StripeGateway`

Uses the official `stripe/stripe-php` SDK through a `StripeClient` built from `services.stripe.secret`.

- **start:** creates a Checkout Session with:
  - `mode: payment` and `payment_method_types: ['card']`
  - `line_items` from the request in USD cents, plus a "Delivery" line when the fee isn't zero
  - `client_reference_id` set to the payment id, and `metadata` holding `payment_id`, `purpose` and `order_id`
  - `customer_email` set to the user's email
  - `success_url` and `cancel_url` from the request
  - `expires_at` set to now plus 30 minutes, Stripe's minimum

  The request sends the idempotency key `payment-{id}-start`. It returns the session id and its `url`.
- **confirm:** retrieves the session with `expand: ['payment_intent.latest_charge']`.
  - `status: complete` with `payment_status: paid` gives `Succeeded`. The PaymentIntent id and the charge's `payment_method_details.card.brand` and `last4` are read from it.
  - `open` gives `StillPending`.
  - `expired` gives `Cancelled`.
- **lookup:** the same read as confirm.
- **cancel:** expires the session (idempotency key `payment-{id}-cancel`). If Stripe refuses because the session is already complete, it confirms instead and returns that outcome.
- **Webhook verification:** `\Stripe\Webhook::constructEvent` with `services.stripe.webhook_secret`.
- **Live-key guard:** the constructor throws a `LogicException` if the secret starts with `sk_live_` or `rk_live_`.

### 3.3 `PayPalGateway`

Uses Laravel's HTTP client against PayPal's Orders v2 REST API. The base URL is fixed to `https://api-m.sandbox.paypal.com` in code, not read from configuration, so a live host can't be reached.

- **Authentication:** an OAuth client-credentials token from `services.paypal.client_id` and `services.paypal.secret`. It is cached until 60 seconds before it expires.
- **start:** `POST /v2/checkout/orders` with:
  - `intent: CAPTURE`
  - one purchase unit: `custom_id` set to the payment id, an amount whose breakdown holds `item_total` and `shipping`, and `items` from the request
  - `payment_source.paypal.experience_context` holding the return and cancel URLs, `brand_name: Cartopia`, `user_action: PAY_NOW` and `shipping_preference: NO_SHIPPING`

  The header `PayPal-Request-Id: payment-{id}-start` is sent. It returns the order id and the `payer-action` link.
- **confirm:** `POST /v2/checkout/orders/{id}/capture` with `PayPal-Request-Id: payment-{id}-capture`.
  - A capture with status `COMPLETED` gives `Succeeded`, with the capture id and amount.
  - A capture with status `PENDING` (held by PayPal) gives `StillPending` with the capture id. `PAYMENT.CAPTURE.COMPLETED` settles it later.
  - `INSTRUMENT_DECLINED` and other declines give `Declined`.
  - `ORDER_ALREADY_CAPTURED` fetches the order (`GET /v2/checkout/orders/{id}`) and reads its existing capture as above. That makes `confirm` safe to call again.
  - An order that hasn't been approved yet gives `StillPending`.
  - A payment that already has a capture id (a held capture) isn't captured again: confirm reads the order with lookup instead.
- **lookup:** `GET /v2/checkout/orders/{id}`, read as above. It never captures. A `COMPLETED` capture gives `Succeeded`, a `PENDING` one `StillPending` with the capture id, and an order with no capture `StillPending`.
- **cancel:** PayPal orders can't be voided before approval, so this only reports `Cancelled`. Safety comes from section 6: Cartopia never captures a payment it has closed.

### 3.4 `PayPalWebhookVerifier`

- It calls `POST /v1/notifications/verify-webhook-signature` with:
  - the `PAYPAL-AUTH-ALGO`, `PAYPAL-CERT-URL`, `PAYPAL-TRANSMISSION-ID`, `PAYPAL-TRANSMISSION-SIG` and `PAYPAL-TRANSMISSION-TIME` headers
  - `webhook_id` from `services.paypal.webhook_id`
  - the event
- The event is spliced into the request JSON as the **raw request body**, without decoding and re-encoding it. Re-encoding can change bytes and make verification fail.
- It returns true only for `verification_status: SUCCESS`. A missing webhook id returns false and is logged.

### 3.5 Configuration

`config/services.php` gains:

```php
'stripe' => [
    'secret' => env('STRIPE_SECRET'),
    'webhook_secret' => env('STRIPE_WEBHOOK_SECRET'),
],
'paypal' => [
    'client_id' => env('PAYPAL_CLIENT_ID'),
    'secret' => env('PAYPAL_SECRET'),
    'webhook_id' => env('PAYPAL_WEBHOOK_ID'),
],
```

`.env.example` gains these five keys with empty values, plus `MAIL_FROM_ADDRESS=shop@cartopia.test` and `MAIL_FROM_NAME=Cartopia`. No Stripe publishable key is needed, because nothing loads Stripe.js.

**Availability:**
- A method is available only when its API credentials are set. Methods that aren't available are left out of "Pay with" and refused by validation, so a fresh clone or CI without keys works with the wallet only.
- A webhook secret or id isn't needed for a method to be available. Without it, that provider's webhook refuses every event, and payments are settled by the return alone.

## 4. Flows

### 4.1 Checkout by card or PayPal, alone or split

1. `POST /checkout` (`CheckoutRequest`) gains:
   - **`payment_method`:** required, and one of `wallet` plus the available hosted methods
   - **`use_wallet`:** a boolean, only read for a hosted method
2. **For `wallet`:** `PlaceOrder` runs as today. The flash text changes (section 7.3).
3. **For `stripe` or `paypal`:** the new `StartOrderPayment` action runs in one database transaction:
   1. It locks the user row and prices the basket from the database exactly as `PlaceOrder` does. It throws `EmptyCart` if the basket is empty.
   2. It works out the split with `SplitPlan::for(total, balance, useWallet)` (section 5).
   3. It refuses with `AmountTooSmall` if the hosted part would be below $0.50.
   4. It creates the order with status `AwaitingPayment`, `payment_method`, `wallet_amount`, the totals and the item snapshot.
   5. If `wallet_amount` is above 0, it debits the wallet for that amount (a `Purchase` row, "Order #5", linked to the order). The balance is held from this moment.
   6. It creates a `pending` payment for the hosted part.
   7. It calls the gateway's `start` and stores the returned `provider_reference` on the payment.

   If anything throws, the transaction rolls back and nothing is kept, including the wallet debit.
4. The controller answers `Inertia::location($redirectUrl)`, which sends the browser to the hosted page.
5. The basket isn't touched yet.

### 4.2 Top-up by card or PayPal

1. `POST /wallet/top-ups` (`TopUpRequest`) gains `payment_method`: required, and one of the available hosted methods. The amount limits of $5.00 to $500.00 stay, which keeps every top-up above the $0.50 minimum.
2. The new `StartTopUpPayment` action creates a `pending` payment with the purpose `top_up` and no order. It calls `start` with the single line "Cartopia wallet top-up", stores the reference, and the controller answers `Inertia::location`. A failed `start` rolls back the transaction here too.

### 4.3 Closing a payment

The new `ClosePayment` action is the one place a payment leaves `pending` without succeeding. The return, cancel, both webhooks and the stale job all use it.

It takes the payment, the end status (`cancelled`, `failed` or `expired`) and whether to notify the shopper. In one transaction it:
1. Locks the payment row and reloads it. If it is no longer pending, it returns without doing anything.
2. Sets the status and `completed_at`.
3. For an order payment, marks the order `Cancelled`. If the order's `wallet_amount` is above 0, it credits that amount back as a `Release` row.
4. If asked to notify, queues `PaymentProblemMail` (section 8).

### 4.4 Return

`GET /payments/{payment}/return`, named `payments.return`, behind `auth` and a `PaymentPolicy::view` check (someone else's payment answers 404).

1. Query parameters from the provider are ignored. The payment row already knows its reference.
2. If the payment isn't pending, skip to step 4 and redirect according to its current status.
3. Call the gateway's `confirm`:
   - **`Succeeded`:** `CompletePayment` runs (section 6.1).
   - **`Declined`:** `ClosePayment` with `failed`, no email.
   - **`Cancelled`:** `ClosePayment` with `cancelled`, no email.
   - **`StillPending`:** the capture id, if given, is stored. Nothing else changes.
4. Redirect:

   | Status | Order payment | Top-up |
   |---|---|---|
   | succeeded | the order's receipt, with the `orderPlaced` flash | the wallet, with the success toast |
   | failed | the basket, with the decline message | the wallet, with the decline message |
   | cancelled or expired | the basket, with the cancel message | the wallet, with the cancel message |
   | still pending | the basket, with the still-waiting message | the wallet, with the still-waiting message |

### 4.5 Cancel

`GET /payments/{payment}/cancel`, named `payments.cancel`, with the same middleware and policy as the return.

The new `CancelPayment` action handles it. If the payment is pending, it calls the gateway's `cancel`:
- **`Cancelled`:** `ClosePayment` with `cancelled`, no email.
- **`Succeeded`:** the shopper paid after all, for example in another tab. `CompletePayment` runs instead.

Then it redirects as in section 4.4, step 4. A cancelled order payment leaves the basket untouched and returns any wallet share.

### 4.6 Stripe webhook

`POST /webhooks/stripe`, named `webhooks.stripe`.
- It is excluded from CSRF in `bootstrap/app.php` and needs no login.
- `StripeWebhookController` verifies the `Stripe-Signature` header. A bad signature, a missing secret or an unparseable payload answers 400.
- The payment is found by `provider_reference`, the session id.
- **`checkout.session.completed`:** if the payment is still pending, it confirms through the gateway and runs `CompletePayment`.
- **`checkout.session.expired`:** `ClosePayment` with `expired`, **with** the email.
- Unknown sessions and other event types answer 200 and are ignored, so Stripe doesn't retry them.

### 4.7 PayPal webhook

`POST /webhooks/paypal`, named `webhooks.paypal`.
- It is excluded from CSRF and needs no login.
- `PayPalWebhookController` checks the event with `PayPalWebhookVerifier`. A failed check answers 400.
- **Finding the payment:** first by `custom_id` in the event's resource. If that's missing, by the PayPal order id: `resource.id` for order events, or `resource.supplementary_data.related_ids.order_id` for capture events.

| Event | What happens |
|---|---|
| `CHECKOUT.ORDER.APPROVED` | The buyer approved but may never come back. If the payment is still pending, Cartopia confirms (captures) it now and runs `CompletePayment` on `Succeeded`. The capture carries the same `PayPal-Request-Id` as the return's, so the two can't both charge. |
| `PAYMENT.CAPTURE.COMPLETED` | If the payment is still pending (its capture was held), it confirms through the gateway and runs `CompletePayment`. |
| `PAYMENT.CAPTURE.DENIED` | `ClosePayment` with `failed`, **with** the email. |
| Anything else, or an unknown payment | Answers 200 and is ignored. |

### 4.8 Stale payments

The artisan command `payments:expire-stale`, scheduled every 15 minutes in `routes/console.php`:
- It runs `ClosePayment` with `expired`, **with** the email, for payments that are still `pending` more than 3 hours after they were created.
- It skips payments that already have a `provider_payment_id`: those are PayPal captures held by PayPal, which its webhook settles.
- It doesn't call the providers. Stripe sessions expire on their own after 30 minutes, and Cartopia never captures a PayPal payment that is no longer pending.

## 5. Splitting a payment

`App\Payments\SplitPlan` is a small value object with `walletCents` and `hostedCents`, built by `SplitPlan::for(Money $total, Money $balance, bool $useWallet)`:

- **No split:** `useWallet` is false, or the balance is zero, or the balance covers the total. The wallet pays 0 and the hosted method pays the total.
- **Split:** the balance is above zero but below the total, and `useWallet` is true.
  - The hosted part is the larger of what's missing (`total − balance`) and $0.50.
  - The wallet part is the total minus the hosted part.
  - If that leaves the wallet part at 0 or below (the total is $0.50 or less), there's no split.

Examples:

| Total | Balance | Wallet pays | Card or PayPal pays |
|---|---|---|---|
| $4.34 | $2.10 | $2.10 | $2.24 |
| $4.34 | $4.00 | $3.84 | $0.50 |
| $4.34 | $0.00 | $0.00 | $4.34 |
| $0.40 | $0.20 | none | not possible (below $0.50) |

The server always works the split out again from the locked balance. The client works out the same numbers with a matching function in `modules/cart/money.ts`, to show them before the shopper clicks. If the balance changed in between, the hosted page shows the server's numbers.

## 6. Idempotency and races

The money moves once at the provider. Cartopia's side can be triggered more than once, by the return and a webhook arriving together, a refreshed return page, or a webhook delivered twice. Three layers keep that safe.

### 6.1 Fulfilment

The `CompletePayment` action runs in one transaction:
1. It locks the payment row (`lockForUpdate`) and reloads it.
2. **If it is already `succeeded`,** it returns without doing anything.
3. It checks that the outcome's amount and currency match the row. A mismatch is logged as an error and the payment is closed as `failed` through `ClosePayment`, with no email. Nothing is fulfilled.
4. If the payment is not pending (it was closed meanwhile), it follows the "money wins" rule in section 6.3.
5. It marks the payment `succeeded`, and stores `provider_payment_id`, the card details and `completed_at`.
6. **For an order:** the order becomes `Placed`, the basket is emptied, and `OrderReceiptMail` is queued.
7. **For a top-up:** `Wallet::credit` runs for the amount, with the payment attached, and `TopUpReceiptMail` is queued.

Database constraints back the lock up:
- The unique `wallet_transactions.payment_id` stops a double credit.
- The unique (`method`, `provider_reference`) ties one session or PayPal order to one payment.

### 6.2 Calls to the providers

- Every Stripe request that writes sends an `Idempotency-Key`, and every PayPal create or capture sends a `PayPal-Request-Id`. Both are built from the payment id and the step (`payment-42-start`, `payment-42-capture`). A retry after a timeout gets the first answer back instead of a second session or a second charge.
- Cartopia never calls `confirm` for a payment that isn't pending.
- PayPal's `ORDER_ALREADY_CAPTURED` is read as the existing capture.

### 6.3 Races

| Race | What happens |
|---|---|
| Return and webhook at the same moment | Both reach `CompletePayment`; the row lock lets one fulfil and the other returns early. |
| PayPal approval webhook and the return | Both capture with the same `PayPal-Request-Id`; PayPal charges once and both get the same capture back; the row lock fulfils once. |
| A refreshed return page | The payment is no longer pending, so the shopper is only redirected. |
| A webhook delivered twice | The second finds the payment settled and does nothing. |
| Cancel in one tab, paid in another (Stripe) | Expiring the session fails because it's complete, so `cancel` confirms and the order is placed. |
| The stale job and a late PayPal return | Both lock the row. If the job wins, the payment is `expired` and the return never captures, so no money is taken. |
| The provider confirms money for a payment Cartopia had closed | Prevented by design: Stripe sessions are expired at Stripe before the row closes, or have expired there long before the 3-hour job; PayPal is never captured once closed. If it happens anyway, money the provider confirms wins: the payment becomes `succeeded` and the order `Placed`, but the basket is left alone (the shopper may have filled it again). For a split order, the returned wallet share is debited again if the balance allows; otherwise it's logged at critical level for the admin panel. |
| A double click on "Place order" | The button disables itself while the request runs, and `throttle:10,1` stays. A second request that got through only creates a second unpaid order (and a second wallet hold, for a split). That expires and returns on its own. |

**Emails are sent exactly once** because each is queued inside the single locked state change that causes it, and the mailables are dispatched after commit. A rolled-back transaction sends nothing.

## 7. What the shopper sees

The mockup each item matches is named in brackets.

### 7.1 Basket: "Pay with" [Basket, Basket: wallet too low, Basket: split]

`PayWithOptions.vue` gains the Stripe and PayPal option cards below the wallet card, inside the same fieldset. The three are radios bound to the form's `payment_method`.

- **Stripe card:** the "stripe" wordmark drawn as text, in `#635BFF` in light mode and `#A39DFF` in dark mode, over "Credit or debit card".
- **PayPal card:** the "PayPal" wordmark drawn as text: "Pay" in `#003087` in light mode and `#8FB4FF` in dark mode, "Pal" in `#009CDE`. Under it, "Pay with your PayPal account".
- The wordmark colours become new tokens in `resources/css/app.css`, `brand-stripe`, `brand-paypal` and `brand-paypal-light`, with light and dark values, following the redesign's token rule.
- Only the methods in the shared `paymentMethods` prop are shown.
- **Below $0.50,** the Stripe and PayPal cards are disabled with "Stripe and PayPal payments start at $0.50."

**The wallet card has three states:**

| Balance | Wallet card | Default choice |
|---|---|---|
| Covers the total | A normal option card: "Balance $115.26" | Wallet |
| Above zero but short, with a hosted method available | It becomes a switch card [Basket: wallet + card split]: a wallet icon in a marigold tile, the title "My $2.10 balance" (the whole balance, also under the 50-cent rule), the note "Switch on, then pay the rest with Stripe or PayPal below.", and a sticker-style switch on the right (outlined pill, hard shadow, marigold knob with a check), on by default. The card has the selected fill while the switch is on. The switch's accessible name is "Use my $2.10 balance". It isn't a radio in this state: the radios are only Stripe and PayPal. | Stripe, with the switch on |
| Zero, or short with no hosted method available | Disabled, as today: "Balance $2.10. You need $2.24 more.", with the "Top up your wallet" link | Stripe, if available. Otherwise nothing is chosen and "Place order" stays disabled, as today. |

**Under the total,** when a split is on, a small line gives the exact split: "Wallet $2.10 · Stripe $2.24" (or "PayPal"). Under the 50-cent rule it shows the real shares, for example "Wallet $3.84 · Stripe $0.50".

**The main button follows the choice:**

| Choice | Button |
|---|---|
| Wallet | "Place order and pay $X" |
| Stripe | "Pay $X with Stripe", where $X is the hosted part when split |
| PayPal | "Pay $X with PayPal", the same |

The phone bar's button keeps the text "Place order". While the request runs, both buttons are disabled.

### 7.2 Wallet: "Add credit" [Wallet]

- Under the amount, a "Pay with" fieldset holds the Stripe and PayPal option cards, drawn as on the canvas. Stripe is picked by default.
- The button reads "Continue to pay $X", or "Continue to pay" while the amount is empty or invalid.
- If no hosted method is available, the fieldset is replaced by "Stripe and PayPal payments aren't set up on this copy of Cartopia." and the button is disabled.
- The comment in `TopUpForm.vue` about free credit goes.

### 7.3 Receipt [Receipt: wallet, Receipt: card, Receipt: PayPal, Receipt: split]

`OrderResource` gains a `payment` object: `method`, `paid_with`, `reference` (shortened for display) and `slip_lines` (label and value pairs for the bottom of the slip). The page renders:

| | Wallet | Stripe | PayPal | Split (wallet + Stripe) |
|---|---|---|---|---|
| Banner after checkout | "Order placed. $4.34 was paid from your wallet." | "Order placed. Your Stripe payment of $2.90 was approved." | "Order placed. Your PayPal payment of $11.00 was approved." | "Order placed. $2.10 came from your wallet and your Stripe payment of $2.24 was approved." (or "…your PayPal payment of $2.24 was approved.") |
| Paid with | Cartopia wallet | Visa ending in 4242, through Stripe | PayPal | Cartopia wallet $2.10 + Visa ending in 4242 $2.24 |
| Payment ref. | (no row) | Stripe payment pi_3Q8x…7Kd2 | PayPal capture 7XK21…48N | as for the card or PayPal part |
| End of the slip | Wallet balance after $110.92 | Paid through Stripe $2.90 | Paid through PayPal $11.00 | Paid from wallet −$2.10, Paid through Stripe $2.24 (or Paid through PayPal), Wallet balance after $0.00 |

- **Card brand:** capitalised as the card network writes it: Visa, Mastercard, American Express, Discover. Any other brand is capitalised as given.
- **Payment reference:** shortened to its first 8 and last 4 characters, joined by an ellipsis. The full id is in the element's `title` and in an accessible label.
- **Icons:** a wallet, a card or an external-link icon, as drawn on the canvas.
- **Data:** the banner text comes from the `orderPlaced` flash, which now carries the full sentence. "Wallet balance after" comes from the order's `Purchase` ledger row, as `balance_after` does today.

### 7.4 Orders list and ledger [Orders, Wallet]

- The order card's tag reads "Paid with wallet", "Paid with Stripe", "Paid with PayPal", "Wallet + Stripe" or "Wallet + PayPal".
- Ledger rows read "Top-up with Stripe" or "Top-up with PayPal" for paid top-ups, and "Order #5 not paid, balance returned" for a release, which is shown as a credit.

### 7.5 Messages

All of these are toasts, plus the inline `checkoutError` spot on the basket for order payments:

| When | Message |
|---|---|
| The shopper cancels on the provider's page | "Payment cancelled. Nothing was charged." For a split, the toast also says "Your $2.10 wallet balance is back." |
| PayPal declines on return | "PayPal couldn't take the payment. Try again or pick another way to pay." |
| The provider can't be reached when starting | "We couldn't reach Stripe. Try again in a moment." (or "PayPal") |
| The payment is still pending on return | "We're still waiting for the payment to be confirmed. Check your orders in a minute." |
| The hosted part would be below $0.50 | "Stripe and PayPal payments start at $0.50." |
| A top-up succeeds | "Added $25.00 to your wallet." |

## 8. Emails

### 8.1 The three emails

All three are queued mailables (`ShouldQueue`) that are dispatched after the database commit (`afterCommit`). Each has a Blade HTML version and a plain-text version.

| Email | Subject | Sent when | Holds |
|---|---|---|---|
| `OrderReceiptMail` | "Your Cartopia Order #5 Is Confirmed!" | An order becomes `Placed`: by wallet in `PlaceOrder`, by card, PayPal or split in `CompletePayment` | The receipt page's content: items, quantities and prices, subtotal, delivery, total, how it was paid (both parts for a split), the payment reference, the wallet balance after (when the wallet paid any of it), and a "View your order" link |
| `TopUpReceiptMail` | "You added $25.00 to your Cartopia wallet" | A paid top-up is credited in `CompletePayment` | The amount, card or PayPal, the payment reference, the new balance, and a "Go to your wallet" link |
| `PaymentProblemMail` | "Payment Not Completed – Cartopia Order #5" (or "Payment Not Completed – Cartopia Top-up of $25.00") | A payment ends badly **while the shopper isn't on the page**: expired by the stale job or Stripe's expiry webhook, or denied by PayPal's webhook | What wasn't paid, "Nothing was charged.", the returned wallet share for a split, "Your basket is still there." (for orders), and a link back to the basket or wallet |

A shopper who cancels on the provider's page, or sees a decline on return, gets the on-screen message and no email.

The approved wording (canvas, Emails page), verbatim. Each email shows a title, then an intro line under it:

| Email | Title | Intro |
|---|---|---|
| Order receipt (wallet, Stripe, PayPal or split) | "Your order is confirmed!" | "Thank you for shopping at Cartopia. We've successfully received your order, and we're happy to share your purchase details below." |
| Top-up receipt | "$25.00 is in your wallet" | "Your top-up went through. It is ready to spend at checkout." |
| Payment problem, order | "Payment not completed" | "We couldn't complete your payment for this order. Don't worry — your basket is saved, and you can try again whenever you're ready." |
| Payment problem, top-up | "Payment not completed" | "We couldn't complete your payment for this top-up. Nothing was charged, and you can try again from your wallet whenever you're ready." |

- **Receipt rows** under the slip: "Paid with", "Payment ref.", and for a split "Paid from wallet" and "Paid through Stripe" (or PayPal), then "Wallet balance after" when the wallet paid any of it. The slip's top line is "Order #5 · Pickup" (or the delivery method) and the date.
- **Top-up rows:** "Amount", "Paid with", "Payment ref.", "New balance".
- **Problem email (order):** an "Order" row ("#5 · $4.34"), then ticks: "Nothing was charged.", "$2.10 has been restored to your wallet." (split only), "Your basket is still there.". The top-up version shows an "Amount" row and the "Nothing was charged." tick.
- **Buttons:** "View your order", "Go to your wallet", "Back to your basket" (or "Go to your wallet" for a top-up problem).
- **Footer:** "Cartopia, a small online market for everyday staples." and "You're getting this email because you have a Cartopia account."

### 8.2 Look and delivery

- **Look:** the templates live in `resources/views/mail/`, with a shared layout. They use inline CSS in the Market Stall colours (paper, surface, ink, stall green, marigold).
  - **Type:** Georgia stands in for Young Serif in headings and prices, and a system sans for the rest, because web fonts are unreliable in email.
  - **Layout:** the receipt email uses a paper-slip block like the receipt page. It needs no images, apart from the logo mark served from `public/`.
  - **Dark mode:** a `prefers-color-scheme: dark` block switches to the dark tokens in the mail apps that honour it.
- **From:** "Cartopia <shop@cartopia.test>", from `MAIL_FROM_ADDRESS` and `MAIL_FROM_NAME`.
- **Locally:** your `.env` uses Laragon's Mailpit (`MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`), and the inbox is at `http://localhost:8025`. Emails reach Mailpit only while `php artisan queue:work` is running.
- **In `.env.example` and CI:** the `log` mailer stays.

## 9. Security

- **Server-side confirmation:** the return URL only triggers a check with the provider. Visiting it, or forging its query string, can never mark a payment as paid.
- **Amount checks:** the amount and currency the provider reports are compared to the payment row before anything is fulfilled.
- **Ownership:** a payment's return and cancel routes check that it belongs to the signed-in user.
- **Webhooks:** both have no login and no CSRF token, so they trust only a verified signature: Stripe's signing secret, and PayPal's verification API with the webhook id.
- **Sandbox only:** Stripe refuses live keys, and PayPal can only reach the sandbox host (sections 3.2 and 3.3).
- **Logging:** provider errors are logged with the payment id and the provider's error code, never with keys or tokens.
- **Card data:** none touches Cartopia. Only the brand and last 4 digits that Stripe returns are stored.
- **Emails:** emails only ever go to the account's own address.

## 10. Code structure

- **`app/Enums/`:**
  - new: `PaymentMethod`, `PaymentPurpose` and `PaymentStatus`
  - changed: `OrderStatus` and `WalletTransactionType`
- **`app/Payments/`:**
  - `PaymentGateway`, `PaymentGateways`, `StripeGateway`, `PayPalGateway`, `PayPalWebhookVerifier` and `FakePaymentGateway`
  - `SplitPlan`
  - the value objects `PaymentRequest`, `PaymentLine`, `StartedPayment` and `PaymentOutcome`
  - the exceptions `PaymentProviderUnavailable`, `PaymentProviderRejected` and `AmountTooSmall`
- **`app/Actions/`:**
  - new: `StartOrderPayment`, `StartTopUpPayment`, `CompletePayment`, `ClosePayment` and `CancelPayment`
  - changed: `PlaceOrder` sets `payment_method` and `wallet_amount`, and queues the receipt email
  - removed: `TopUpWallet`
- **`app/Http/Controllers/`:**
  - new: `PaymentReturnController`, `PaymentCancelController`, `StripeWebhookController` and `PayPalWebhookController`
  - changed: `CheckoutController` and `TopUpController` branch on the method
- **`app/Policies/`:** a new `PaymentPolicy`. `OrderPolicy::view` hides orders that aren't placed.
- **`app/Console/Commands/`:** `ExpireStalePayments`.
- **`app/Mail/`:** `OrderReceiptMail`, `TopUpReceiptMail` and `PaymentProblemMail`, with their templates in `resources/views/mail/`.
- **Front end:**
  - `modules/cart/PayWithOptions.vue`, `CheckoutPanel.vue` and `money.ts` (the split function)
  - `modules/wallet/TopUpForm.vue` and `LedgerTable.vue`
  - `pages/orders/OrderShowPage.vue` and `OrdersIndexPage.vue`
  - a shared `modules/payments/` folder holding the wordmarks and the method labels
- **Shared props:** `HandleInertiaRequests` shares `paymentMethods`, the list of available hosted methods.

## 11. Local setup

Documented in the README.

- **Stripe:**
  - Put the test secret key in `STRIPE_SECRET`.
  - For the webhook, run `stripe listen --forward-to https://cartopia.test/webhooks/stripe --skip-verify` and put the printed signing secret in `STRIPE_WEBHOOK_SECRET`. The self-signed certificate needs `--skip-verify`.
- **PayPal:**
  - Put the sandbox app's client id and secret in `PAYPAL_CLIENT_ID` and `PAYPAL_SECRET`.
  - For the webhook, run Laragon's ngrok, `ngrok http https://cartopia.test --host-header=cartopia.test`, ideally on the free plan's one static domain.
  - In the PayPal Developer dashboard, add a webhook on the sandbox app for `https://<domain>/webhooks/paypal` with the three events in section 4.7, and put its id in `PAYPAL_WEBHOOK_ID`.
- **Mail:** start Mailpit from Laragon, set the `MAIL_*` lines in section 8.2, and run `php artisan queue:work`.
- **Scheduler:** run `php artisan schedule:work` for stale payments to expire.

## 12. Testing

### 12.1 Feature and unit tests (Pest)

- **`StripeGateway`**, through a fake implementation of stripe-php's HTTP client interface:
  - the session request carries the right line items, the delivery line, the single split line, the URLs, `expires_at`, the metadata and the idempotency key
  - `confirm` reads paid, open and expired sessions, plus the card brand and last 4 digits
  - `cancel` handles a session that is already complete
  - live keys are refused
- **`PayPalGateway`**, through `Http::fake`:
  - the token is fetched once and cached
  - the order request carries the breakdown, the items, the experience context and `PayPal-Request-Id`
  - a capture is read as completed, pending, declined, already captured (followed by a fetch) or not approved yet
  - connection errors and 5xx answers become `PaymentProviderUnavailable`
- **`PayPalWebhookVerifier`:**
  - it sends the raw body byte for byte
  - it reads SUCCESS and FAILURE
  - a missing webhook id fails
- **`SplitPlan`:** every row of the table in section 5, plus a balance that exactly covers the total.
- **Starting a payment**, with the fake gateway:
  - it creates an `AwaitingPayment` order and a pending payment with the right amount, and redirects to the hosted URL
  - a split debits the wallet share and links it to the order
  - a provider outage rolls everything back, including the wallet debit
  - an empty basket, a hosted part below $0.50 and a method that isn't available are each refused
  - a top-up outside $5 to $500 fails validation
- **The return:**
  - success places the order, empties the basket and flashes the right sentence for each method and for a split
  - a top-up credits the wallet once, with the right description
  - a decline marks the payment failed, keeps the basket and returns a wallet share
  - still pending changes nothing apart from storing a capture id
  - someone else's payment answers 404
- **Closing:**
  - `ClosePayment` cancels the order and returns the wallet share exactly once, even when called twice
  - it emails only when asked
- **Idempotency:**
  - a second return, a duplicate webhook, and a return together with a webhook each fulfil only once
  - the unique `payment_id` on the ledger rejects a second credit
- **The Stripe webhook:**
  - a valid signature completes the payment, and a bad one or a missing secret answers 400
  - an expired event closes the payment and emails
  - unknown sessions and events answer 200
- **The PayPal webhook:**
  - a failed verification answers 400
  - an approval with no return captures and places the order
  - an approval racing the return captures once
  - a pending capture followed by its completed event places the order
  - a denied capture closes the payment and emails
  - the payment is found by `custom_id`, then by the order id
- **Cancel:**
  - it keeps the basket, cancels the order and returns a wallet share
  - cancel-after-paid places the order
- **`payments:expire-stale`:**
  - it expires only pending payments older than 3 hours, emails, and returns wallet shares
  - it skips held PayPal captures
- **Emails** (`Mail::fake()`):
  - each email is queued exactly once, including under duplicate returns and webhooks
  - nothing is queued when the transaction rolls back
  - the problem notice is not sent for a cancel or decline the shopper saw
  - rendering tests check each email's content for the wallet, card, PayPal and split cases, and that both the HTML and the text versions render
- **Orders:**
  - the list hides awaiting and cancelled orders
  - their receipts answer 404
  - the receipt props are right for each method and for splits
- **Shared props:** `paymentMethods` lists only the configured methods.
- **Existing tests:** the top-up tests that expect free credit are rewritten for the paid flow, and the checkout tests that expect "Place order" to be blocked on a low wallet are updated.

### 12.2 Browser tests (Pest browser plugin)

A `FakePaymentGateway` is bound for the browser suite. Its "hosted page" URL is Cartopia's own return URL (which approves the payment) or its cancel URL, so the journeys run without leaving the site. They cover:
- paying for a basket by card and landing on the card receipt
- the same with PayPal
- cancelling and finding the basket unchanged
- a short wallet turns into the "My $X balance" switch card; paying the rest with Stripe lands on the split receipt and the balance reads $0.00
- turning the switch off makes the card pay everything
- topping up by card and seeing the new balance and ledger row

### 12.3 Manual sandbox walkthrough at `https://cartopia.test`

- **Stripe test cards:**
  - 4242 4242 4242 4242 succeeds
  - 4000 0025 0000 3155 asks for 3-D Secure
  - 4000 0000 0000 0002 is declined; Stripe keeps the shopper on its page
- **PayPal:** a payment with the sandbox buyer account, a cancel from PayPal's page, and a split order.
- **Webhooks:** pay with each provider and close the tab before returning. The order still turns up as placed and the receipt email arrives.
- **Mailpit:** every email in section 8 appears and reads correctly, in light and dark.

### 12.4 Quality

- All tests pass on SQLite and on MySQL.
- Pint, Larastan level 7, Oxlint and vue-tsc stay green, locally and in CI.
- CI runs without any payment keys and with the `log` mailer.

## 13. Phases

Each phase is one branch and one pull request, stacked like the redesign. `main` keeps working after each merge.

1. **Foundations** (`payments/phase-1-foundations`):
   - the enums, the migrations (payments, the new order columns, the ledger's `payment_id`) and the `Payment` model
   - the gateway contract, the value objects, `SplitPlan`, the Stripe, PayPal and fake gateways, the PayPal webhook verifier, the configuration and the live-key guard
   - their tests

   Nothing visible changes.
2. **Checkout by card and PayPal** (`payments/phase-2-checkout`):
   - `StartOrderPayment` without the split, `CompletePayment`, `ClosePayment` and `CancelPayment`
   - the return and cancel routes, both webhooks, and the stale-payments command
   - the Stripe and PayPal option cards, the button labels, the card and PayPal receipts and the Orders tags
   - their feature and browser tests
3. **Split wallet + card** (`payments/phase-3-split`):
   - first, draw Basket: split and Receipt: split on the canvas in light and dark, and get them approved
   - then the wallet hold and release, the "My $X balance" switch card, the 50-cent rule in the UI, the split receipt, the split tag and the release ledger row
4. **Paid top-ups** (`payments/phase-4-top-ups`):
   - `StartTopUpPayment`, the wallet's "Pay with" choice and the ledger copy
   - removing `TopUpWallet` and rewriting its tests
5. **Emails** (`payments/phase-5-emails`):
   - first, draw the three emails on the canvas, and get them approved
   - then the mailables, their templates, their triggers in the actions, the Mailpit setup. `ClosePayment` takes its `notify` flag from phase 2 onwards, and the flag only starts sending once this phase lands.
   - the README section on local setup (section 11)

## 14. Definition of done

- Every item in section 7 matches its canvas mockup in light and dark mode, at desktop and phone widths, and every email in section 8 matches its mockup.
- All tests pass on SQLite and on MySQL, and the quality checks are green.
- The manual sandbox walkthrough in section 12.3 works at `https://cartopia.test`.
- The README explains the local setup in section 11.
- Each phase reaches `main` through a pull request, with no AI attribution in commits or PR bodies.

## 15. Decisions and risks

- **Hosted pages instead of embedded fields.** Card data never touches Cartopia and the checkout layout doesn't change. Risk: the shopper leaves the site and may not come back. Both webhooks settle those payments.
- **Orders exist before they're paid.** The prices are fixed when the shopper clicks, and the order number can travel to Stripe and PayPal. Risk: abandoned orders build up. They're hidden from shoppers and expired by the scheduled command.
- **The wallet share of a split is taken when the shopper clicks, not when the card pays.** The balance can't be spent twice while the shopper is on Stripe or PayPal, and the card never succeeds for an order the wallet can no longer cover. Risk: the balance looks lower for up to 30 minutes (Stripe) or 3 hours (PayPal) on an abandoned payment. It comes back with a "Returned" ledger row and an email.
- **The 50-cent minimum.** Orders under $0.50 can only be paid from the wallet, and a split never charges less than $0.50 by card. Accepted, since Stripe can't charge less.
- **The basket is emptied on success, not on start.** A shopper who cancels keeps their basket. Risk: items added in another tab while paying are emptied with the rest. Accepted, since the wallet flow does the same today.
- **Webhooks need tunnels locally:** the Stripe CLI and ngrok. Without them everything still works through the return; only the "closed the tab" case waits until the shopper returns or the payment expires.
- **PayPal captures held by PayPal** wait for `PAYMENT.CAPTURE.COMPLETED`. Without a PayPal webhook configured, such a payment stays pending until someone looks at it in the admin panel (sub-project 4). Rare in the sandbox.
- **The queue worker and the scheduler must run** for emails to arrive and stale payments to expire. Locally that means `php artisan queue:work` and `php artisan schedule:work`. If they don't run, emails wait in the `jobs` table and pending orders linger; nothing is charged.
- **Emails can't use the store's web fonts.** Georgia and a system sans stand in, which the email mockups account for.
