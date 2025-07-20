<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;

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
        // ✅ Custom URL reset password ke API endpoint milik kita
        ResetPassword::createUrlUsing(function ($notifiable, string $token) {
            return url('/api/reset-password') . '?token=' . $token . '&email=' . urlencode($notifiable->email);
        });
    }
}
