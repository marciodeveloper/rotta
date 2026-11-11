<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEvent;
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

    public function store(Request $request, SensitiveHash $hash): RedirectResponse
    {
        $validated = $request->validate(['password' => ['required', 'string', 'max:1024']]);
        $admin = $request->user('admin');
        if (! Hash::check($validated['password'], $admin->password)) {
            throw ValidationException::withMessages(['password' => 'A senha informada não é válida.']);
        }
        $request->session()->regenerate();
        $request->session()->put('admin_password_confirmed_at', now()->timestamp);
        AdminAuditEvent::query()->create([
            'admin_id' => $admin->id,
            'action' => 'admin.reauthenticated',
            'ip_hash' => $hash->makeFor('ip-address', (string) $request->ip()),
        ]);

        return redirect()->intended(route('admin.dashboard'));
    }
}
