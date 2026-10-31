<?php

namespace App\Providers;

use App\Cart\CartStore;
use App\Cart\DatabaseCartStore;
use App\Cart\SessionCartStore;
use App\Models\User;
use App\Payments\PaymentGateways;
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

        // Tasks 5 and 6 add the 'stripe' and 'paypal' factories.
        $this->app->singleton(PaymentGateways::class, fn (): PaymentGateways => new PaymentGateways([]));
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
