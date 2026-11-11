<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResendConfirmationRequest;
use App\Models\PreRegistration;
use App\Services\AdminResendConfirmation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class ResendConfirmationController extends Controller
{
    public function __invoke(ResendConfirmationRequest $request, PreRegistration $preRegistration, AdminResendConfirmation $resend): JsonResponse|RedirectResponse
    {
        $resend->handle($preRegistration, $request->user('admin'), $request->integer('status_version'), $request->ip());
        $message = 'Reenvio solicitado. A confirmação será processada pela fila de e-mail.';
        if ($request->expectsJson()) {
            $request->session()->flash('status', $message);

            return response()->json(['message' => $message]);
        }

        return back()->with('status', $message);
    }
}
