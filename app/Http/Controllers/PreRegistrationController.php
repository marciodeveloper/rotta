<?php

namespace App\Http\Controllers;

use App\Contracts\TurnstileVerifier;
use App\Http\Requests\StorePreRegistrationRequest;
use App\Models\PrivacyNotice;
use App\Services\CreatePreRegistration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PreRegistrationController extends Controller
{
    public function create(): View
    {
        $publishedNotice = PrivacyNotice::query()->currentPublished()->first();
        $displayedNotice = $publishedNotice ?? PrivacyNotice::query()->latest()->first();
        abort_if($displayedNotice === null, 503, 'Privacy notice unavailable.');

        return view('pre-registration.create', [
            'privacyNotice' => $displayedNotice,
            'registrationOpen' => $publishedNotice !== null,
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

        $privacyNotice = PrivacyNotice::query()->currentPublished()->first();
        if ($privacyNotice === null || ! hash_equals($privacyNotice->version, $request->string('privacy_notice_version')->toString())) {
            throw ValidationException::withMessages(['privacy_accepted' => 'O aviso de privacidade foi atualizado. Revise-o antes de continuar.']);
        }

        if (! $turnstile->verify($request->string('cf-turnstile-response')->toString(), $request->ip())) {
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Não foi possível validar a verificação de segurança. Tente novamente.']);
        }

        $createPreRegistration->handle($request->validated(), $privacyNotice);

        return redirect()->route('pre-registration.success');
    }

    public function success(): View
    {
        return view('pre-registration.success');
    }
}
