<?php

namespace App\Providers;

use App\Contracts\CepLookup;
use App\Contracts\TurnstileVerifier;
use App\Services\CloudflareTurnstileVerifier;
use App\Services\ViaCepLookup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
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
        $this->app->bind(TurnstileVerifier::class, CloudflareTurnstileVerifier::class);
        $this->app->bind(CepLookup::class, ViaCepLookup::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        RateLimiter::for('pre-registration', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('confirmation', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('cep', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            hash('sha256', Str::lower($request->string('email')->toString()).'|'.$request->ip()),
        ));
        RateLimiter::for('admin-mfa', fn (Request $request): Limit => Limit::perMinute(5)->by(
            hash('sha256', (string) $request->session()->get('admin_mfa_pending_id').'|'.$request->ip()),
        ));
        RateLimiter::for('admin-export', fn (Request $request): Limit => Limit::perHour(5)->by((string) $request->user('admin')?->id));
    }
}
