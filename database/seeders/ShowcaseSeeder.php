<?php

namespace Database\Seeders;

use App\Actions\PlaceOrder;
use App\Actions\SaveReview;
use App\Cart\Cart;
use App\Cart\PricedBasket;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentPurpose;
use App\Enums\PaymentStatus;
use App\Enums\WalletTransactionType;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ShippingMethod;
use App\Models\StoreSettings;
use App\Models\User;
use App\Support\Money;
use App\Wallet\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use LogicException;
use RuntimeException;

/**
 * One fixed, believable store state for the README screenshots and the
 * walkthrough video: Sam's basket, wallet, orders and reviews, plus a week
 * of other customers. Run it with
 * `php artisan migrate:fresh --seed --seeder=ShowcaseSeeder`.
 */
class ShowcaseSeeder extends Seeder
{
    public const int SAM_BALANCE_CENTS = 4250;

    /**
     * Other customers, each with their orders (days ago, time, products and
     * quantities) and their reviews (product, stars, text) of what they bought.
     * Between them the orders cover every one of the last seven days.
     *
     * @var list<array{name: string, email: string, orders: non-empty-list<array{int, string, array<string, int>}>, reviews: list<array{string, int, string|null}>}>
     */
    private const array CUSTOMERS = [
        [
            'name' => 'Maya Chen',
            'email' => 'maya.chen@example.com',
            'orders' => [[6, '09:15', ['apple' => 3, 'bananas' => 1]], [2, '12:00', ['penne-pasta' => 2, 'olive-oil' => 1, 'vine-tomatoes' => 1]]],
            'reviews' => [['apple', 5, 'Crisp and sweet, exactly what I wanted.'], ['penne-pasta', 4, null]],
        ],
        [
            'name' => 'Daniel Okafor',
            'email' => 'daniel.okafor@example.com',
            'orders' => [[6, '17:40', ['water' => 2, 'sourdough-loaf' => 1]], [2, '19:30', ['mixed-nuts' => 1, 'cheese-triangle' => 2]]],
            'reviews' => [['sourdough-loaf', 5, "Best loaf I've had from a shop. The crust is perfect."], ['cheese-triangle', 3, 'Fine, if a little mild for me.']],
        ],
        [
            'name' => 'Priya Nair',
            'email' => 'priya.nair@example.com',
            'orders' => [[5, '11:05', ['greek-yogurt' => 2, 'granola-bars' => 1]], [1, '09:40', ['free-range-eggs' => 1, 'whole-milk' => 1, 'bagels' => 1]]],
            'reviews' => [['greek-yogurt', 4, null], ['bagels', 5, null]],
        ],
        [
            'name' => 'Lucas Martin',
            'email' => 'lucas.martin@example.com',
            'orders' => [[5, '20:10', ['monster-energy-drink' => 4, 'sea-salt-crisps' => 2]], [1, '16:25', ['sparkling-water' => 2]]],
            'reviews' => [['sea-salt-crisps', 5, 'Proper crunch, and not too salty.']],
        ],
        [
            'name' => 'Hannah Weber',
            'email' => 'hannah.weber@example.com',
            'orders' => [[4, '08:30', ['croissants' => 1, 'orange-juice' => 1]], [0, '09:10', ['blueberry-muffins' => 1, 'honey' => 1]]],
            'reviews' => [['croissants', 5, 'Flaky and buttery. Warm them up for a few minutes first.']],
        ],
        [
            'name' => 'Omar Haddad',
            'email' => 'omar.haddad@example.com',
            'orders' => [[4, '13:50', ['basmati-rice' => 2, 'olive-oil' => 1]], [0, '11:30', ['bananas' => 2, 'apple' => 4]]],
            'reviews' => [['olive-oil', 4, 'Lovely peppery finish on a salad.'], ['basmati-rice', 5, 'Fluffy every single time.']],
        ],
        [
            'name' => 'Grace Kim',
            'email' => 'grace.kim@example.com',
            'orders' => [[3, '10:20', ['avocados' => 2, 'vine-tomatoes' => 1, 'sourdough-loaf' => 1]], [0, '08:05', ['whole-milk' => 1]]],
            'reviews' => [['avocados', 4, null]],
        ],
        [
            'name' => 'Tom Fletcher',
            'email' => 'tom.fletcher@example.com',
            'orders' => [[3, '18:45', ['dark-chocolate' => 3]]],
            'reviews' => [['dark-chocolate', 5, 'Rich without being bitter.']],
        ],
    ];

    /**
     * Baskets the month's older orders rotate through.
     *
     * @var non-empty-list<array<string, int>>
     */
    private const array HISTORY_BASKETS = [
        ['bananas' => 1, 'whole-milk' => 1, 'sourdough-loaf' => 1],
        ['orange-juice' => 2, 'croissants' => 1],
        ['penne-pasta' => 2, 'vine-tomatoes' => 1, 'olive-oil' => 1],
        ['apple' => 6],
        ['sea-salt-crisps' => 2, 'sparkling-water' => 1],
        ['free-range-eggs' => 1, 'bagels' => 1],
        ['dark-chocolate' => 2, 'mixed-nuts' => 1],
        ['greek-yogurt' => 1, 'honey' => 1, 'granola-bars' => 1],
        ['basmati-rice' => 1, 'avocados' => 1],
        ['water' => 3, 'monster-energy-drink' => 2],
    ];

    private Carbon $now;

    private ShippingMethod $pickup;

    public function __construct(
        private readonly Wallet $wallet,
        private readonly PlaceOrder $placeOrder,
        private readonly SaveReview $saveReview,
    ) {}

    public function run(): void
    {
        // Demo accounts with the password "password" and fake payments never belong in production.
        if (app()->isProduction()) {
            throw new LogicException('ShowcaseSeeder only seeds local demo data: it never runs in production.');
        }

        $this->call(DatabaseSeeder::class);

        $this->now = Carbon::now();
        $this->pickup = ShippingMethod::query()->where('fee', 0)->firstOrFail();

        $this->seedSam();
        $this->seedCustomers();
    }

    private function seedSam(): void
    {
        $sam = User::query()->where('email', 'demo@cartopia.test')->firstOrFail();
        $joined = $this->when(8, '18:00');
        $sam->forceFill(['created_at' => $joined, 'updated_at' => $joined])->save();
        $sam->walletTransactions()->update(['created_at' => $joined]);

        $this->at($this->when(6, '10:05'), fn () => $this->topUp($sam, 2500));
        $this->at($this->when(6, '10:30'), fn () => $this->walletOrder($sam, ['olive-oil' => 1, 'sourdough-loaf' => 1]));
        $this->at($this->when(4, '18:10'), fn () => $this->walletOrder($sam, ['bananas' => 1, 'greek-yogurt' => 2, 'croissants' => 1]));
        $this->at($this->when(3, '09:00'), fn () => $this->review($sam, 'olive-oil', 5, 'Peppery and fresh. I put it on everything now.'));
        $this->at($this->when(3, '09:05'), fn () => $this->review($sam, 'croissants', 4, null));
        $this->at($this->when(2, '12:40'), fn () => $this->walletOrder($sam, ['dark-chocolate' => 2, 'mixed-nuts' => 1]));
        $this->at($this->when(1, '19:00'), fn () => $this->topUp($sam, 3000));
        $this->at($this->when(1, '19:20'), fn () => $this->splitOrder($sam, ['honey' => 1, 'free-range-eggs' => 1, 'sea-salt-crisps' => 1, 'whole-milk' => 1]));

        $basket = Cart::forUser($sam);
        $basket->add($this->product('avocados'), 1);
        $basket->add($this->product('sparkling-water'), 1);
        $basket->add($this->product('blueberry-muffins'), 1);
    }

    private function seedCustomers(): void
    {
        $customers = array_map(
            fn (array $data): User => $this->at($this->when(31, '20:00'), function () use ($data): User {
                $customer = User::factory()->create(['name' => $data['name'], 'email' => $data['email'], 'password' => 'password']);
                $this->wallet->credit($customer, Money::fromCents(StoreSettings::DEFAULT_SIGNUP_CREDIT_CENTS), WalletTransactionType::SignupBonus);
                $this->topUp($customer, 10000);

                return $customer;
            }),
            self::CUSTOMERS,
        );

        $this->seedHistory($customers);

        foreach (self::CUSTOMERS as $index => $data) {
            $customer = $customers[$index];
            $boughtAt = [];
            foreach ($data['orders'] as [$daysAgo, $time, $items]) {
                $when = $this->when($daysAgo, $time);
                $this->at($when, fn () => $this->walletOrder($customer, $items));
                $boughtAt += array_fill_keys(array_keys($items), $when);
            }

            foreach ($data['reviews'] as [$slug, $stars, $body]) {
                $when = ($boughtAt[$slug] ?? throw new RuntimeException("{$data['name']} never bought {$slug}."))
                    ->copy()->addHours(3)->min($this->now);
                $this->at($when, fn () => $this->review($customer, $slug, $stars, $body));
            }
        }
    }

    /**
     * Older orders from 7 to 29 days ago, so the dashboard's 30-day chart
     * reads like a store that has been trading all month. One or two orders
     * a day, rotating through the customers and baskets.
     *
     * @param  list<User>  $customers
     */
    private function seedHistory(array $customers): void
    {
        $order = 0;
        for ($daysAgo = 29; $daysAgo >= 7; $daysAgo--) {
            $perDay = $daysAgo % 3 === 0 ? 2 : 1;
            for ($slot = 0; $slot < $perDay; $slot++) {
                $customer = $customers[($daysAgo * 3 + $slot) % count($customers)];
                $basket = self::HISTORY_BASKETS[$order % count(self::HISTORY_BASKETS)];
                $time = $slot === 0 ? '10:30' : '18:15';
                $this->at($this->when($daysAgo, $time), fn () => $this->walletOrder($customer, $basket));
                $order++;
            }
        }
    }

    /**
     * @param  array<string, int>  $items
     */
    private function walletOrder(User $user, array $items): Order
    {
        $this->fillCart($user, $items);

        return $this->placeOrder->handle($user, $this->pickup);
    }

    /**
     * An order paid partly from the wallet and the rest by Stripe, settled the
     * way a completed checkout leaves it. The wallet share is whatever brings
     * Sam's balance to exactly SAM_BALANCE_CENTS.
     *
     * @param  array<string, int>  $items
     */
    private function splitOrder(User $user, array $items): Order
    {
        $cart = $this->fillCart($user, $items);
        $basket = PricedBasket::of($cart, $this->pickup);
        $walletShare = $user->refresh()->balance->cents() - self::SAM_BALANCE_CENTS;
        $hosted = $basket->total->cents() - $walletShare;

        if ($walletShare < 1 || $hosted < 50) {
            throw new RuntimeException("The showcase split order can't take {$walletShare} cents from the wallet.");
        }

        $order = Order::create([
            'user_id' => $user->id,
            'shipping_method_id' => $this->pickup->id,
            'subtotal' => $basket->subtotal(),
            'shipping_fee' => $basket->fee,
            'total' => $basket->total,
            'status' => OrderStatus::Placed,
            'payment_method' => PaymentMethod::Stripe,
            'wallet_amount' => Money::fromCents($walletShare),
        ]);
        $order->items()->createMany($basket->itemAttributes());
        $this->wallet->debit($user, Money::fromCents($walletShare), WalletTransactionType::Purchase, "Order #{$order->id}", $order);

        Payment::create([
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::Order,
            'order_id' => $order->id,
            'method' => PaymentMethod::Stripe,
            'status' => PaymentStatus::Succeeded,
            'amount' => Money::fromCents($hosted),
            'currency' => 'usd',
            'provider_reference' => "cs_test_showcase_{$order->id}",
            'provider_payment_id' => "pi_test_showcase_{$order->id}",
            'card_brand' => 'visa',
            'card_last4' => '4242',
            'completed_at' => now(),
        ]);
        $cart->clear();

        return $order;
    }

    private function topUp(User $user, int $cents): void
    {
        $payment = Payment::create([
            'user_id' => $user->id,
            'purpose' => PaymentPurpose::TopUp,
            'method' => PaymentMethod::Stripe,
            'status' => PaymentStatus::Succeeded,
            'amount' => Money::fromCents($cents),
            'currency' => 'usd',
            'provider_reference' => 'cs_test_showcase_top_up_'.$user->id.'_'.now()->timestamp,
            'card_brand' => 'visa',
            'card_last4' => '4242',
            'completed_at' => now(),
        ]);
        $this->wallet->credit($user, Money::fromCents($cents), WalletTransactionType::TopUp, 'Top-up with Stripe', payment: $payment);
    }

    private function review(User $user, string $slug, int $stars, ?string $body): void
    {
        $this->saveReview->handle($user, $this->product($slug), $stars, $body);
    }

    /**
     * @param  array<string, int>  $items
     */
    private function fillCart(User $user, array $items): Cart
    {
        $cart = Cart::forUser($user);
        foreach ($items as $slug => $quantity) {
            $cart->add($this->product($slug), $quantity);
        }

        return $cart;
    }

    private function product(string $slug): Product
    {
        return Product::query()->where('slug', $slug)->firstOrFail();
    }

    /**
     * A moment `$daysAgo` days back at `$time`. Today's moments never pass
     * the current time, and never slip into yesterday.
     */
    private function when(int $daysAgo, string $time): Carbon
    {
        [$hour, $minute] = array_map(intval(...), explode(':', $time));
        $moment = $this->now->copy()->startOfDay()->subDays($daysAgo)->setTime($hour, $minute);

        if ($moment->greaterThan($this->now)) {
            $moment = $this->now->copy()->subMinutes(1)->max($this->now->copy()->startOfDay());
        }

        return $moment;
    }

    /**
     * Runs `$callback` with the clock frozen at `$moment`, then restores it.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    private function at(Carbon $moment, callable $callback): mixed
    {
        $previous = Carbon::getTestNow();
        Carbon::setTestNow($moment);

        try {
            return $callback();
        } finally {
            Carbon::setTestNow($previous);
        }
    }
}
