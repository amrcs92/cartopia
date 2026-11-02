<?php

use App\Actions\Admin\RefundOrder;
use App\Admin\SalesFigures;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Filament\Widgets\LatestOrders;
use App\Filament\Widgets\SalesChart;
use App\Filament\Widgets\SalesOverview;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Livewire\Livewire;

function orderAt(string $when, int $total = 1000, string $state = 'create'): Order
{
    $factory = Order::factory();

    if ($state === 'cancelled') {
        $factory = $factory->cancelled();
    } elseif ($state === 'awaiting') {
        $factory = $factory->awaitingPayment();
    }

    return $factory->create(['total' => $total, 'created_at' => $when]);
}

beforeEach(function () {
    // A Wednesday, so the week has days both before and after "now".
    $this->travelTo('2026-11-04 12:00:00');
});

it('sums only placed orders for today', function () {
    orderAt('2026-11-04 00:00:00', 1000);
    orderAt('2026-11-04 09:30:00', 2500);
    orderAt('2026-11-03 23:59:59', 9999);
    orderAt('2026-11-04 10:00:00', 7000, 'cancelled');
    orderAt('2026-11-04 10:00:00', 7000, 'awaiting');

    $today = app(SalesFigures::class)->today();

    expect($today)->toBeInstanceOf(Money::class)
        ->and($today->cents())->toBe(3500);
});

it('starts the week on Monday at midnight', function () {
    orderAt('2026-11-01 23:59:00', 5000); // Sunday, last week
    orderAt('2026-11-02 00:00:00', 1000); // Monday, this week
    orderAt('2026-11-04 08:00:00', 2000);
    orderAt('2026-11-03 08:00:00', 4000, 'cancelled');
    orderAt('2026-11-03 08:00:00', 4000, 'awaiting');

    $figures = app(SalesFigures::class);

    expect($figures->thisWeek()->cents())->toBe(3000)
        ->and($figures->ordersThisWeek())->toBe(2);
});

it('starts the week on Monday even in a locale whose week starts on Sunday', function () {
    app()->setLocale('en_US');
    orderAt('2026-11-01 12:00:00', 5000); // Sunday, which starts en_US's week
    orderAt('2026-11-02 00:00:00', 1000); // Monday

    $figures = app(SalesFigures::class);

    expect(now()->startOfWeek()->isSunday())->toBeTrue()
        ->and($figures->thisWeek()->cents())->toBe(1000)
        ->and($figures->ordersThisWeek())->toBe(1);
});

it('counts pending payments older than 30 minutes as needing attention', function () {
    Payment::factory()->create(['created_at' => now()->subMinutes(31)]);
    Payment::factory()->create(['created_at' => now()->subMinutes(5)]);
    Payment::factory()->create(['status' => PaymentStatus::Succeeded, 'created_at' => now()->subHours(2)]);

    expect(app(SalesFigures::class)->needsAttention())->toBe(1);
});

it('returns 30 zero-filled days, oldest first, ending today', function () {
    orderAt('2026-11-04 09:00:00', 1500);
    orderAt('2026-11-04 11:00:00', 500);
    orderAt('2026-10-06 09:00:00', 700); // 29 days ago: the first key
    orderAt('2026-10-05 09:00:00', 900); // 30 days ago: outside
    orderAt('2026-11-02 09:00:00', 900, 'cancelled');

    $daily = app(SalesFigures::class)->daily(30);

    expect($daily)->toHaveCount(30)
        ->and(array_key_first($daily))->toBe('2026-10-06')
        ->and(array_key_last($daily))->toBe('2026-11-04')
        ->and($daily['2026-11-04'])->toBe(2000)
        ->and($daily['2026-10-06'])->toBe(700)
        ->and($daily['2026-11-02'])->toBe(0)
        ->and($daily['2026-10-20'])->toBe(0);
});

it('shows the four stats on the overview widget', function () {
    orderAt('2026-11-04 09:00:00', 1250);
    Payment::factory()->create(['created_at' => now()->subHour()]);

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(SalesOverview::class)
        ->assertSee('Sales today')
        ->assertSee('Sales this week')
        ->assertSee('Orders this week')
        ->assertSee('Needs attention')
        ->assertSee('$12.50')
        ->assertSee(PaymentResource::getUrl('index', ['tab' => 'needs-attention']), false);
});

it('renders the dashboard with the widgets and the five latest orders', function () {
    $orders = collect(range(1, 6))->map(fn (int $i) => Order::factory()->create([
        'created_at' => now()->subMinutes($i),
        'user_id' => User::factory()->create(['name' => "Shopper {$i}"]),
    ]));

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSee('Sales today')
        ->assertSee('Sales this week')
        ->assertSee('Orders this week')
        ->assertSee('Needs attention')
        ->assertSee('Sales, last 30 days');

    foreach (range(1, 5) as $i) {
        $response->assertSee("Shopper {$i}");
    }
    $response->assertDontSee('Shopper 6');

    Livewire::actingAs(User::factory()->admin()->create())
        ->test(LatestOrders::class)
        ->assertCanSeeTableRecords($orders->take(5))
        ->assertSee(OrderResource::getUrl('view', ['record' => $orders[0]]), false);
});

it('charts the daily totals in dollars', function () {
    orderAt('2026-11-04 09:00:00', 1250);

    $component = Livewire::actingAs(User::factory()->admin()->create())->test(SalesChart::class);

    $component->assertSee('Sales, last 30 days');

    $data = (fn () => $this->getData())->call($component->instance());

    expect($data['datasets'][0]['data'])->toHaveCount(30)
        ->and(end($data['datasets'][0]['data']))->toBe(12.5)
        ->and($data['labels'])->toHaveCount(30)
        ->and(end($data['labels']))->toBe('Nov 4');
});

it('keeps shoppers off the dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertNotFound();
});

it('drops an order from the sales figures once it is refunded', function () {
    $order = orderAt('2026-11-04 09:00:00', 4000);
    orderAt('2026-11-04 10:00:00', 1000);
    $figures = app(SalesFigures::class);

    expect($figures->today()->cents())->toBe(5000);

    app(RefundOrder::class)->handle($order, User::factory()->admin()->create(), 'Damaged in transit');

    expect($order->fresh()->status)->toBe(OrderStatus::Refunded)
        ->and($figures->today()->cents())->toBe(1000)
        ->and($figures->thisWeek()->cents())->toBe(1000)
        ->and($figures->ordersThisWeek())->toBe(1);
});
