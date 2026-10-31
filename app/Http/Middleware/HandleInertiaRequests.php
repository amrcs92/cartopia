<?php

namespace App\Http\Middleware;

use App\Cart\Cart;
use App\Catalog\Aisles;
use App\Http\Resources\AisleResource;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Report the URL exactly as requested. The default resolver goes through
     * fullUrl(), which rewrites `aisle[]=x` into `aisle[0]=x` and would push
     * that into the browser history.
     */
    public function urlResolver(): Closure
    {
        return fn (Request $request): string => $request->getRequestUri();
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => fn (): ?array => $this->currentUser($request->user()),
            ],
            'cart' => fn (): array => ['count' => app(Cart::class)->count()],
            'flash' => Inertia::always(fn (): array => [
                'success' => $request->session()->get('success'),
                'error' => $request->session()->get('error'),
            ]),
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // Only sent when the phone menu or the footer asks for it; once
            // loaded, the client keeps it across visits instead of refetching.
            'menuAisles' => Inertia::optional(fn (): array => AisleResource::collection(Aisles::all())->resolve())->once(),
        ];
    }

    /**
     * @return array{id: int, name: string, email: string, balance: string, balance_cents: int, is_admin: bool}|null
     */
    private function currentUser(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'balance' => $user->balance->format(),
            'balance_cents' => $user->balance->cents(),
            'is_admin' => $user->is_admin,
        ];
    }
}
