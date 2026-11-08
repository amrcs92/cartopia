<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * When APP_URL is https, a page opened over http goes to the same page over
 * https. Photo and file URLs are built from APP_URL, so on an http page the
 * admin panel's uploader fetched them from what the browser counts as another
 * site, the fetch was blocked, and photos stayed on "Waiting for size".
 * Only GET and HEAD are redirected: a redirected POST (a provider webhook, a
 * form) would lose its body.
 */
class RedirectToHttps
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            ! $request->secure()
            && in_array($request->method(), ['GET', 'HEAD'], true)
            && str_starts_with((string) config('app.url'), 'https://')
        ) {
            return redirect()->secure($request->getRequestUri(), 301);
        }

        return $next($request);
    }
}
