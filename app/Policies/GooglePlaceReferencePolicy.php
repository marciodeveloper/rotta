<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\GooglePlaceReference;

class GooglePlaceReferencePolicy
{
    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function useForProspect(Admin $admin, GooglePlaceReference $reference): bool
    {
        return $admin->is_active;
    }
}
