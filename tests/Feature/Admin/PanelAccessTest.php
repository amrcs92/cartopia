<?php

use App\Filament\Pages\EditStoreSettings;
use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Http\Middleware\AuthenticateAdmin;
use App\Models\StoreSettings;
use App\Models\User;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;

it('lets an admin into the panel', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk();
});

it('shows a shopper the not-found page', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin')
        ->assertNotFound();
});

it('sends a guest to the store login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('has no Filament login page', function () {
    $this->get('/admin/login')->assertNotFound();
});

it('sends an admin back to the panel after the store login', function () {
    $admin = User::factory()->admin()->create();

    $this->get('/admin');

    $this->post(route('login.store'), ['email' => $admin->email, 'password' => 'password'])
        ->assertRedirect('/admin');
});

it('shows the logo and the Cartopia name in the panel brand', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertSee(asset('favicon.svg'), false)
        ->assertSee('>Cartopia</span>', false);
});

/**
 * The snapshot of one Livewire component, by class, from the HTML a panel
 * page load returned.
 *
 * @param  class-string  $component
 * @return array<string, mixed>
 */
function panelPageSnapshot(string $html, string $component): array
{
    preg_match_all('/wire:snapshot="([^"]*)"/', $html, $matches);

    foreach ($matches[1] as $encoded) {
        $snapshot = json_decode(html_entity_decode($encoded, ENT_QUOTES), true);

        if ($snapshot['memo']['name'] === $component) {
            return $snapshot;
        }
    }

    throw new RuntimeException("No {$component} component on the page.");
}

it('re-checks admin access on Livewire requests, so a removed admin can no longer act', function () {
    $admin = User::factory()->admin()->create();
    $customer = User::factory()->create();
    $this->actingAs($admin);

    $html = $this->get(CustomerResource::getUrl('view', ['record' => $customer]))->assertOk()->getContent();
    $snapshot = panelPageSnapshot($html, ViewCustomer::class);

    // A click on Adjust wallet, as the browser sends it. Livewire keeps which
    // route's middleware it last ran until its state is flushed, which a real
    // request does on its own.
    $openAdjustWallet = function () use ($snapshot) {
        Livewire::flushState();

        return $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => [],
                'calls' => [['method' => 'mountAction', 'params' => ['adjustWallet'], 'path' => '']],
            ]],
        ]);
    };

    $openAdjustWallet()->assertOk();

    // Another admin removes this one while their tab is still open. Saved
    // through the logged-in model, as the next request would load it fresh.
    $admin->forceFill(['is_admin' => false])->save();

    $openAdjustWallet()->assertNotFound();
});

it('refuses a Store settings save from an open tab once admin access is removed', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $html = $this->get(EditStoreSettings::getUrl())->assertOk()->getContent();
    $snapshot = panelPageSnapshot($html, EditStoreSettings::class);

    // A click on Save with a new amount, as the browser sends it.
    $save = function (string $amount) use ($snapshot) {
        Livewire::flushState();

        return $this->withHeader('X-Livewire', 'true')->postJson(Livewire::getUpdateUri(), [
            'components' => [[
                'snapshot' => json_encode($snapshot),
                'updates' => ['data.signup_credit' => $amount],
                'calls' => [['method' => 'save', 'params' => [], 'path' => '']],
            ]],
        ]);
    };

    $save('1')->assertOk();
    expect(StoreSettings::current()->signup_credit->cents())->toBe(100);

    $admin->forceFill(['is_admin' => false])->save();

    $save('2')->assertNotFound();
    expect(StoreSettings::current()->signup_credit->cents())->toBe(100);
});

it('ends the panel menu with a Go to store link to the home page', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get('/admin')
        ->assertOk()
        ->assertSeeInOrder(['Store settings', 'Go to store'])
        ->assertSee('href="'.route('home').'"', false);
});

it('lists the admin check among Livewire\'s persistent middleware', function () {
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())->toContain(AuthenticateAdmin::class);
});
