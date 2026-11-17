<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\GooglePlaceReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

class SaveGooglePlaceReference
{
    public function __construct(private PlacesContext $context) {}

    public function handle(Admin $admin, string $token): GooglePlaceReference
    {
        Gate::forUser($admin)->authorize('create', GooglePlaceReference::class);
        $context = $this->context->read($admin, 'place', $token);
        $data = Validator::make($context, ['place_id' => ['required', 'string', 'regex:'.GooglePlaceReference::ID_PATTERN], 'queried_at' => ['required', 'date']])->validate();

        return DB::transaction(function () use ($admin, $data): GooglePlaceReference {
            DB::select('SELECT pg_advisory_xact_lock(731204, 1)');
            $reference = GooglePlaceReference::firstOrCreate(['place_id' => $data['place_id']], ['saved_by' => $admin->id, 'queried_at' => $data['queried_at']]);
            if ($reference->wasRecentlyCreated) {
                AdminAuditEvent::create(['admin_id' => $admin->id, 'action' => 'business_explorer.reference_saved', 'target_type' => GooglePlaceReference::class, 'target_id' => $reference->id, 'metadata' => ['provider' => 'google_places_new', 'storage_eligibility' => 'place_id_only']]);
            }

            return $reference;
        }, 3);
    }
}
