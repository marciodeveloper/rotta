<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LoginRequest;
use App\Models\Admin;
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

    public function store(LoginRequest $request): RedirectResponse
    {
        $admin = Admin::query()->where('email', $request->string('email')->lower()->toString())->first();
        if ($admin === null || ! $admin->is_active || ! Hash::check($request->string('password')->toString(), $admin->password)) {
            throw ValidationException::withMessages(['email' => 'As credenciais informadas não são válidas.']);
        }

        $request->session()->put('admin_mfa_pending_id', $admin->id);

        return redirect()->route('admin.mfa.create');
    }

    public function destroy(): RedirectResponse
    {
        Auth::guard('admin')->logout();
        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}
