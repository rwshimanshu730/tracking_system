<?php

namespace App\Providers;

use App\Models\Device;
use App\Models\Notification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        View::composer('layouts.app', function ($view) {
            if (! Auth::check()) {
                return;
            }

            $view->with([
                'onlineDevices' => Device::where('is_online', true)
                    ->where('last_seen_at', '>=', now()->subMinutes(5))
                    ->count(),
                'unreadNotifications' => Notification::where('is_read', false)->count(),
            ]);
        });

        RateLimiter::for('tracking', function (Request $request) {
            $key = $request->input('machine_name')
                ?: $request->ip();

            return [
                Limit::perMinute(1000)->by($key),
                Limit::perMinute(500)->by($request->ip()),
            ];
        });
    }
}
