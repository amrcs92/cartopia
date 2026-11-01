# Cartopia Payments Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let shoppers pay for baskets and wallet top-ups by card (Stripe) or PayPal in sandbox mode, alone or split with a short wallet balance, with both providers' webhooks and emailed receipts and notices.

**Architecture:**
- Each hosted payment is a `payments` row that goes from `pending` to `succeeded`, `cancelled`, `failed` or `expired`. It is driven through one `PaymentGateway` contract with Stripe (stripe-php) and PayPal (Laravel HTTP, Orders v2) implementations.
- Four actions are the only code that changes a payment's state: `StartOrderPayment`, `StartTopUpPayment`, `CompletePayment` and `ClosePayment`. `SettlePayment` and `CancelPayment` route a provider's answer to them. Each locks the payment row, so returns, webhooks and the stale job can race safely.
- The front end reuses the redesign's option cards; the copy lives in one PHP class, `PaymentWording`.

**Tech Stack:**
- Laravel 13, Inertia 3, Vue 3 with TypeScript, Tailwind 4
- `stripe/stripe-php` and the Laravel HTTP client
- Pest 4 with its browser plugin, Larastan level 7, Pint, Oxlint and vue-tsc
- MySQL 8 and SQLite; Mailpit locally

**Spec:** `docs/superpowers/specs/2026-11-01-cartopia-payments-design.md`. Read it before any task; section numbers below refer to it.

## Global Constraints

- **Repository:** `C:\laragon\www\cartopia`. Never use the xampp folder.
- **PHP:** before any `php` or `vendor/bin` command, run `export PATH="/c/laragon/bin/php/php-8.3.36-Win32-vs16-x64:$PATH"`.
- **Commands:**
  - **Feature and unit tests:** `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest --exclude-testsuite=Browser`, or with a path such as `tests/Feature/Payments`.
  - **Browser tests:** run `npm run build` first, then `env -u AI_AGENT -u CLAUDECODE php -d extension=sockets vendor/bin/pest tests/Browser`.
  - **Quality checks:** `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, `npm run lint`, `npm run format:check` and `npm run types`.
- **Database:** MySQL80 on port 3306, with the credentials in `.env`. Never use Laragon's MySQL. Tests use SQLite unless told otherwise.
- **Git:**
  - Every phase is one branch. Phase 1 branches from `payments/design`; each later phase branches from the previous phase's branch.
  - Commit messages follow the repository's style: a short imperative sentence such as "Add the payments table", with no `feat:` prefix.
  - Never add `Co-Authored-By` or any AI attribution to commits or PR bodies.
  - Push right after every commit (`git push`). Never force-push without the user's explicit permission.
- **One-time messages:** use Inertia 3's native flash (`Inertia::flash(...)`), never session flash props.
- **Money:**
  - Always `App\Support\Money`, held as integer cents.
  - The currency is `usd` and never anything else.
  - Shown as `Money::format()` / `formatCents()` ("$1,234.56").
- **Limits:**
  - Hosted payments are at least `PaymentGateways::MIN_CENTS = 50`.
  - Top-ups are 500 to 50000 cents.
  - Stripe sessions expire after 30 minutes.
  - Stale payments expire after 3 hours, checked every 15 minutes.
- **Idempotency keys:** always the format `payment-{id}-{step}`, where the step is `start`, `cancel` or `capture`.
- **Sandbox only:**
  - Stripe refuses secrets starting `sk_live_` or `rk_live_`.
  - The PayPal base URL is the constant `https://api-m.sandbox.paypal.com`.
- **Copy:**
  - Use the spec's exact strings (sections 7 and 8).
  - Use the words basket, wallet, top up, log in and receipt. "Card" is the shopper's word for Stripe.
  - No emoji. Prices always show two decimals.
- **UI:**
  - Colours only through tokens in `resources/css/app.css`, with light and dark values, never raw hex in components.
  - Inputs are at least 16px and touch targets at least 44 × 44px.
  - Every new screen works in light and dark mode and at 360px wide.
- **Configuration:** a hosted method is available only when its credentials are set. CI has no payment keys and uses the `log` mailer, so every test fakes the gateways.

## Review Focus

1. **The basket changes in another tab while the shopper is on Stripe or PayPal.** The order keeps what was priced at the click, and the whole basket is emptied on success, as the spec accepts. Pinned in Task 10.
2. **A product is deleted, or its price changes, between start and completion.** Completion never reprices or reloads products; the order's snapshot stands. Pinned in Task 10.
3. **The session expires while the shopper is away, so the return URL hits `auth` as a guest.** They log in and land back on the return URL, which then completes the payment. Pinned in Task 11.
4. **PayPal amounts need the right shape.** They are strings with exactly two decimals and no thousands separator: `"0.50"`, `"1234.00"`. Pinned in Task 6.
5. **Long product names on PayPal.** PayPal item names over 127 characters are cut to 127, so a long product name doesn't make PayPal reject the order. Pinned in Task 6.

---

# Phase 1: Foundations (`payments/phase-1-foundations`)

Nothing visible changes in this phase. Start with `git switch payments/design && git switch -c payments/phase-1-foundations`.

### Task 1: Payment enums and new enum cases

**Files:**
- Create: `app/Enums/PaymentMethod.php`, `app/Enums/PaymentPurpose.php` and `app/Enums/PaymentStatus.php`
- Modify: `app/Enums/OrderStatus.php` and `app/Enums/WalletTransactionType.php`
- Test: `tests/Unit/PaymentEnumsTest.php`

**Interfaces:**
- Produces:
  - `PaymentMethod::{Wallet='wallet', Stripe='stripe', PayPal='paypal'}` with `label(): string` and `isHosted(): bool`, plus the static `hosted(): list<self>`, which returns `[Stripe, PayPal]`.
  - `PaymentPurpose::{Order='order', TopUp='top_up'}`.
  - `PaymentStatus::{Pending, Succeeded, Cancelled, Failed, Expired}` (lower-case values).
  - `OrderStatus::{AwaitingPayment='awaiting_payment', Cancelled='cancelled'}`.
  - `WalletTransactionType::Release='release'`, labelled "Returned".

- [ ] **Step 1: Write the failing test**

```php
it('labels payment methods the way shoppers say them', function () {
    expect(PaymentMethod::Wallet->label())->toBe('Wallet')
        ->and(PaymentMethod::Stripe->label())->toBe('Card')
        ->and(PaymentMethod::PayPal->label())->toBe('PayPal')
        ->and(PaymentMethod::hosted())->toBe([PaymentMethod::Stripe, PaymentMethod::PayPal])
        ->and(PaymentMethod::Wallet->isHosted())->toBeFalse();
});

it('has the new order and ledger cases', function () {
    expect(OrderStatus::from('awaiting_payment'))->toBe(OrderStatus::AwaitingPayment)
        ->and(OrderStatus::from('cancelled'))->toBe(OrderStatus::Cancelled)
        ->and(WalletTransactionType::Release->label())->toBe('Returned')
        ->and(PaymentStatus::from('expired'))->toBe(PaymentStatus::Expired)
        ->and(PaymentPurpose::from('top_up'))->toBe(PaymentPurpose::TopUp);
});
```

- [ ] **Step 2: Run the test** with `pest tests/Unit/PaymentEnumsTest.php`. It should fail with a class-not-found error.
- [ ] **Step 3: Add the three enums and the new cases** in the style of `WalletTransactionType` (string-backed, with `label()` as a `match`).
- [ ] **Step 4: Run the test again.** It should pass.
- [ ] **Step 5: Commit and push:** "Add the payment enums and the new order and ledger cases".

### Task 2: The payments table, the new columns and the `Payment` model

**Files:**
- Create:
  - `database/migrations/2026_11_02_000001_create_payments_table.php`
  - `database/migrations/2026_11_02_000002_add_payment_columns_to_orders_table.php` (`payment_method` defaulting to `wallet`, `wallet_amount` unsigned integer defaulting to 0)
  - `database/migrations/2026_11_02_000003_add_payment_id_to_wallet_transactions_table.php` (nullable, unique, `nullOnDelete`)
  - `app/Models/Payment.php` and `database/factories/PaymentFactory.php`
- Modify:
  - `app/Models/Order.php`: fillable, casts, `payments(): HasMany`, `succeededPayment(): HasOne` (where `status = succeeded`, `latestOfMany()`), `isSplit(): bool`, `scopePlaced()`
  - `app/Models/User.php`: `payments(): HasMany`
  - `app/Models/WalletTransaction.php`: fillable `payment_id`, `payment(): BelongsTo`
  - `app/Wallet/Wallet.php`: `credit()` signature
  - `database/factories/OrderFactory.php`: `awaitingPayment()` state, and `payment_method => wallet`, `wallet_amount => total` by default
- Test: `tests/Feature/Domain/PaymentModelTest.php`, and additions to `tests/Feature/Domain/SchemaTest.php`

**Interfaces:**
- Produces:
  - The `Payment` model: the columns in spec section 2.2; casts `purpose`, `method` and `status` (enums), `amount` (`MoneyCast`) and `completed_at` (datetime); `user(): BelongsTo`, `order(): BelongsTo`, `isPending(): bool` and `scopePending()`.
  - `PaymentFactory` states: `forOrder(Order $order)` (the purpose `order`, the amount is the order total minus `wallet_amount`, the user is the order's user), `topUp(int $cents)`, `stripe()`, `paypal()`, `withReference(string $ref)`, `succeeded()`.
  - `Wallet::credit(User $user, Money $amount, WalletTransactionType $type, ?string $description = null, ?Order $order = null, ?Payment $payment = null): WalletTransaction`. `apply()` gains the same two optional arguments and stores `order_id` and `payment_id`.

- [ ] **Step 1: Write the failing tests**

```php
it('belongs to a user and an order', function () {
    $order = Order::factory()->awaitingPayment()->create();
    $payment = Payment::factory()->forOrder($order)->stripe()->create();

    expect($payment->order->is($order))->toBeTrue()
        ->and($payment->user->is($order->user))->toBeTrue()
        ->and($payment->status)->toBe(PaymentStatus::Pending)
        ->and($payment->isPending())->toBeTrue()
        ->and($order->payments)->toHaveCount(1);
});

it('allows one payment per provider reference', function () {
    Payment::factory()->stripe()->withReference('cs_test_1')->create();
    Payment::factory()->stripe()->withReference('cs_test_1')->create();
})->throws(QueryException::class);

it('credits a payment to the ledger only once', function () {
    $payment = Payment::factory()->topUp(2500)->stripe()->create();
    $wallet = app(Wallet::class);
    $wallet->credit($payment->user, Money::fromCents(2500), WalletTransactionType::TopUp, 'Top-up by card', payment: $payment);
    $wallet->credit($payment->user, Money::fromCents(2500), WalletTransactionType::TopUp, 'Top-up by card', payment: $payment);
})->throws(QueryException::class);

it('reads existing orders as wallet orders', function () {
    $order = Order::factory()->create();

    expect($order->fresh()->payment_method)->toBe(PaymentMethod::Wallet)
        ->and($order->isSplit())->toBeFalse();
});

it('scopes placed orders', function () {
    Order::factory()->create();
    Order::factory()->awaitingPayment()->create();

    expect(Order::query()->placed()->count())->toBe(1);
});
```

In `SchemaTest`, assert the `payments` columns and the unique indexes, in the existing test's style.

- [ ] **Step 2: Run the tests** with `pest tests/Feature/Domain/PaymentModelTest.php tests/Feature/Domain/SchemaTest.php`. They should fail.
- [ ] **Step 3: Write the migrations, the model, the factory and the model changes listed above.** The indexes are those in spec section 2.2.
- [ ] **Step 4: Run the tests again, and the full feature suite.** Everything should pass, with the existing `WalletTest` still green.
- [ ] **Step 5: Commit and push:** "Add the payments table and model".

### Task 3: `SplitPlan` and the payment value objects

**Files:**
- Create:
  - `app/Payments/SplitPlan.php` and `app/Payments/PaymentLine.php`
  - `app/Payments/PaymentRequest.php` and `app/Payments/StartedPayment.php`
  - `app/Payments/Outcomes/PaymentOutcome.php` (an interface), `Succeeded.php`, `StillPending.php`, `Declined.php` and `Cancelled.php`
  - `app/Payments/Exceptions/PaymentProviderUnavailable.php`, `PaymentProviderRejected.php` and `AmountTooSmall.php`
- Test: `tests/Unit/Payments/SplitPlanTest.php` and `tests/Unit/Payments/PaymentRequestTest.php`

**Interfaces:**
- Produces:
  - `final readonly class SplitPlan { public Money $wallet; public Money $hosted; public static function for(Money $total, Money $balance, bool $useWallet): self; public function isSplit(): bool }`
  - `final readonly class PaymentLine { string $name; Money $unitPrice; int $quantity }`
  - `final readonly class PaymentRequest`:
    - fields: `list<PaymentLine> $lines`, `Money $deliveryFee`, `string $description`, `string $returnUrl`, `string $cancelUrl`
    - `total(): Money` (the lines plus the delivery fee)
    - `static single(string $name, Money $amount, string $description, string $returnUrl, string $cancelUrl): self` (one line, no delivery fee)
  - `final readonly class StartedPayment { string $reference; string $redirectUrl }`
  - The outcomes:
    - `Succeeded(string $providerPaymentId, Money $amount, string $currency, ?string $cardBrand = null, ?string $cardLast4 = null)`
    - `StillPending(?string $providerPaymentId = null)`
    - `Declined(string $reason)`
    - `Cancelled()`
  - The three exceptions extend `RuntimeException`.

- [ ] **Step 1: Write the failing tests.** `SplitPlanTest` is a dataset of `[total, balance, useWallet, walletCents, hostedCents]`:

```php
it('splits a total', function (int $total, int $balance, bool $use, int $wallet, int $hosted) {
    $plan = SplitPlan::for(Money::fromCents($total), Money::fromCents($balance), $use);

    expect($plan->wallet->cents())->toBe($wallet)
        ->and($plan->hosted->cents())->toBe($hosted)
        ->and($plan->isSplit())->toBe($wallet > 0);
})->with([
    'short wallet' => [434, 210, true, 210, 224],
    'fifty-cent rule' => [434, 400, true, 384, 50],
    'empty wallet' => [434, 0, true, 0, 434],
    'switch off' => [434, 210, false, 0, 434],
    'wallet covers it' => [434, 434, true, 0, 434],
    'too small to split' => [40, 20, true, 0, 40],
]);
```

`PaymentRequestTest` checks that `total()` adds the lines (unit price × quantity) and the delivery fee, and that `single()` has one line and no delivery fee.

- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write the classes.** `SplitPlan::for` follows spec section 5 exactly. "Wallet covers it" is not a split: the hosted method pays everything because the shopper chose it.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Add the split plan and payment value objects".

### Task 4: The gateway contract, the registry, the fake gateway and the configuration

**Files:**
- Create: `app/Payments/PaymentGateway.php`, `app/Payments/PaymentGateways.php` and `app/Payments/FakePaymentGateway.php`
- Modify:
  - `config/services.php` (spec section 3.5)
  - `.env.example` (the five payment keys, empty; `MAIL_FROM_ADDRESS=shop@cartopia.test`; `MAIL_FROM_NAME=Cartopia`)
  - `app/Providers/AppServiceProvider.php` (`PaymentGateways` as a singleton)
- Test: `tests/Feature/Payments/PaymentGatewaysTest.php`

**Interfaces:**
- Produces:
  - `interface PaymentGateway { start(Payment, PaymentRequest): StartedPayment; confirm(Payment): PaymentOutcome; cancel(Payment): PaymentOutcome; }`. Implementations throw `PaymentProviderUnavailable` or `PaymentProviderRejected`.
  - `class PaymentGateways`:
    - `const MIN_CENTS = 50`
    - `for(PaymentMethod $method): PaymentGateway` throws `InvalidArgumentException` for `Wallet`
    - `available(): list<PaymentMethod>`: Stripe when `services.stripe.secret` is set, PayPal when both `services.paypal.client_id` and `services.paypal.secret` are set
    - `static fake(): FakePaymentGateway` binds a registry in the container in which both hosted methods are available and resolve to one shared fake
  - `class FakePaymentGateway implements PaymentGateway`:
    - `start` returns the reference `fake_{payment id}` and the redirect URL `route('payments.return', $payment)`, or `route('payments.cancel', $payment)` after `redirectToCancel()`.
    - `confirm` returns the next queued outcome, or else `Succeeded("fake_pay_{id}", $payment->amount, 'usd', 'visa', '4242')`. The card details are only filled for a Stripe payment.
    - `cancel` returns the next queued cancel outcome, or else `Cancelled`.
    - Controls: `willConfirm(PaymentOutcome ...$outcomes): static`, `willCancelWith(PaymentOutcome $o): static`, `failToStart(): static` (the next `start` throws `PaymentProviderUnavailable`) and `redirectToCancel(): static`.
    - Inspection: `$started` (a list of `[Payment, PaymentRequest]`), `$confirmed` (a list of payment ids) and `$cancelled`.
  - The `payments.return` and `payments.cancel` routes don't exist until Task 11. In this task the fake builds its URLs with `url("/payments/{$payment->id}/return")` and `url("/payments/{$payment->id}/cancel")`, and Task 11 switches them to `route()`.

- [ ] **Step 1: Write the failing tests**

```php
it('lists only configured methods', function () {
    config(['services.stripe.secret' => null, 'services.paypal.client_id' => null, 'services.paypal.secret' => null]);
    expect(app(PaymentGateways::class)->available())->toBe([]);

    config(['services.stripe.secret' => 'sk_test_x']);
    expect(app(PaymentGateways::class)->available())->toBe([PaymentMethod::Stripe]);

    config(['services.paypal.client_id' => 'id', 'services.paypal.secret' => 'secret']);
    expect(app(PaymentGateways::class)->available())->toBe([PaymentMethod::Stripe, PaymentMethod::PayPal]);
});

it('refuses the wallet as a gateway', function () {
    app(PaymentGateways::class)->for(PaymentMethod::Wallet);
})->throws(InvalidArgumentException::class);

it('fakes both methods with one gateway', function () {
    $fake = PaymentGateways::fake();

    expect(app(PaymentGateways::class)->for(PaymentMethod::PayPal))->toBe($fake)
        ->and(app(PaymentGateways::class)->available())->toBe(PaymentMethod::hosted());
});
```

- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write the contract, the registry, the fake, the configuration and the binding.** The registry resolves `StripeGateway` and `PayPalGateway` lazily from the container, so a missing key never breaks the boot.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Add the payment gateway contract and registry".

### Task 5: `StripeGateway`

**Files:**
- Create: `app/Payments/StripeGateway.php` and `tests/Support/FakeStripeHttpClient.php` (autoload it through `autoload-dev` if `tests/` isn't mapped already)
- Modify: `composer.json`, by running `composer require stripe/stripe-php`
- Test: `tests/Feature/Payments/StripeGatewayTest.php`

**Interfaces:**
- Consumes: Task 3's value objects and Task 4's contract.
- Produces:
  - `StripeGateway::__construct(string $secret)` throws `LogicException` for a secret starting `sk_live_` or `rk_live_`.
  - The static `StripeGateway::verifyWebhook(string $payload, string $signature, ?string $secret): \Stripe\Event` throws `\UnexpectedValueException` or `\Stripe\Exception\SignatureVerificationException`.
  - `FakeStripeHttpClient` implements `\Stripe\HttpClient\ClientInterface` for the installed SDK version. It has `queue(int $status, array $body): static` and records `$requests` (method, URL, headers, params). Tests install it with `\Stripe\ApiRequestor::setHttpClient()` and reset it afterwards.

- [ ] **Step 1: Write the failing tests:**
  - **`it('creates a checkout session')`:** a payment with id 42, two lines (Apple 30 × 2, Cheese Triangle 374 × 1) and a 500 delivery fee. Assert that the recorded request has:
    - `mode=payment` and `payment_method_types[0]=card`
    - three `line_items`, the last being "Delivery" with `unit_amount` 500
    - `currency=usd`, `client_reference_id=42`, `metadata[payment_id]=42`
    - `expires_at` within a second of now plus 1800
    - the header `Idempotency-Key: payment-42-start`

    The returned `StartedPayment` carries the session id and URL from the queued response.
  - **`it('sends a split as one line')`:** `PaymentRequest::single(...)` gives exactly one line item and no Delivery line.
  - **`it('reads a paid session')`:** the expanded charge holds `brand: visa` and `last4: 4242`, giving `Succeeded('pi_123', Money(1274), 'usd', 'visa', '4242')`.
  - **`it('reads open and expired sessions')`:** they give `StillPending` and `Cancelled`.
  - **`it('expires a session on cancel')`:** this checks the `Idempotency-Key: payment-42-cancel` header and the `Cancelled` result. When the expire call answers 400 because the session is complete, a retrieve follows and the result is `Succeeded`.
  - **`it('maps outages')`:** a connection error and a 503 both throw `PaymentProviderUnavailable`.
  - **`it('refuses live keys')`:** both `sk_live_x` and `rk_live_x` throw `LogicException`.
  - **`it('verifies a webhook signature')`:** a payload signed with `"t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", 'whsec_test')` gives an `Event`. A wrong secret and a null secret both throw.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write `StripeGateway`** with `StripeClient`. Requests use `checkout->sessions->create/retrieve/expire` with `['idempotency_key' => ...]`, the expand list `['payment_intent.latest_charge']` and the fields in spec section 3.2. API connection errors become `PaymentProviderUnavailable`. Other `ApiErrorException`s become `PaymentProviderRejected`, except the "already complete" refusal on expire.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Add the Stripe gateway".

### Task 6: `PayPalGateway`

**Files:**
- Create: `app/Payments/PayPalGateway.php`
- Test: `tests/Feature/Payments/PayPalGatewayTest.php`

**Interfaces:**
- Produces:
  - `PayPalGateway::__construct(string $clientId, string $secret)`, with `const BASE_URL = 'https://api-m.sandbox.paypal.com'`.
  - `accessToken(): string` is public, so the webhook verifier can reuse it. It is cached under `paypal.token` until 60 seconds before `expires_in`.
  - `static amount(Money $m): string` formats cents as dollars with two decimals and no thousands separator.

- [ ] **Step 1: Write the failing tests** with `Http::fake` keyed by URL:
  - **`it('fetches a token once')`:** two `start` calls make one `POST /v1/oauth2/token`.
  - **`it('creates an order')`:** the request has:
    - `intent=CAPTURE` and `purchase_units.0.custom_id='42'`
    - `amount.value='12.74'`, `breakdown.item_total.value='7.74'` and `breakdown.shipping.value='5.00'`
    - items carrying `unit_amount.value` and `quantity` as strings
    - `experience_context` holding `return_url`, `cancel_url`, `brand_name=Cartopia`, `user_action=PAY_NOW` and `shipping_preference=NO_SHIPPING`
    - the header `PayPal-Request-Id: payment-42-start`

    The result's URL is the `payer-action` link.
  - **`it('formats amounts for PayPal')`:** `amount()` gives 50 → `'0.50'`, 123400 → `'1234.00'` and 7 → `'0.07'`. (Review Focus 4.)
  - **`it('cuts long item names')`:** a 200-character product name is sent as its first 127 characters. (Review Focus 5.)
  - **`it('captures a completed payment')`:** the header is `PayPal-Request-Id: payment-42-capture`, giving `Succeeded('CAP1', Money(1274), 'usd')`.
  - **`it('reads a held capture as pending')`:** a capture with status `PENDING` gives `StillPending('CAP1')`.
  - **`it('reads a decline')`:** a 422 with issue `INSTRUMENT_DECLINED` gives `Declined`.
  - **`it('reads an order that was already captured')`:** a 422 with `ORDER_ALREADY_CAPTURED` makes the gateway `GET` the order, giving `Succeeded` with the existing capture.
  - **`it('reads an unapproved order as pending')`:** a 422 with `ORDER_NOT_APPROVED` gives `StillPending(null)`.
  - **`it('maps outages')`:** a connection exception and a 500 both throw `PaymentProviderUnavailable`. A 401 throws `PaymentProviderRejected`.
  - **`it('cancels without calling PayPal')`:** `cancel` gives `Cancelled` and sends no request.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write `PayPalGateway`** with `Http::baseUrl(self::BASE_URL)->withToken(...)->withHeaders([...])`, to the request shape in spec section 3.3.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Add the PayPal gateway".

### Task 7: `PayPalWebhookVerifier`

**Files:**
- Create: `app/Payments/PayPalWebhookVerifier.php`
- Test: `tests/Feature/Payments/PayPalWebhookVerifierTest.php`

**Interfaces:**
- Consumes: `PayPalGateway::accessToken()`.
- Produces: `PayPalWebhookVerifier::verify(Request $request): bool`.

- [ ] **Step 1: Write the failing tests:**
  - **`it('sends the raw body unchanged')`:** the raw body `{"id":"WH-1","resource":{"amount":{"value":"1.10"}}}` must reach `/v1/notifications/verify-webhook-signature`'s request body as the exact substring `"webhook_event":{"id":"WH-1","resource":{"amount":{"value":"1.10"}}}`. Assert it with `Http::assertSent` on `$request->body()`. The five `PAYPAL-*` headers map to `auth_algo`, `cert_url`, `transmission_id`, `transmission_sig` and `transmission_time`, and `webhook_id` comes from configuration.
  - **`it('reads success and failure')`:** `SUCCESS` gives true and `FAILURE` gives false.
  - **`it('fails without a webhook id')`:** it returns false, sends no request, and logs a warning.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write it.** Build the JSON body by hand: encode the other fields with `json_encode`, then splice `$request->getContent()` in as `webhook_event`, and send it with `withBody($json, 'application/json')`.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Add the PayPal webhook verifier".

### Task 8 (phase gate): Verify and open the phase PR

- [ ] **Step 1: Run the feature suite, `phpstan analyse` and `pint --test`.** All should be green. Also run the feature suite once against MySQL: set `DB_CONNECTION=mysql DB_DATABASE=cartopia_testing` in the environment for that run, as the redesign did.
- [ ] **Step 2: Open the PR** with `gh pr create --base main --head payments/phase-1-foundations --title "Payments, phase 1: foundations"`. The body summarises the phase and links the spec. It includes the spec and plan commits from `payments/design`, and has no AI attribution.

---

# Phase 2: Checkout by card and PayPal (`payments/phase-2-checkout`)

Start with `git switch -c payments/phase-2-checkout` from phase 1's branch.

### Task 9: Wallet orders record their method; hide orders that aren't placed

**Files:**
- Modify:
  - `app/Actions/PlaceOrder.php`: set `payment_method => Wallet` and `wallet_amount => $total`
  - `app/Http/Controllers/CheckoutController.php`: the flash becomes `PaymentWording::placedMessage($order)`
  - `app/Http/Controllers/OrderController.php`: `index` uses `->placed()`
  - `app/Policies/OrderPolicy.php`
- Create: `app/Payments/PaymentWording.php`, holding `placedMessage()` for now (Task 15 adds the rest)
- Test: `tests/Feature/Http/CheckoutTest.php` and `tests/Feature/Http/OrdersTest.php`

**Interfaces:**
- Produces:
  - `PaymentWording::placedMessage(Order $order): string`. For a wallet order it returns "Order placed. {total} was paid from your wallet." The card and PayPal cases arrive in Task 15, and the split cases in Task 21.
  - `OrderPolicy::view` denies as not found when `status !== Placed`.

- [ ] **Step 1: Update the tests.**
  - In the first `CheckoutTest` test, `hasFlash('orderPlaced', 'Order placed.')` becomes `hasFlash('orderPlaced', 'Order placed. $12.74 was paid from your wallet.')`.
  - Also assert that `$order->payment_method` is `PaymentMethod::Wallet` and `$order->wallet_amount->cents()` is 1274.
  - In `OrdersTest`, add `it('hides orders that are not placed')`: an awaiting order and a cancelled order are missing from `orders`, and `get(route('orders.show', $awaiting))->assertNotFound()`.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Make the changes.**
- [ ] **Step 4: Run them again**, together with the Browser `CheckoutFlowTest`, which still waits for the text "Order placed.". It should pass.
- [ ] **Step 5: Commit and push:** "Record how wallet orders were paid and hide unpaid orders".

### Task 10: Start an order payment, complete it, close it

**Files:**
- Create:
  - `app/Actions/StartOrderPayment.php`, `app/Actions/CompletePayment.php`, `app/Actions/ClosePayment.php` and `app/Actions/SettlePayment.php`
  - `app/Http/Middleware/HandleInertiaRequests.php` gains the shared prop `paymentMethods`
- Modify:
  - `app/Http/Requests/CheckoutRequest.php`: `payment_method` is required and `in:` wallet plus `available()`
  - `app/Http/Controllers/CheckoutController.php`: branch on the method
  - `resources/js/types/shared.ts`: `paymentMethods: ('stripe' | 'paypal')[]`
- Test: `tests/Feature/Payments/StartOrderPaymentTest.php`, `tests/Feature/Payments/CompletePaymentTest.php` and `tests/Feature/Http/SharedPropsTest.php`

**Interfaces:**
- Consumes: `PaymentGateways`, `FakePaymentGateway`, `Cart`, `Wallet` and the outcomes.
- Produces:
  - `StartOrderPayment::handle(User $user, ShippingMethod $method, PaymentMethod $paymentMethod, bool $useWallet = false): StartedPayment`
    - Throws `EmptyCart`, `AmountTooSmall` and the provider exceptions.
    - Builds the request from the order's items, with the description "Cartopia order #{id}" and the return and cancel URLs.
    - In this phase `$useWallet` is ignored. Task 20 implements it.
  - `CompletePayment::handle(Payment $payment, Succeeded $outcome): Payment` follows spec section 6.1. The money-wins branch in step 4 places the order without emptying the basket; Task 20 adds the wallet re-debit.
  - `ClosePayment::handle(Payment $payment, PaymentStatus $status, bool $notify): Payment` follows spec section 4.3, steps 1 to 3. `$notify` is accepted and unused until Task 29; leave a `// sends PaymentProblemMail from phase 5` note. Task 20 adds the release.
  - `SettlePayment::handle(Payment $payment, bool $notifyOnFailure): Payment`:
    - It does nothing unless the payment is pending.
    - It calls `confirm` and routes the outcome: `Succeeded` to `CompletePayment`; `Declined` to `ClosePayment(Failed, $notifyOnFailure)`; `Cancelled` to `ClosePayment(Cancelled, false)`; `StillPending` stores `providerPaymentId` when given.
  - `CheckoutController::store`:
    - For a hosted method, `Inertia::location($started->redirectUrl)`.
    - On `PaymentProviderUnavailable` or `PaymentProviderRejected`, `refused("We couldn't reach {Stripe|PayPal}. Try again in a moment.")` (the provider name comes from the method, Stripe or PayPal).
    - On `AmountTooSmall`, `refused('Stripe and PayPal payments start at $0.50.')`.
  - The shared prop `paymentMethods`: a list of `PaymentMethod` values from `available()`.

- [ ] **Step 1: Write the failing tests.** Set `PaymentGateways::fake()` in a `beforeEach`, and reuse `checkoutSetup()` from `CheckoutTest` by moving it to `tests/Pest.php`. In `StartOrderPaymentTest`:
  - **`it('creates an unpaid order and a pending payment, then redirects')`:**
    - a plain POST with `payment_method=stripe` redirects to `url('/payments/{id}/return')` (`Inertia::location` answers 302 outside Inertia requests and 409 with `X-Inertia-Location` inside them; assert the 409 too, with the `X-Inertia: true` header)
    - `Order::sole()->status` is `AwaitingPayment` with a total of 1274
    - `Payment::sole()` has the amount 1274, the method `Stripe`, the status `Pending` and the reference `fake_{id}`
    - the basket count is still 3
    - the balance hasn't changed
  - **`it('rolls back when the provider is down')`:** `failToStart()`. Then:
    - there are no orders and no payments
    - the response redirects to `cart.show` with the `checkoutError` flash "We couldn't reach Stripe. Try again in a moment."
  - **`it('refuses an empty basket')`:** the `checkoutError` is "Your basket is empty."
  - **`it('refuses a method that is not available')`:** after `config(['services.paypal.client_id' => null])` and with no fake bound, `payment_method=paypal` gives a validation error on `payment_method`.
  - **`it('refuses card payments under 50 cents')`:** a single product priced 30, picked up, with `payment_method=stripe`, gives the `checkoutError` "Stripe and PayPal payments start at $0.50."
  - **`it('passes the order lines to the gateway')`:** `$fake->started[0][1]` has two lines and a delivery fee of 500, its total equals 1274, and its description is "Cartopia order #{id}".

  In `CompletePaymentTest`:
  - **`it('places the order and empties the basket')`:** the payment becomes `Succeeded`, with `provider_payment_id` `fake_pay_{id}`, `card_last4` 4242 and `completed_at` set. The order is `Placed` and the basket is empty.
  - **`it('does nothing the second time')`:** calling `handle` twice leaves one `completed_at` value and doesn't change the order's `updated_at`.
  - **`it('refuses a mismatched amount')`:** an outcome of 1000 against a payment of 1274 marks the payment `Failed`, cancels the order, keeps the basket, and logs an error.
  - **`it('keeps the order as priced when the basket changes meanwhile')`:** after the start, add a third product and change the price of an existing one. After completion, the order's items and total equal the start snapshot and the basket is empty. (Review Focus 1.)
  - **`it('completes after the product was deleted')`:** delete a product after the start; completion still places the order with its snapshot line. (Review Focus 2.)
  - **`it('places an order whose payment was closed, but keeps the basket')`:** call `ClosePayment(Expired)` first, then add an item, then `CompletePayment`. The payment is `Succeeded`, the order `Placed`, and the basket count is 1.

  In `SharedPropsTest`, `paymentMethods` equals `['stripe', 'paypal']` with the fake bound and `[]` without keys.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write the actions, the request rule, the controller branch and the shared prop.** `StartOrderPayment` locks the user exactly as `PlaceOrder` does, and calls `start` inside the transaction so that a throw rolls it back. Each action's transaction begins with `Payment::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail()`.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Start, complete and close order payments".

### Task 11: The return and cancel routes

**Files:**
- Create:
  - `app/Http/Controllers/PaymentReturnController.php` and `app/Http/Controllers/PaymentCancelController.php`
  - `app/Http/Controllers/Concerns/RedirectsAfterPayment.php`
  - `app/Actions/CancelPayment.php` and `app/Policies/PaymentPolicy.php`
- Modify: `routes/web.php` (inside the `auth` group: `payments/{payment}/return` and `payments/{payment}/cancel`, both with `can:view,payment`) and `app/Payments/FakePaymentGateway.php` (switch to `route()`)
- Test: `tests/Feature/Http/PaymentReturnTest.php`

**Interfaces:**
- Consumes: `SettlePayment` and `ClosePayment`.
- Produces:
  - `CancelPayment::handle(Payment $payment): Payment`. When pending, it calls `cancel`: `Succeeded` goes to `CompletePayment`, and anything else to `ClosePayment(Cancelled, false)`.
  - `RedirectsAfterPayment::afterPayment(Payment $payment): RedirectResponse` implements the redirect table in spec section 4.4, step 4, using Inertia flash:
    - an order that succeeded: `orderPlaced` with `PaymentWording::placedMessage($order)`, to `orders.show`
    - otherwise a `toast` (type `error`, or `success` for a top-up) plus `checkoutError` for orders, to `cart.show`
    - top-ups go to `wallet.show`

    The messages are the spec section 7.5 strings. A succeeded top-up's toast is "Added {amount} to your wallet."
  - `PaymentPolicy::view(User $user, Payment $payment): Response` denies as not found.

- [ ] **Step 1: Write the failing tests:**
  - **`it('places the order on return')`:**
    - start by card, then `get(route('payments.return', $payment))`
    - it redirects to `orders.show`
    - the receipt has the flash `orderPlaced` "Order placed. Your card was charged $12.74."
  - **`it('only redirects on a second return')`:** `$fake->confirmed` has one entry after two returns.
  - **`it('closes a declined payment')`:** `willConfirm(new Declined('INSTRUMENT_DECLINED'))` on a PayPal payment makes the payment `Failed`, keeps the basket, and gives the `checkoutError` "PayPal couldn't take the payment. Try again or pick another way to pay."
  - **`it('waits on a pending payment')`:** `willConfirm(new StillPending('CAP1'))` keeps the payment pending with `provider_payment_id` CAP1, and gives the toast "We're still waiting for the payment to be confirmed. Check your orders in a minute."
  - **`it('cancels and keeps the basket')`:** `get(route('payments.cancel', $payment))` makes the payment `Cancelled` and the order `Cancelled`, keeps the basket, and gives the toast "Payment cancelled. Nothing was charged."
  - **`it('places the order when cancel finds it paid')`:** `willCancelWith(new Succeeded(...))` places the order and redirects to the receipt.
  - **`it('hides other shoppers payments')`:** another user's return and cancel URLs both answer 404.
  - **`it('completes after logging in again')`:** a guest `get` of the return URL redirects to `login`. Posting the login form then redirects to the return URL, and following it places the order. (Review Focus 3.)
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write the controllers, the action, the policy, the trait and the routes.** `PaymentWording::placedMessage` gains the card ("Your card was charged {amount}.") and PayPal ("Your PayPal payment of {amount} was approved.") sentences now, using the payment's amount.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Handle the return and cancel from Stripe and PayPal".

### Task 12: The Stripe webhook

**Files:**
- Create: `app/Http/Controllers/StripeWebhookController.php`
- Modify: `routes/web.php` (`POST webhooks/stripe`, named `webhooks.stripe`, no `auth`) and `bootstrap/app.php` (`$middleware->validateCsrfTokens(except: ['webhooks/*'])`)
- Test: `tests/Feature/Http/StripeWebhookTest.php`, with a `stripeSignature(string $payload, string $secret): string` helper in `tests/Pest.php`

**Interfaces:**
- Consumes: `StripeGateway::verifyWebhook`, `SettlePayment` and `ClosePayment`.
- Produces: `__invoke(Request $request): Response`, which answers 200, or 400 for a bad signature, a missing secret or a bad payload.

- [ ] **Step 1: Write the failing tests.** Use the fake gateway, `services.stripe.webhook_secret=whsec_test` and a pending Stripe payment with the reference `cs_test_1`. Post the raw JSON with `$this->call('POST', route('webhooks.stripe'), server: ['HTTP_STRIPE_SIGNATURE' => ...], content: $json)`.
  - **`it('completes a payment')`:** a `checkout.session.completed` event for `cs_test_1` answers 200, and the order is `Placed`.
  - **`it('ignores a duplicate event')`:** the same event twice gives one `confirm` call.
  - **`it('expires a payment')`:** a `checkout.session.expired` event makes the payment `Expired` and the order `Cancelled`.
  - **`it('rejects a bad signature')`:** it answers 400, and the payment is still pending. The same holds with a null `webhook_secret`.
  - **`it('ignores unknown sessions and events')`:** a `cs_unknown` session and a `customer.created` event both answer 200, with no change.
  - **`it('needs no CSRF token or login')`:** the requests above are sent as a guest without a token.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write the controller.** Find the payment with `Payment::query()->where('method', 'stripe')->where('provider_reference', $session->id)->first()`. Use `SettlePayment(notifyOnFailure: true)` for the completed event and `ClosePayment(Expired, notify: true)` for the expired one.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Handle Stripe webhooks".

### Task 13: The PayPal webhook

**Files:**
- Create: `app/Http/Controllers/PayPalWebhookController.php`
- Modify: `routes/web.php` (`POST webhooks/paypal`, named `webhooks.paypal`)
- Test: `tests/Feature/Http/PayPalWebhookTest.php`

**Interfaces:**
- Consumes: `PayPalWebhookVerifier::verify`, `SettlePayment` and `ClosePayment`.
- Produces: `__invoke(Request $request): Response`. Bind a verifier double with `$this->mock(PayPalWebhookVerifier::class)` in the tests.

- [ ] **Step 1: Write the failing tests.** Use a pending PayPal payment with the reference `PP-ORDER-1`.
  - **`it('captures an approved order the shopper never returned from')`:** a `CHECKOUT.ORDER.APPROVED` event with `resource.id=PP-ORDER-1` gives one `confirm` call, and the order is `Placed`.
  - **`it('captures once when the approval races the return')`:** the return and then the webhook give one `confirm` call and one placed order.
  - **`it('finishes a held capture')`:** `willConfirm(new StillPending('CAP1'))` on return, then `PAYMENT.CAPTURE.COMPLETED` with `resource.custom_id={payment id}` after `willConfirm(Succeeded)`, places the order.
  - **`it('closes a denied capture')`:** `PAYMENT.CAPTURE.DENIED` gives `Failed` and a `Cancelled` order.
  - **`it('finds the payment by order id when custom_id is missing')`:** a capture event carrying only `supplementary_data.related_ids.order_id=PP-ORDER-1` still finds the payment.
  - **`it('rejects an unverified event')`:** when the verifier returns false, the webhook answers 400 and nothing changes.
  - **`it('ignores unknown events')`:** they answer 200.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write the controller** to the event table in spec section 4.7. Approved and completed events go through `SettlePayment(notifyOnFailure: true)`; a denied capture goes through `ClosePayment(Failed, notify: true)`.
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Handle PayPal webhooks".

### Task 14: Expire stale payments

**Files:**
- Create: `app/Console/Commands/ExpireStalePayments.php`
- Modify: `routes/console.php` (`Schedule::command('payments:expire-stale')->everyFifteenMinutes()`)
- Test: `tests/Feature/Payments/ExpireStalePaymentsTest.php`

**Interfaces:**
- Produces: the signature `payments:expire-stale`. It calls `ClosePayment(Expired, notify: true)` for each pending payment whose `created_at` is at least 3 hours ago and whose `provider_payment_id` is null, and prints "Expired N payments."

- [ ] **Step 1: Write the failing tests:**
  - **`it('expires only old pending payments')`:** payments created 3 hours 1 minute ago, 2 hours 59 minutes ago, 4 hours ago (but already succeeded) and 4 hours ago (with `provider_payment_id` CAP1). Only the first becomes `Expired`, with its order `Cancelled`, and the output is "Expired 1 payments.".
  - **`it('is scheduled every fifteen minutes')`:** assert through `Schedule::events()` that the command's expression is `*/15 * * * *`.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Write the command and the schedule entry.**
- [ ] **Step 4: Run the tests again.** They should pass.
- [ ] **Step 5: Commit and push:** "Expire stale payments on a schedule".

### Task 15: Receipt and Orders list wording

**Files:**
- Modify:
  - `app/Payments/PaymentWording.php`
  - `app/Http/Resources/OrderResource.php`, which gains `payment`
  - `app/Http/Controllers/OrderController.php`, which eager loads `succeededPayment`
  - `resources/js/types/orders.ts`
  - `resources/js/pages/orders/OrderShowPage.vue`
  - `resources/js/modules/orders/OrderRow.vue`
- Test: `tests/Unit/Payments/PaymentWordingTest.php` and `tests/Feature/Http/OrdersTest.php`

**Interfaces:**
- Produces:
  - `PaymentWording::paidWith(Order $order): string`
  - `PaymentWording::reference(Payment $p): ?array{short: string, full: string}`
  - `PaymentWording::slipLines(Order $order, ?Money $balanceAfter): list<array{label: string, value: string}>`
  - `PaymentWording::tag(Order $order): string`
  - `PaymentWording::cardBrand(?string $brand): string`
  - `OrderResource` gains `payment: {method, paid_with, reference: {short, full}|null, slip_lines, tag}`. The `balance_after` page prop stays.
  - The TS type `OrderPayment` mirrors that, and `Order` gains `payment: OrderPayment`.

- [ ] **Step 1: Write the failing tests.** `PaymentWordingTest` covers the wallet, card and PayPal columns of spec section 7.3 with the spec's own example values:
  - `paidWith` gives "Visa ending in 4242, through Stripe".
  - `reference` turns `pi_3Q8xKLMNOP7Kd2` into the short form "Stripe payment pi_3Q8xK…7Kd2" (the first 8 and last 4 characters of the id), with the full id kept. A PayPal capture `7XK21ABCDE48N` gives "PayPal capture 7XK21ABC…E48N".
  - The slip lines are `[['label' => 'Charged to card', 'value' => '$2.90']]`.
  - The tags are "Paid with wallet", "Paid by card" and "Paid with PayPal".
  - `cardBrand` gives `amex` → "American Express" and `jcb` → "Jcb".

  In `OrdersTest`, check that the receipt's `order.payment.paid_with` is "Cartopia wallet" for a wallet order.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Implement the wording and the resource changes.**
- [ ] **Step 4: Update the front end:**
  - In the receipt slip, the "Paid with" row shows `paid_with` with the method's icon (wallet, card or external-link; add the missing icons to the `Icon` set).
  - A "Payment ref." row shows `reference.short`, with `:title` and an `aria-label` set to the full id.
  - The `slip_lines` replace the "Wallet balance afterwards" row. For a wallet order the line is "Wallet balance after", from `balance_after`.
  - `OrderRow`'s chip shows `order.payment.tag`.
  - Keep `data-test="balance-after"` on the wallet line.
- [ ] **Step 5: Run the tests again, then `npm run types` and `npm run lint`.** All should pass.
- [ ] **Step 6: Commit and push:** "Show how each order was paid on the receipt and the Orders list".

### Task 16: Stripe and PayPal in "Pay with"

**Files:**
- Create: `resources/js/modules/payments/StripeWordmark.vue`, `resources/js/modules/payments/PayPalWordmark.vue` and `resources/js/modules/payments/methods.ts`
- Modify:
  - `resources/css/app.css`: the tokens `brand-stripe` (`#635BFF` / `#A39DFF`), `brand-paypal` (`#003087` / `#8FB4FF`) and `brand-paypal-light` (`#009CDE` in both modes)
  - `resources/js/modules/cart/PayWithOptions.vue` and `CheckoutPanel.vue`
  - `resources/js/composables` or `useCurrentUser` if needed, to read `paymentMethods`

**Interfaces:**
- Produces:
  - `methods.ts` exports `type HostedMethod = 'stripe' | 'paypal'`, `buttonLabel(method: 'wallet' | HostedMethod, amountCents: number): string` (the spec section 7.1 table) and `noteFor(method: HostedMethod): string`.
  - `PayWithOptions` becomes `v-model` on `'wallet' | HostedMethod | null`, with the props `balance`, `balanceCents`, `totalCents` and `methods: HostedMethod[]`.
  - `CheckoutPanel`'s form gains `payment_method`. The default is `wallet` when the balance covers the total; otherwise `methods[0] ?? null`.
  - `canOrder` requires a chosen method, and a hosted choice needs `totalCents >= 50`.

- [ ] **Step 1: Build the wordmarks** as spec section 7.1 draws them: text, with the tokens, and `aria-hidden`. The option card carries a visible "Card" or "PayPal" name for assistive technology through `sr-only`.
- [ ] **Step 2: Extend `PayWithOptions`:**
  - Add one `OptionCard` per available method, with the `data-test` values `pay-stripe` and `pay-paypal`.
  - When `totalCents < 50`, the hosted cards are disabled and the note "Stripe and PayPal payments start at $0.50." is shown.
  - The wallet card's disabled state is unchanged.
- [ ] **Step 3: Update `CheckoutPanel`:**
  - The desktop button text comes from `buttonLabel`.
  - The phone bar stays "Place order".
  - `form.submit(store())`. Inertia follows the 409 location by itself.
- [ ] **Step 4: Run `npm run types`, `npm run lint` and `npm run format:check`.** All should pass. Then build and check the basket by hand at `https://cartopia.test`, in light and dark, at desktop width and at 360px.
- [ ] **Step 5: Commit and push:** "Offer card and PayPal in Pay with".

### Task 17: Browser journeys for card and PayPal

**Files:**
- Modify: `tests/Browser/CheckoutFlowTest.php`
- Create: `tests/Browser/HostedPaymentTest.php`

**Interfaces:**
- Consumes: `PaymentGateways::fake()`. The fake's redirect URL is Cartopia's own return or cancel URL.

- [ ] **Step 1: Check the binding.** Write a smoke test that calls `PaymentGateways::fake()` and then visits the basket of a seeded shopper. If the browser server doesn't see the binding (the "Pay with" cards are missing), add a fallback to `AppServiceProvider`: when `app()->environment('testing')` and `config('payments.fake')` is true, bind `PaymentGateways::fake()`. Set it with `config(['payments.fake' => true])` in the browser tests, and note the reason in a comment.
- [ ] **Step 2: Write the journeys:**
  - **`it('pays by card and shows the card receipt')`:** a balance of 10000, Garden Peas at 1000, UPS. Click `[data-test="pay-stripe"]`, press "Pay $15.00 by card", wait for "Your card was charged $15.00." and see "Visa ending in 4242, through Stripe". The balance is still $100.00.
  - **`it('pays with PayPal')`:** the same with `pay-paypal`, "Pay $15.00 with PayPal" and "Your PayPal payment of $15.00 was approved.".
  - **`it('cancels and keeps the basket')`:** `redirectToCancel()`. See "Payment cancelled. Nothing was charged." and "Garden Peas" still in the basket.
  - **`it('defaults to card when the wallet is short')`:** a balance of 0. The Stripe card is selected and the button reads "Pay $15.00 by card".
  - Each journey ends with `assertNoJavaScriptErrors()`.
- [ ] **Step 3: Run `npm run build` and the browser suite.** It should pass.
- [ ] **Step 4: Commit and push:** "Cover paying by card and PayPal in browser tests".

### Task 18 (phase gate): Verify and open the phase PR

- [ ] **Step 1: Run the full suite, the browser suite, every quality check, and the MySQL run.** Then do the Stripe and PayPal parts of the manual walkthrough (spec section 12.3), with keys in `.env`, `stripe listen`, and ngrok for the PayPal webhook.
- [ ] **Step 2: Open the PR** with `gh pr create --base payments/phase-1-foundations --head payments/phase-2-checkout --title "Payments, phase 2: checkout by card and PayPal"`.

---

# Phase 3: Split wallet + card (`payments/phase-3-split`)

Start with `git switch -c payments/phase-3-split` from phase 2's branch.

### Task 19: Draw the split screens on the canvas and get them approved

**Files:**
- Canvas: https://claude.ai/artifact/GMbSbdvDMisyhnSpqQUmkq. Follow its Design type's instructions to read and publish. Change only these files:
  - `project/Cart.dc.html`: add a `split` boolean prop. The wallet card becomes the "Use my $2.10 balance" switch (on), with the note "Pay the remaining $2.24 by card or PayPal below.", Stripe is selected, the line "Wallet $2.10 · Card $2.24" sits under the total, and the button reads "Pay $2.24 by card".
  - `project/Order.dc.html`: add a `split` variant using the spec section 7.3 split column.
  - New artboards: `CartSplit.dc.html`, `DarkCartSplit.dc.html`, `OrderSplit.dc.html` and `DarkOrderSplit.dc.html`, each a `dc-import` of the above, as `CartLow` and `OrderCard` do. Add them to `canvas.json` on the **Final store** page, beside their siblings.

- [ ] **Step 1: Read `canvas.json`, `Cart.dc.html` and `Order.dc.html`; edit them; publish.**
- [ ] **Step 2: Ask the user to approve the four artboards.** Stop until they approve, and apply any changes they ask for.

### Task 20: The wallet hold, the release and the split rules on the server

**Files:**
- Modify:
  - `app/Actions/StartOrderPayment.php`: use `SplitPlan` and debit the wallet share
  - `app/Actions/ClosePayment.php`: release
  - `app/Actions/CompletePayment.php`: re-debit in the money-wins branch
  - `app/Http/Requests/CheckoutRequest.php`: `use_wallet` is `boolean`
  - `app/Http/Controllers/CheckoutController.php`: pass `$request->boolean('use_wallet')`
- Test: `tests/Feature/Payments/SplitPaymentTest.php`

**Interfaces:**
- Consumes: `SplitPlan::for` and `Wallet::debit`/`credit` with `order:`.
- Produces:
  - The order's `wallet_amount` holds the wallet share. The payment's amount holds the hosted share.
  - For a split, `PaymentRequest::single("Cartopia order #{id} (after {wallet} from your wallet)", ...)`.
  - A release row: type `Release`, the description "Order #{id} not paid, balance returned", and the order id.

- [ ] **Step 1: Write the failing tests.** The total is 1274 throughout.
  - **`it('holds the wallet share when the payment starts')`:**
    - a balance of 500 with `use_wallet=1` by card
    - the order's `wallet_amount` is 500 and the payment's amount is 774
    - the balance is 0
    - a `Purchase` row of −500 with the description "Order #{id}" is linked to the order
    - the fake received one line named "Cartopia order #{id} (after $5.00 from your wallet)" at 774
  - **`it('applies the fifty-cent rule')`:** a balance of 1250 gives a wallet share of 1224 and a payment of 50.
  - **`it('pays everything by card when the switch is off')`:** a balance of 500 with `use_wallet=0` gives a wallet share of 0, a payment of 1274, and a balance still at 500.
  - **`it('returns the wallet share once when the payment closes')`:** `ClosePayment(Cancelled)` twice gives a balance of 500 and exactly one `Release` row, "Order #{id} not paid, balance returned".
  - **`it('returns the share when the provider is down')`:** with `failToStart()`, the balance is 500, there are no orders, and there are no ledger rows besides the seed.
  - **`it('takes the share again when money arrives after closing')`:** close the payment, then `CompletePayment`. The order is `Placed` and the balance is 0. If the balance was spent meanwhile (set it to 100), the order is still placed, the balance stays 100, and a critical log is written.
  - **`it('says the share is back after a cancel')`:** the cancel route's toast is "Payment cancelled. Nothing was charged. Your $5.00 wallet balance is back." Add that sentence to `RedirectsAfterPayment` for split orders.
  - **`it('ignores use_wallet for wallet checkouts')`:** posting `payment_method=wallet` with `use_wallet=1` behaves exactly like a plain wallet order.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Implement the changes**, all inside the existing locked transactions.
- [ ] **Step 4: Run the tests again, and the full feature suite.** Everything should pass.
- [ ] **Step 5: Commit and push:** "Split an order between the wallet and a card or PayPal".

### Task 21: Split wording on the receipt, the Orders list and the ledger

**Files:**
- Modify: `app/Payments/PaymentWording.php`, `resources/js/modules/wallet/LedgerTable.vue`, and the icon and colour handling for `release` rows
- Test: `tests/Unit/Payments/PaymentWordingTest.php`

- [ ] **Step 1: Write the failing tests** for the split column of spec section 7.3, with wallet 210 and card 224:
  - **`placedMessage`:** "Order placed. $2.10 came from your wallet and your Stripe payment of $2.24 was approved." For PayPal: "Order placed. $2.10 came from your wallet and your PayPal payment of $2.24 was approved."
  - **`paidWith`:** "Cartopia wallet $2.10 + Visa ending in 4242 $2.24".
  - **`slipLines`:** with a balance after of 0: `[['Paid from wallet', '-$2.10'], ['Paid through Stripe', '$2.24'], ['Wallet balance after', '$0.00']]`, in label and value form.
  - **`tag`:** "Wallet + Stripe" and "Wallet + PayPal".
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Implement the wording.** `LedgerTable` shows `release` rows as credits, with the "up" icon and the tint the canvas uses for incoming money.
- [ ] **Step 4: Run the tests again, then `npm run types`.** Both should pass.
- [ ] **Step 5: Commit and push:** "Word split payments on receipts, orders and the ledger".

### Task 22: The "My $X balance" switch card

**Files:**
- Modify:
  - `resources/js/modules/cart/money.ts`: `splitPlan()`
  - `resources/js/modules/cart/PayWithOptions.vue` and `CheckoutPanel.vue`
  - `tests/Browser/HostedPaymentTest.php`
- Create: `resources/js/modules/cart/money.test.ts` only if the repository already runs front-end unit tests. Otherwise the browser tests cover it.

**Interfaces:**
- Produces:
  - `splitPlan(totalCents: number, balanceCents: number, useWallet: boolean): { walletCents: number; hostedCents: number }`, which mirrors `SplitPlan::for` exactly.
  - The form gains `use_wallet: boolean`, defaulting to true.

- [ ] **Step 1: Implement the switch to Task 19's approved mockup and spec section 7.1:**
  - The wallet card has the three states.
  - The switch card matches the approved Basket: wallet + card split mockup (spec section 7.1): a wallet icon in a marigold tile, the title "My $2.10 balance" (always the whole balance), the note "Switch on, then pay the rest with Stripe or PayPal below.", and a sticker-style switch on the right. The switch is a real `<input type="checkbox" role="switch">` whose accessible name is "Use my $2.10 balance".
  - The "Wallet $X · Stripe $Y" (or "PayPal") line under the total shows the real shares, and `buttonLabel(method, hostedCents)` follows the spec.
- [ ] **Step 2: Add the browser journeys:**
  - **`it('splits a short wallet with the card')`:** a balance of 500 and Garden Peas at 1000 with pickup. See "My $5.00 balance", press "Pay $5.00 with Stripe", and see "Order placed. $5.00 came from your wallet and your Stripe payment of $5.00 was approved." and "Wallet balance after $0.00".
  - **`it('lets the card pay everything')`:** uncheck the switch, and the button reads "Pay $10.00 with Stripe".
- [ ] **Step 3: Run `npm run types`, `npm run lint`, `npm run build` and the browser suite.** All should pass. Compare the result against the mockups in light and dark, and at 360px.
- [ ] **Step 4: Commit and push:** "Let a short wallet pay part of the order".

### Task 23 (phase gate): Verify and open the phase PR

- [ ] **Step 1: Run all suites and checks.** Then do a manual split with Stripe and with PayPal, plus a cancel that returns the share.
- [ ] **Step 2: Open the PR** with `gh pr create --base payments/phase-2-checkout --head payments/phase-3-split --title "Payments, phase 3: split wallet and card"`.

---

# Phase 4: Paid top-ups (`payments/phase-4-top-ups`)

Start with `git switch -c payments/phase-4-top-ups` from phase 3's branch.

### Task 24: Pay for top-ups

**Files:**
- Create: `app/Actions/StartTopUpPayment.php`
- Modify:
  - `app/Actions/CompletePayment.php`: the top-up branch
  - `app/Http/Requests/TopUpRequest.php`: `payment_method` is required and `in:` `available()`
  - `app/Http/Controllers/TopUpController.php`
- Delete: `app/Actions/TopUpWallet.php`. Move `MIN_CENTS` and `MAX_CENTS` to `StartTopUpPayment`.
- Test: rewrite `tests/Feature/Http/WalletTest.php` and `tests/Feature/Domain/WalletTest.php` where they top up for free

**Interfaces:**
- Produces:
  - `StartTopUpPayment::handle(User $user, Money $amount, PaymentMethod $method): StartedPayment` uses `PaymentRequest::single('Cartopia wallet top-up', ...)` with the description "Cartopia wallet top-up".
  - The `CompletePayment` top-up branch credits with the type `TopUp`, the description "Top-up with Stripe" or "Top-up with PayPal", and `payment:`.

- [ ] **Step 1: Write the failing tests:**
  - **`it('sends a top-up to the provider')`:** posting `amount=25` and `payment_method=paypal` redirects to the fake's return URL. A pending top-up payment of 2500 exists, with no order. The balance is unchanged.
  - **`it('credits a top-up once on return')`:** after two returns the balance has grown by 2500, with one ledger row "Top-up by PayPal" linked to the payment, and the toast "Added $25.00 to your wallet.".
  - **`it('refuses a top-up without a method')`, `it('refuses one outside $5 to $500')` and `it('refuses an unavailable method')`:** each gives a validation error.
  - **`it('cancels a top-up back to the wallet page')`:** the payment is `Cancelled`, the response redirects to `wallet.show`, and the toast is "Payment cancelled. Nothing was charged.".
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Implement the changes, and delete `TopUpWallet` and its tests.**
- [ ] **Step 4: Run the full feature suite and `phpstan analyse`.** Both should pass, with no references left to `TopUpWallet`.
- [ ] **Step 5: Commit and push:** "Pay for wallet top-ups by card or PayPal".

### Task 25: "Pay with" on the wallet page

**Files:**
- Modify: `resources/js/modules/wallet/TopUpForm.vue` and `tests/Browser/TopUpTest.php`

- [ ] **Step 1: Update `TopUpForm`:**
  - Reuse the hosted option cards from Task 16, as the canvas's Wallet board draws them. Stripe is the default.
  - The button reads "Continue to pay $X", or "Continue to pay" while the amount is empty or invalid.
  - With no methods available, show "Stripe and PayPal payments aren't set up on this copy of Cartopia." and disable the button.
  - Remove the comment about free credit.
- [ ] **Step 2: Rewrite the browser tests**, with the fake bound:
  - The quick amount $25 gives "Continue to pay $25.00".
  - Filling in 20 and pressing the button gives the toast "Added $20.00 to your wallet.", the balance $120.00, and the ledger row "Top-up with Stripe".
  - The test that checks the toast doesn't come back when going Back keeps its intent, with the new text.
- [ ] **Step 3: Run `npm run types`, `npm run lint`, `npm run build` and the browser suite.** All should pass.
- [ ] **Step 4: Commit and push:** "Choose how to pay on the wallet page".

### Task 26 (phase gate): Verify and open the phase PR

- [ ] **Step 1: Run all suites and checks**, and do a manual top-up with each provider.
- [ ] **Step 2: Open the PR** with `gh pr create --base payments/phase-3-split --head payments/phase-4-top-ups --title "Payments, phase 4: paid top-ups"`.

---

# Phase 5: Emails (`payments/phase-5-emails`)

Start with `git switch -c payments/phase-5-emails` from phase 4's branch.

### Task 27: Draw the three emails on the canvas and get them approved

**Files:**
- Canvas: the same as Task 19. The new artboards are `EmailReceipt.dc.html`, `EmailTopUp.dc.html` and `EmailProblem.dc.html`, at 640 px wide, plus a dark twin of each through a `dark` prop.
  - They use the spec section 8 content with the spec's example values: order #3 paid by wallet $4.34, and a split variant for the receipt; a $25.00 top-up by card; order #5 not paid, with $2.10 returned.
  - They use Georgia and a system sans, not the web fonts. Add them on a new **Emails** page in `canvas.json`.

- [ ] **Step 1: Draw and publish the artboards.**
- [ ] **Step 2: Ask the user to approve them.** Stop until they approve.

### Task 28: The mail layout and the order receipt

**Files:**
- Create:
  - `resources/views/mail/layout.blade.php`
  - `resources/views/mail/order-receipt.blade.php` and `resources/views/mail/order-receipt-text.blade.php`
  - `app/Mail/OrderReceiptMail.php`
- Modify: `app/Actions/PlaceOrder.php` and `app/Actions/CompletePayment.php`, to queue the receipt
- Test: `tests/Feature/Mail/OrderReceiptMailTest.php`

**Interfaces:**
- Consumes: `PaymentWording` (`paidWith`, `reference`, `slipLines`) and the order's `Purchase` ledger row for the balance after.
- Produces:
  - `OrderReceiptMail(Order $order) implements ShouldQueue`. The constructor calls `$this->afterCommit()`.
  - The subject is "Your Cartopia Order #{id} Is Confirmed!". The title, intro, rows, button and footer are the spec section 8.1 approved wording, verbatim.
  - The view is `mail.order-receipt` and the text view `mail.order-receipt-text`.
  - It is queued with `Mail::to($order->user)->queue(new OrderReceiptMail($order))`.

- [ ] **Step 1: Write the failing tests:**
  - **`it('queues a receipt for a wallet order')`:** after `Mail::fake()` and a wallet checkout, `Mail::assertQueued(OrderReceiptMail::class, 1)`, sent to the shopper.
  - **`it('queues one receipt for a card order despite duplicate settles')`:** a return plus a Stripe webhook give one queued email.
  - **`it('sends nothing when the order rolls back')`:** a failed start queues nothing.
  - **`it('renders the receipt')`:** for each of wallet, card, PayPal and split, `(new OrderReceiptMail($order))->render()` contains the item names, the total, the `paidWith` text and the slip lines. The split case contains "Cartopia wallet $2.10 + Visa ending in 4242 $2.24". `renderText()` also contains the total.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Build the layout and the templates** to Task 27's approved mockup, with inline CSS, a `prefers-color-scheme: dark` block, and the logo mark from `public/favicon-32.png`. Add the triggers.
- [ ] **Step 4: Run the tests again.** They should pass. With Mailpit and `queue:work` running, check the email in `http://localhost:8025`.
- [ ] **Step 5: Commit and push:** "Email a receipt for every placed order".

### Task 29: The top-up receipt and the payment problem notice

**Files:**
- Create:
  - `app/Mail/TopUpReceiptMail.php` and `app/Mail/PaymentProblemMail.php`
  - `resources/views/mail/top-up-receipt.blade.php` and `resources/views/mail/top-up-receipt-text.blade.php`
  - `resources/views/mail/payment-problem.blade.php` and `resources/views/mail/payment-problem-text.blade.php`
- Modify: `app/Actions/CompletePayment.php` (the top-up branch) and `app/Actions/ClosePayment.php` (use `$notify`, and remove the phase-2 note)
- Test: `tests/Feature/Mail/TopUpReceiptMailTest.php` and `tests/Feature/Mail/PaymentProblemMailTest.php`

**Interfaces:**
- Produces:
  - `TopUpReceiptMail(Payment $payment)`, with the subject "You added {amount} to your Cartopia wallet".
  - `PaymentProblemMail(Payment $payment)`, with the subject "Payment Not Completed – Cartopia Order #{id}" or "Payment Not Completed – Cartopia Top-up of {amount}".
  - The titles, intros, rows, ticks and buttons are the spec section 8.1 approved wording, verbatim.
  - Both are queued after commit.

- [ ] **Step 1: Write the failing tests:**
  - **`it('queues one top-up receipt')`:** two returns give one queued email. The render contains "$25.00", the `paidWith` text ("Visa ending in 4242, through Stripe" or the PayPal wording), the reference, and the new balance.
  - **`it('emails when a payment expires or is denied while the shopper is away')`:**
    - `payments:expire-stale`, the Stripe expired webhook and the PayPal denied webhook each queue one `PaymentProblemMail`.
    - The split case's render contains "$2.10 has been restored to your wallet." and "Your basket is still there.".
    - The top-up case has the subject "Payment Not Completed – Cartopia Top-up of $25.00".
  - **`it('does not email for a cancel or a decline the shopper saw')`:** the cancel route and a declined return queue nothing.
- [ ] **Step 2: Run the tests.** They should fail.
- [ ] **Step 3: Build the templates** to the approved mockups, and add the triggers.
- [ ] **Step 4: Run the tests again, and the full feature suite.** Both should pass.
- [ ] **Step 5: Commit and push:** "Email top-up receipts and payment problems".

### Task 30: The local setup README, and the final check

**Files:**
- Modify: `README.md`, with a "Payments in sandbox mode" section covering spec section 11 step by step:
  - Stripe keys and `stripe listen ... --skip-verify`
  - PayPal app keys, ngrok with `--host-header=cartopia.test`, and the webhook with its three events and `PAYPAL_WEBHOOK_ID`
  - Mailpit's `MAIL_*` lines, `queue:work` and `schedule:work`
  - the test cards

- [ ] **Step 1: Write the README section.** Don't edit `.env`. Tell the user which `MAIL_*` lines to set for Mailpit: `MAIL_MAILER=smtp`, `MAIL_HOST=127.0.0.1`, `MAIL_PORT=1025`.
- [ ] **Step 2: Run everything.** That means the feature suite on SQLite and MySQL, the browser suite after `npm run build`, Pint, Larastan, Oxlint, the format check and vue-tsc. All should be green.
- [ ] **Step 3: Walk through spec section 12.3 in full with the user's sandbox keys**, and check every email in Mailpit in light and dark.
- [ ] **Step 4: Commit and push:** "Document the payments sandbox setup".
- [ ] **Step 5: Open the PR** with `gh pr create --base payments/phase-4-top-ups --head payments/phase-5-emails --title "Payments, phase 5: emails"`.
