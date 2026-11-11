<?php

namespace App\Providers;

use App\Contracts\CepLookup;
use App\Contracts\ConfirmationMailSender;
use App\Contracts\TurnstileVerifier;
use App\Models\Admin;
use App\Models\PreRegistration;
use App\Services\CloudflareTurnstileVerifier;
use App\Services\LaravelConfirmationMailSender;
use App\Services\ViaCepLookup;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
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
        $this->app->bind(ConfirmationMailSender::class, LaravelConfirmationMailSender::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());
        Gate::define('export-pre-registrations', fn ($admin): bool => $admin->is_active && $admin->can_export);
        Gate::define('view-pre-registration', fn (Admin $admin, PreRegistration $registration): bool => $admin->is_active);
        Gate::define('manage-pre-registration', fn (Admin $admin, PreRegistration $registration): bool => $admin->is_active);

        RateLimiter::for('pre-registration', fn (Request $request): Limit => Limit::perHour(5)->by($request->ip()));
        RateLimiter::for('confirmation', fn (Request $request): Limit => Limit::perMinute(20)->by($request->ip()));
        RateLimiter::for('confirmation-resend', fn (Request $request): Limit => Limit::perHour(3)->by($request->ip()));
        RateLimiter::for('cep', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip()));
        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(5)->by(
            hash('sha256', Str::lower($request->string('email')->toString()).'|'.$request->ip()),
        ));
        RateLimiter::for('admin-reauthenticate', fn (Request $request): Limit => Limit::perMinute(5)->by(
            hash('sha256', (string) $request->user('admin')?->id.'|'.$request->ip()),
        ));
        RateLimiter::for('admin-export', fn (Request $request): Limit => Limit::perHour(5)->by((string) $request->user('admin')?->id));
        RateLimiter::for('admin-confirmation-resend', function (Request $request): array {
            $registration = $request->route('preRegistration');
            $id = $registration instanceof PreRegistration ? $registration->id : (string) $registration;

            return [
                Limit::perMinute(1)->by('admin-resend:'.$request->user('admin')?->id.':'.$id),
                Limit::perHour(3)->by('registration-resend:'.$id),
            ];
        });
    }
}
