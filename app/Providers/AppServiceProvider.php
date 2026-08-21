<?php

namespace App\Providers;

use App\Models\RsvpLink;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

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
        RateLimiter::for('admin-login', function (Request $request) {
            $email = Str::lower(trim((string) $request->input('email')));

            return Limit::perMinute(5)
                ->by($email.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many login attempts. Please wait before trying again.',
                    ], 429, $headers);
                });
        });

        RateLimiter::for('public-rsvp', function (Request $request) {
            return Limit::perMinute(120)->by('public-rsvp|'.$request->ip());
        });

        RateLimiter::for('rsvp-submit', function (Request $request) {
            $rsvpLink = $request->route('rsvpLink');
            $linkIdentifier = $rsvpLink instanceof RsvpLink ? $rsvpLink->getKey() : (string) $rsvpLink;

            return Limit::perMinute(10)
                ->by('rsvp-submit|'.$linkIdentifier.'|'.$request->ip())
                ->response(function (Request $request, array $headers) {
                    return response()->json([
                        'message' => 'Too many RSVP attempts. Please wait before trying again.',
                    ], 429, $headers);
                });
        });
    }
}
