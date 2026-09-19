<?php

// Local dev runs PHP 8.5 while CI/prod run 8.2 (composer platform). Laravel 11's
// own config files emit E_DEPRECATED notices on 8.5 during bootstrap — printed
// before headers, which silently kills ALL response headers (incl. CORS) under
// `artisan serve`. Mute E_DEPRECATED display early on 8.5+ only; 8.2 behavior
// (and CI) is untouched.
if (PHP_VERSION_ID >= 80500) {
    error_reporting(E_ALL & ~E_DEPRECATED);
}

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['role' => \App\Http\Middleware\RoleMiddleware::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
