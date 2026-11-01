<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdatePreRegistrationStatusRequest;
use App\Models\AdminAuditEvent;
use App\Models\PreRegistration;
use App\Models\PreRegistrationStatusHistory;
use App\Services\SensitiveHash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class StatusController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(UpdatePreRegistrationStatusRequest $request, PreRegistration $preRegistration, SensitiveHash $hash): RedirectResponse
    {
        DB::transaction(function () use ($request, $preRegistration, $hash): void {
            $locked = PreRegistration::query()->lockForUpdate()->findOrFail($preRegistration->id);
            $fromStatus = $locked->status;
            $locked->update(['status' => $request->string('status')->toString()]);
            PreRegistrationStatusHistory::query()->create([
                'pre_registration_id' => $locked->id,
                'from_status' => $fromStatus,
                'to_status' => $locked->status,
                'admin_id' => $request->user('admin')->id,
                'reason' => $request->input('reason'),
            ]);
            AdminAuditEvent::query()->create([
                'admin_id' => $request->user('admin')->id,
                'action' => 'pre_registration.status_updated',
                'target_type' => PreRegistration::class,
                'target_id' => $locked->id,
                'metadata' => ['from' => $fromStatus, 'to' => $locked->status],
                'ip_hash' => $hash->make((string) $request->ip()),
            ]);
        }, 3);

        return back()->with('status', 'Status atualizado.');
    }
}
