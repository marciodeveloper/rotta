<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePreRegistrationStatusRequest;
use App\Models\PreRegistration;
use App\Services\TransitionPreRegistrationStatus;
use Illuminate\Http\RedirectResponse;

class StatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdatePreRegistrationStatusRequest $request, PreRegistration $preRegistration, TransitionPreRegistrationStatus $transition): RedirectResponse
    {
        $transition->handle(
            $preRegistration,
            $request->user('admin'),
            $request->string('status')->toString(),
            $request->string('reason')->trim()->toString() ?: null,
            $request->integer('status_version'),
            $request->ip(),
        );

        return back()->with('status', 'Status atualizado.');
    }
}
