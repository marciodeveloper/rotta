<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Services\SensitiveHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function create(): View
    {
        return view('admin.login');
    }

    public function store(LoginRequest $request, SensitiveHash $hash): RedirectResponse
    {
        $admin = Admin::query()->where('email', $request->string('email')->lower()->toString())->first();
        if ($admin === null || ! $admin->is_active || ! Hash::check($request->string('password')->toString(), $admin->password)) {
            throw ValidationException::withMessages(['email' => 'As credenciais informadas não são válidas.']);
        }

        Auth::guard('admin')->login($admin);
        $request->session()->regenerate();
        $request->session()->forget(['admin_mfa_pending_id', 'admin_mfa_verified_at']);
        $request->session()->put('admin_password_confirmed_at', now()->timestamp);
        AdminAuditEvent::query()->create([
            'admin_id' => $admin->id,
            'action' => 'admin.login',
            'ip_hash' => $hash->makeFor('ip-address', (string) $request->ip()),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('admin')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
