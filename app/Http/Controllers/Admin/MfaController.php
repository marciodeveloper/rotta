<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\VerifyMfaRequest;
use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Services\AdminSecondFactorAuthenticator;
use App\Services\SensitiveHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class MfaController extends Controller
{
    public function create(): View
    {
        abort_unless(session()->has('admin_mfa_pending_id'), 403);

        return view('admin.mfa');
    }

    public function store(VerifyMfaRequest $request, AdminSecondFactorAuthenticator $secondFactor, SensitiveHash $hash): RedirectResponse
    {
        $admin = Admin::query()->find($request->session()->get('admin_mfa_pending_id'));
        if ($admin === null || ! $admin->is_active || ! $secondFactor->verify($admin, $request->string('code')->toString())) {
            throw ValidationException::withMessages(['code' => 'O código de autenticação não é válido.']);
        }

        Auth::guard('admin')->login($admin);
        $request->session()->forget('admin_mfa_pending_id');
        $request->session()->regenerate();
        $request->session()->put('admin_mfa_verified_at', now()->timestamp);
        AdminAuditEvent::query()->create([
            'admin_id' => $admin->id,
            'action' => 'admin.login',
            'ip_hash' => $hash->makeFor('ip-address', (string) $request->ip()),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }
}
