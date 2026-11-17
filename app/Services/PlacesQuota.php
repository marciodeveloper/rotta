<?php

namespace App\Services;

use App\Exceptions\PlacesUnavailable;
use App\Models\Admin;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

class PlacesQuota
{
    public function reserve(Admin $admin, string $budget = 'places'): void
    {
        if (! app()->environment('testing') && (in_array(config('cache.default'), ['array', 'null'], true) || in_array(config('cache.limiter'), ['array', 'null'], true))) {
            throw new PlacesUnavailable('As quotas exigem armazenamento persistente. Solicite a revisão da configuração de cache ao administrador.', 503, ['category' => 'quota_configuration_error']);
        }
        try {
            Cache::lock($budget.'-budget-v1', 10)->block(2, function () use ($admin, $budget): void {
                $day = now()->utc()->format('Y-m-d');
                $limits = [
                    [$budget.':minute:'.$admin->id, (int) config('business_explorer.per_admin_minute'), 60],
                    [$budget.':day:'.$day.':'.$admin->id, (int) config('business_explorer.per_admin_day'), 86400],
                    [$budget.':global:'.$day, (int) config('business_explorer.global_day'), 86400],
                ];
                foreach ($limits as [$key, $limit]) {
                    if ($limit < 1 || RateLimiter::tooManyAttempts($key, $limit)) {
                        throw new PlacesUnavailable('O limite de consultas do Explorador foi atingido. Aguarde a renovação da quota.', 429);
                    }
                }
                foreach ($limits as [$key, $limit, $seconds]) {
                    RateLimiter::hit($key, $seconds);
                }
            });
        } catch (PlacesUnavailable $exception) {
            throw $exception;
        } catch (Throwable) {
            throw new PlacesUnavailable('Não foi possível verificar a quota do Explorador. Tente novamente mais tarde.');
        }
    }
}
