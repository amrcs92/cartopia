<?php

namespace App\Providers;

use App\Cart\CartStore;
use App\Cart\DatabaseCartStore;
use App\Cart\SessionCartStore;
use App\Enums\PaymentMethod;
use App\Models\User;
use App\Payments\PaymentGateway;
use App\Payments\PaymentGateways;
use App\Payments\PayPalGateway;
use App\Payments\StripeGateway;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(CartStore::class, fn (): CartStore => auth()->user() instanceof User
            ? new DatabaseCartStore(auth()->user())
            : new SessionCartStore(app('session.store')));

        $this->app->singleton(PaymentGateways::class, fn (): PaymentGateways => new PaymentGateways([
            PaymentMethod::Stripe->value => fn (): PaymentGateway => new StripeGateway((string) config('services.stripe.secret')),
            PaymentMethod::PayPal->value => fn (): PaymentGateway => new PayPalGateway((string) config('services.paypal.client_id'), (string) config('services.paypal.secret')),
        ]));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
