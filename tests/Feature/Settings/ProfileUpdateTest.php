<?php

use App\Actions\Admin\RefundOrder;
use App\Enums\PaymentMethod;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\User;
use App\Models\WalletTransaction;
use App\Payments\PaymentGateways;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile page receives the editable profile fields', function () {
    $user = User::factory()->create(['phone' => '0123', 'address' => '1 Corniche Rd']);

    $this->actingAs($user)
        ->get(route('profile.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ProfilePage')
            ->where('profile', [
                'name' => $user->name,
                'email' => $user->email,
                'phone' => '0123',
                'address' => '1 Corniche Rd',
            ])
        );
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

/**
 * Refunds a placed $122.74 order, $100.00 from the wallet and $22.74 through
 * Stripe, so it has a refund row and a wallet credit pointing at it.
 */
function refundedOrderFor(User $shopper, User $admin): Order
{
    Mail::fake();
    PaymentGateways::fake();

    $order = Order::factory()->for($shopper)->create([
        'subtotal' => 12274, 'shipping_fee' => 0, 'total' => 12274,
        'payment_method' => PaymentMethod::Stripe, 'wallet_amount' => 10000,
    ]);
    Payment::factory()->forOrder($order)->succeeded()->create(['provider_payment_id' => 'pi_1']);

    app(RefundOrder::class)->handle($order, $admin, 'Damaged in transit');

    return $order;
}

test('a shopper with a refunded order can delete their account', function () {
    $shopper = User::factory()->create();
    $order = refundedOrderFor($shopper, User::factory()->admin()->create());
    $refund = $order->refund;

    $this->actingAs($shopper)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($shopper->fresh())->toBeNull()
        ->and(Order::find($order->id))->toBeNull()
        ->and(Refund::find($refund->id))->toBeNull()
        ->and(WalletTransaction::query()->where('refund_id', $refund->id)->exists())->toBeFalse();
});

test('an admin who issued a refund can be deleted, and the refund stays without them', function () {
    $admin = User::factory()->admin()->create();
    $order = refundedOrderFor(User::factory()->create(), $admin);

    $admin->delete();

    $refund = $order->refund()->sole();

    expect($refund->admin_id)->toBeNull()
        ->and($refund->admin)->toBeNull()
        ->and(WalletTransaction::query()->where('refund_id', $refund->id)->sole()->admin_id)->toBeNull();
});

test('an admin cannot delete their account until their admin access is removed', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors(['password' => 'Remove your admin access before deleting your account.'])
        ->assertRedirect(route('profile.edit'));

    $this->assertAuthenticatedAs($admin);
    expect($admin->fresh())->not->toBeNull();

    // Another admin removes their access; now the account can go.
    $admin->forceFill(['is_admin' => false])->save();

    $this->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    expect($admin->fresh())->toBeNull();
});
