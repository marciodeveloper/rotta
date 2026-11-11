<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureRecentAdminPassword
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless($request->user('admin')?->can('export-pre-registrations'), 403);
        $confirmedAt = $request->session()->get('admin_password_confirmed_at');
        $maximumAge = (int) config('prelaunch.admin_export_password_ttl_minutes', 15) * 60;
        if (! is_int($confirmedAt) || $confirmedAt < now()->timestamp - $maximumAge || $confirmedAt > now()->timestamp) {
            $request->session()->put('url.intended', route('admin.dashboard', $request->only(['q', 'status', 'state', 'category'])));

            return redirect()->route('admin.reauthenticate')->with('status', 'Confirme sua senha e solicite novamente a exportação.');
        }

        return $next($request);
    }
}
