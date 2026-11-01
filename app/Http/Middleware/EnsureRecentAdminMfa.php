<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentAdminMfa
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $verifiedAt = $request->session()->get('admin_mfa_verified_at');
        $maximumAge = (int) config('prelaunch.admin_export_mfa_ttl_minutes', 15) * 60;
        abort_unless(is_int($verifiedAt) && $verifiedAt >= now()->timestamp - $maximumAge, 403, 'Recent MFA verification required.');

        return $next($request);
    }
}
