<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        // Provider webhooks are authenticated by their signatures, not a CSRF token.
        $middleware->validateCsrfTokens(except: ['webhooks/*']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Route-model binding throws a 404 for a missing slug; these two must
        // already have run so that 404 page gets the shared props and theme.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: HandleAppearance::class);
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: HandleInertiaRequests::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($response->getStatusCode() === 404 && ! $request->expectsJson()) {
                return Inertia::render('errors/NotFoundPage')->toResponse($request)->setStatusCode(404);
            }

            return $response;
        });
    })->create();
