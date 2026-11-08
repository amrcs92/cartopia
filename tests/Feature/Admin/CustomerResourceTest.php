<?php

use App\Actions\Admin\SetAdminAccess;
use App\Enums\WalletTransactionType;
use App\Exceptions\AdminActionRefused;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ListCustomers;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Filament\Resources\Customers\RelationManagers\OrdersRelationManager;
use App\Filament\Resources\Customers\RelationManagers\WalletTransactionsRelationManager;
use App\Models\Order;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Support\Money;
use Filament\Notifications\Notification;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->admin()->create(['name' => 'Ada Admin']);
    $this->actingAs($this->admin);
});

it('lists customers with balance, order count and an Admin badge', function () {
    $shopper = User::factory()->withBalance(Money::fromCents(1250))->create(['name' => 'Sam Shopper']);
    Order::factory()->for($shopper)->count(2)->create();

    Livewire::test(ListCustomers::class)
        ->assertCanSeeTableRecords([$shopper, $this->admin])
        ->assertTableColumnStateSet('balance', '$12.50', $shopper)
        ->assertTableColumnStateSet('orders_count', 2, $shopper)
        ->assertTableColumnStateSet('is_admin', 'Admin', $this->admin)
        ->assertTableColumnStateSet('is_admin', null, $shopper)
        ->assertTableColumnExists('created_at');
});

it('searches by name and by email', function () {
    $sam = User::factory()->create(['name' => 'Sam Shopper', 'email' => 'sam@example.com']);
    $kim = User::factory()->create(['name' => 'Kim Buyer', 'email' => 'kim@example.com']);

    Livewire::test(ListCustomers::class)
        ->searchTable('Sam')
        ->assertCanSeeTableRecords([$sam])
        ->assertCanNotSeeTableRecords([$kim])
        ->searchTable('kim@example')
        ->assertCanSeeTableRecords([$kim])
        ->assertCanNotSeeTableRecords([$sam]);
});

it('filters to admins only', function () {
    $shopper = User::factory()->create();

    Livewire::test(ListCustomers::class)
        ->filterTable('admins')
        ->assertCanSeeTableRecords([$this->admin])
        ->assertCanNotSeeTableRecords([$shopper]);
});

it('shows a customer read-only', function () {
    $shopper = User::factory()->create(['name' => 'Sam Shopper', 'email' => 'sam@example.com']);

    Livewire::test(ViewCustomer::class, ['record' => $shopper->getKey()])
        ->assertSuccessful()
        ->assertSee('Sam Shopper')
        ->assertSee('sam@example.com');
});

it('lists only the customer\'s orders on the Orders tab', function () {
    $shopper = User::factory()->create();
    $mine = Order::factory()->for($shopper)->create();
    $other = Order::factory()->create();

    Livewire::test(OrdersRelationManager::class, ['ownerRecord' => $shopper, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords([$mine])
        ->assertCanNotSeeTableRecords([$other]);
});

it('lists the ledger newest first on the Wallet tab', function () {
    $shopper = User::factory()->create();
    $older = WalletTransaction::create([
        'user_id' => $shopper->id, 'type' => WalletTransactionType::TopUp, 'amount' => Money::fromCents(1000),
        'balance_after' => Money::fromCents(1000), 'description' => 'Top-up',
    ]);
    $older->forceFill(['created_at' => now()->subDay()])->save();
    $newer = WalletTransaction::create([
        'user_id' => $shopper->id, 'type' => WalletTransactionType::AdminAdjustment, 'amount' => Money::fromCents(-300),
        'balance_after' => Money::fromCents(700), 'description' => 'Goodwill',
    ]);

    Livewire::test(WalletTransactionsRelationManager::class, ['ownerRecord' => $shopper, 'pageClass' => ViewCustomer::class])
        ->assertCanSeeTableRecords([$newer, $older], inOrder: true)
        ->assertTableColumnStateSet('type', 'Adjustment', $newer)
        ->assertTableColumnStateSet('amount', '-$3.00', $newer)
        ->assertTableColumnStateSet('balance_after', '$7.00', $newer)
        ->assertSee('Goodwill');
});

it('credits a wallet', function () {
    $shopper = User::factory()->withBalance(Money::fromCents(500))->create();

    Livewire::test(ViewCustomer::class, ['record' => $shopper->getKey()])
        ->callAction('adjustWallet', ['direction' => 'credit', 'amount' => '10.00', 'reason' => 'Goodwill'])
        ->assertHasNoActionErrors();

    expect($shopper->fresh()->balance->cents())->toBe(1500);

    $row = $shopper->walletTransactions()->latest('id')->first();
    expect($row->type)->toBe(WalletTransactionType::AdminAdjustment)
        ->and($row->description)->toBe('Goodwill')
        ->and($row->admin_id)->toBe($this->admin->id);
});

it('shows the refusal when a debit is too large and changes nothing', function () {
    $shopper = User::factory()->withBalance(Money::fromCents(500))->create(['name' => 'Sam Shopper']);

    $rows = $shopper->walletTransactions()->count();

    Livewire::test(ViewCustomer::class, ['record' => $shopper->getKey()])
        ->callAction('adjustWallet', ['direction' => 'debit', 'amount' => '20.00', 'reason' => 'Oops'])
        ->assertNotified(
            Notification::make()->danger()
                ->title("Sam Shopper's balance is \$5.00, so you can debit at most \$5.00.")
        );

    expect($shopper->fresh()->balance->cents())->toBe(500)
        ->and($shopper->walletTransactions()->count())->toBe($rows);
});

it('validates the adjustment form', function (string $amount, string $reason, string $field) {
    $shopper = User::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $shopper->getKey()])
        ->callAction('adjustWallet', ['direction' => 'credit', 'amount' => $amount, 'reason' => $reason])
        ->assertHasActionErrors([$field]);

    expect($shopper->walletTransactions()->count())->toBe(0);
})->with([
    'missing reason' => ['5.00', '', 'reason'],
    'reason too long' => ['5.00', str_repeat('x', 121), 'reason'],
    'above the cap' => ['500.01', 'x', 'amount'],
    'too many decimals' => ['1.234', 'x', 'amount'],
    'zero' => ['0', 'x', 'amount'],
]);

it('makes a shopper an admin and removes them again', function () {
    $shopper = User::factory()->create();

    Livewire::test(ViewCustomer::class, ['record' => $shopper->getKey()])
        ->assertActionVisible('makeAdmin')
        ->assertActionHidden('removeAdmin')
        ->callAction('makeAdmin');

    expect($shopper->fresh()->is_admin)->toBeTrue();

    Livewire::test(ViewCustomer::class, ['record' => $shopper->getKey()])
        ->assertActionHidden('makeAdmin')
        ->assertActionVisible('removeAdmin')
        ->callAction('removeAdmin');

    expect($shopper->fresh()->is_admin)->toBeFalse();
});

it('hides Remove admin on your own page', function () {
    User::factory()->admin()->create();

    Livewire::test(ViewCustomer::class, ['record' => $this->admin->getKey()])
        ->assertActionHidden('removeAdmin')
        ->assertActionHidden('makeAdmin');
});

it('refuses removing your own admin access if it is attempted anyway', function () {
    expect(fn () => app(SetAdminAccess::class)->handle($this->admin, $this->admin, false))
        ->toThrow(AdminActionRefused::class, "You can't remove your own admin access.");

    expect($this->admin->fresh()->is_admin)->toBeTrue();
});

it('has no create, edit or delete pages', function () {
    expect(array_keys(CustomerResource::getPages()))->toBe(['index', 'view'])
        ->and(CustomerResource::canCreate())->toBeFalse()
        ->and(CustomerResource::canEdit($this->admin))->toBeFalse()
        ->and(CustomerResource::canDelete($this->admin))->toBeFalse();
});
