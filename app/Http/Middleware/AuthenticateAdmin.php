<?php

namespace App\Http\Middleware;

use Filament\Facades\Filament;
use Filament\Http\Middleware\Authenticate;
use Filament\Models\Contracts\FilamentUser;

// Guards the admin panel with the store's login. A guest is sent to the store
// login, and a logged-in shopper gets the store's not-found page rather than
// Filament's 403, so the panel's existence is not given away.
class AuthenticateAdmin extends Authenticate
{
    protected function authenticate($request, array $guards): void
    {
        $user = Filament::auth()->user();

        abort_if(
            $user instanceof FilamentUser && ! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()),
            404,
        );

        parent::authenticate($request, $guards);
    }

    protected function redirectTo($request): ?string
    {
        return route('login');
    }
}
