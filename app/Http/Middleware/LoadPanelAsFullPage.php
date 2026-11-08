<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * The store is an Inertia app and the admin panel isn't. When an Inertia
 * request reaches a panel page (the redirect after logging in, or a link from
 * the store), this answers with Inertia's 409 so the browser loads the page
 * in full. Without it Inertia shows the panel's HTML inside a pop-up frame,
 * where product photos never finish loading.
 */
class LoadPanelAsFullPage
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasHeader('X-Inertia')) {
            return Inertia::location($request->fullUrl());
        }

        return $next($request);
    }
}
