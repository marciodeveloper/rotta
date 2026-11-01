<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEvent;
use App\Services\AdminSecondFactorAuthenticator;
use App\Services\SensitiveHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReauthenticateController extends Controller
{
    public function create(): View
    {
        return view('admin.reauthenticate');
    }

    public function store(Request $request, AdminSecondFactorAuthenticator $secondFactor, SensitiveHash $hash): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string'], 'code' => ['required', 'string', 'max:32']]);
        $admin = $request->user('admin');
        if (! Hash::check($validated['password'], $admin->password) || ! $secondFactor->verify($admin, $validated['code'])) {
            throw ValidationException::withMessages(['code' => 'Não foi possível confirmar sua identidade.']);
        }
        $request->session()->put('admin_mfa_verified_at', now()->timestamp);
        AdminAuditEvent::query()->create([
            'admin_id' => $admin->id,
            'action' => 'admin.reauthenticated',
            'ip_hash' => $hash->makeFor('ip-address', (string) $request->ip()),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }
}
