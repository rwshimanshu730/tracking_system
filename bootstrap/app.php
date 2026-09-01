<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\EnsureUserRole;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => EnsureUserRole::class,
        ]);

        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->routeIs('employee.*')) {
                return route('employee.login');
            }

            if ($request->routeIs('customer.*')) {
                return route('customer.login');
            }

            if ($request->routeIs('pm.*')) {
                return match ($request->query('portal')) {
                    'employee' => route('employee.login'),
                    'customer' => route('customer.login'),
                    default => route('login'),
                };
            }

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
