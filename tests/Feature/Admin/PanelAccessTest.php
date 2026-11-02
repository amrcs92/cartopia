<?php

use App\Filament\Resources\Customers\CustomerResource;
use App\Filament\Resources\Customers\Pages\ViewCustomer;
use App\Http\Middleware\AuthenticateAdmin;
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

it('lists the admin check among Livewire\'s persistent middleware', function () {
    expect(app(PersistentMiddleware::class)->getPersistentMiddleware())->toContain(AuthenticateAdmin::class);
});
