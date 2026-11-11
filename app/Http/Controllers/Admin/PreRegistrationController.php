<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPreRegistrationsRequest;
use App\Models\PreRegistration;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PreRegistrationController extends Controller
{
    public function show(ListPreRegistrationsRequest $request, PreRegistration $preRegistration): View
    {
        Gate::authorize('view-pre-registration', $preRegistration);
        $preRegistration->load(['privacyNotice', 'media', 'statusHistory' => fn ($query) => $query->with('admin:id,name')->orderBy('created_at')->orderBy('id')]);

        return view('admin.pre-registration', [
            'registration' => $preRegistration,
            'listContext' => $request->validated(),
        ]);
    }
}
