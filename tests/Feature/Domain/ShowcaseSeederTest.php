<?php

use App\Cart\Cart;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Review;
use App\Models\User;
use Database\Seeders\ShowcaseSeeder;

it('builds the showcase state for the demo shopper', function () {
    fakePublicDisk();

    $this->seed(ShowcaseSeeder::class);

    $sam = User::query()->where('email', 'demo@cartopia.test')->firstOrFail();
    $orders = $sam->orders()->get();

    expect(Cart::forUser($sam)->summary()->lines)->toHaveCount(3)
        ->and(Cart::forUser($sam)->count())->toBe(3)
        ->and($sam->balance->cents())->toBe(4250)
        ->and((int) $sam->walletTransactions()->sum('amount'))->toBe(4250)
        ->and($orders)->toHaveCount(4)
        ->and($orders->every(fn (Order $order) => $order->status === OrderStatus::Placed))->toBeTrue()
        ->and($orders->filter(fn (Order $order) => ! $order->payment_method->isHosted()))->toHaveCount(3)
        ->and($orders->filter->isSplit())->toHaveCount(1)
        ->and(Review::query()->whereBelongsTo($sam)->count())->toBe(2);

    $split = $orders->first->isSplit();
    $payment = Payment::query()->whereBelongsTo($split)->sole();
    expect($payment->status)->toBe(PaymentStatus::Succeeded)
        ->and($payment->amount->cents())->toBe($split->total->subtract($split->wallet_amount)->cents());

    Review::query()->with('product')->get()
        ->each(fn (Review $review) => expect(Review::canReview($review->user, $review->product))->toBeTrue());
});

it('gives the store a believable week of other customers', function () {
    fakePublicDisk();

    $this->seed(ShowcaseSeeder::class);

    $sam = User::query()->where('email', 'demo@cartopia.test')->firstOrFail();
    $others = User::query()->where('is_admin', false)->whereKeyNot($sam->id)->get();

    expect($others)->toHaveCount(8)
        ->and(Order::query()->whereKeyNot($sam->orders()->pluck('id'))->count())->toBeGreaterThanOrEqual(12)
        ->and(Review::query()->count())->toBeGreaterThanOrEqual(10);

    $others->each(fn (User $customer) => expect($customer->balance->cents())
        ->toBe((int) $customer->walletTransactions()->sum('amount'))
        ->toBeGreaterThanOrEqual(0));
});

it('spreads orders over the dashboard month with every recent day covered', function (int $hour, int $minute) {
    $this->travelTo(now()->setTime($hour, $minute, 30));
    fakePublicDisk();

    $this->seed(ShowcaseSeeder::class);

    $lastWeek = Order::query()->where('created_at', '>=', now()->startOfDay()->subDays(6))
        ->pluck('created_at')->map->toDateString()->unique();

    expect(Order::query()->where('created_at', '>', now())->count())->toBe(0)
        ->and(Order::query()->where('created_at', '<', now()->startOfDay()->subDays(29))->count())->toBe(0)
        ->and(Order::query()->where('created_at', '<', now()->startOfDay()->subDays(7))->count())->toBeGreaterThanOrEqual(20)
        ->and($lastWeek)->toHaveCount(7);
})->with([
    'just before midnight' => [23, 59],
    'just after midnight' => [0, 0],
]);

it('refuses to seed demo accounts in production', function () {
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(ShowcaseSeeder::class)->run())->toThrow(LogicException::class, 'production');
    expect(User::query()->count())->toBe(0);
});
