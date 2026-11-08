<?php

use App\Actions\StartOrderPayment;
use App\Cart\Cart;
use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\User;
use App\Support\Money;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Browser');

/**
 * A shopper with the given balance and a basket of 2 x $2.00 and 1 x $3.74,
 * plus UPS ($5.00): a total of $12.74.
 *
 * @return array{0: User, 1: ShippingMethod}
 */
function checkoutSetup(int $balance = 10000): array
{
    $user = User::factory()->withBalance(Money::fromCents($balance))->create();
    $cart = Cart::forUser($user);
    $cart->add(Product::factory()->create(['price' => 200]), 2);
    $cart->add(Product::factory()->create(['price' => 374]), 1);

    return [$user, ShippingMethod::factory()->ups()->create()];
}

/**
 * Starts a hosted payment for the checkoutSetup() basket. Needs a bound
 * PaymentGateways::fake().
 *
 * @return array{0: User, 1: Payment}
 */
function startedOrderPayment(PaymentMethod $method = PaymentMethod::Stripe): array
{
    [$user, $ups] = checkoutSetup();

    app(StartOrderPayment::class)->handle($user, $ups, $method);

    return [$user, Payment::sole()];
}

/**
 * A valid Stripe-Signature header for the payload, as Stripe would send it.
 */
function stripeSignature(string $payload, string $secret): string
{
    $t = time();

    return "t={$t},v1=".hash_hmac('sha256', "{$t}.{$payload}", $secret);
}

/**
 * Storage::fake('public') in folders of this test process's own, for the
 * public disk and for Livewire's uploads in tests (tmp-for-tests).
 * Storage::fake() always uses storage/framework/testing/disks/{disk} and
 * empties it first, so two test runs at once would delete each other's files.
 */
function fakePublicDisk(): void
{
    static $roots = null;

    if ($roots === null) {
        $roots = [];
        foreach (['public', 'tmp-for-tests'] as $disk) {
            $roots[$disk] = storage_path("framework/testing/disks/{$disk}-".getmypid());
        }
        register_shutdown_function(fn () => array_map((new Filesystem)->deleteDirectory(...), $roots));
    }

    foreach ($roots as $disk => $root) {
        (new Filesystem)->cleanDirectory($root);
        Storage::set($disk, Storage::createLocalDriver(['root' => $root, 'throw' => false]));
    }
}
