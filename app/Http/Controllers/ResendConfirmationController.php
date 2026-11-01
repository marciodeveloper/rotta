<?php

namespace App\Http\Controllers;

use App\Contracts\TurnstileVerifier;
use App\Http\Requests\ResendConfirmationRequest;
use App\Models\PreRegistration;
use App\Services\DocumentValidator;
use App\Services\IssueConfirmationEmail;
use App\Services\SensitiveHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResendConfirmationController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        ResendConfirmationRequest $request,
        TurnstileVerifier $turnstile,
        DocumentValidator $documents,
        SensitiveHash $hash,
        IssueConfirmationEmail $issueConfirmationEmail,
    ): RedirectResponse {
        if (! $turnstile->verify($request->string('cf-turnstile-response')->toString(), $request->ip())) {
            throw ValidationException::withMessages(['cf-turnstile-response' => 'Não foi possível validar a verificação de segurança. Tente novamente.']);
        }

        $documentHash = $hash->make($documents->normalize($request->string('document')->toString()));
        $emailHash = $hash->make(Str::lower($request->string('email')->trim()->toString()));

        DB::transaction(function () use ($documentHash, $emailHash, $issueConfirmationEmail): void {
            $registration = PreRegistration::query()
                ->where('document_hash', $documentHash)
                ->where('email_hash', $emailHash)
                ->where('status', PreRegistration::STATUS_PENDING_EMAIL)
                ->whereNull('email_confirmed_at')
                ->lockForUpdate()
                ->first();

            if ($registration !== null) {
                $issueConfirmationEmail->handle($registration);
            }
        }, 3);

        return redirect()->route('pre-registration.success');
    }
}
