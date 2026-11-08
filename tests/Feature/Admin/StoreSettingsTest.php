<?php

use App\Filament\Pages\EditStoreSettings;
use App\Models\StoreSettings;
use App\Models\User;
use Livewire\Livewire;

it('shows the current sign-up credit to an admin', function () {
    $this->actingAs(User::factory()->admin()->create());

    $this->get('/admin/store-settings')->assertOk()->assertSee('Sign-up credit');

    Livewire::test(EditStoreSettings::class)
        ->assertSchemaStateSet(['signup_credit' => '20.00']);
});

it('saves a new sign-up credit', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditStoreSettings::class)
        ->fillForm(['signup_credit' => '25.50'])
        ->call('save')
        ->assertHasNoFormErrors()
        ->assertNotified('Store settings saved');

    expect(StoreSettings::current()->signup_credit->cents())->toBe(2550);
});

it('accepts zero to turn the credit off', function () {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditStoreSettings::class)
        ->fillForm(['signup_credit' => '0'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(StoreSettings::current()->signup_credit->cents())->toBe(0);
});

it('refuses amounts outside $0 to $500 or with more than two decimals', function (string $amount) {
    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(EditStoreSettings::class)
        ->fillForm(['signup_credit' => $amount])
        ->call('save')
        ->assertHasFormErrors(['signup_credit']);

    expect(StoreSettings::current()->signup_credit->cents())->toBe(2000);
})->with(['-1', '500.01', '12.345', 'abc', '']);

it('shows a shopper the not-found page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/store-settings')
        ->assertNotFound();
});
