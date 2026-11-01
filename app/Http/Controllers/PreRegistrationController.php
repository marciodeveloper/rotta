<?php

namespace App\Http\Controllers;

use App\Contracts\TurnstileVerifier;
use App\Http\Requests\StorePreRegistrationRequest;
use App\Mail\ConfirmPreRegistrationMail;
use App\Models\PrivacyNotice;
use App\Services\CreatePreRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PreRegistrationController extends Controller
{
    public function create(): View
    {
        return view('pre-registration.create', [
            'privacyNotice' => PrivacyNotice::query()->where('is_current', true)->firstOrFail(),
            'categories' => config('prelaunch.categories'),
            'turnstileSiteKey' => config('prelaunch.turnstile.site_key'),
            'googleMapsApiKey' => config('prelaunch.google_places.api_key'),
        ]);
    }

    public function store(
        StorePreRegistrationRequest $request,
        TurnstileVerifier $turnstile,
        CreatePreRegistration $createPreRegistration,
    ): RedirectResponse {
        if ($request->filled('company_website')) {
            return redirect()->route('pre-registration.success');
        }

        if (! $turnstile->verify($request->string('cf-turnstile-response')->toString(), $request->ip())) {
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Não foi possível validar a verificação de segurança. Tente novamente.']);
        }

        $privacyNotice = PrivacyNotice::query()->where('is_current', true)->firstOrFail();
        if (! hash_equals($privacyNotice->version, $request->string('privacy_notice_version')->toString())) {
            throw ValidationException::withMessages(['privacy_accepted' => 'O aviso de privacidade foi atualizado. Revise-o antes de continuar.']);
        }

        $result = $createPreRegistration->handle($request->validated(), $privacyNotice);
        if ($result !== null) {
            Mail::to($result['registration']->email)->send(new ConfirmPreRegistrationMail($result['registration'], $result['token']));
        }

        return redirect()->route('pre-registration.success');
    }

    public function success(): View
    {
        return view('pre-registration.success');
    }
}
