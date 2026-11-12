<?php

namespace App\Policies;

use App\Models\Admin;
use App\Models\Prospect;

class ProspectPolicy
{
    public function reviewBlockedMatch(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function viewAny(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function view(Admin $admin, Prospect $prospect): bool
    {
        return $admin->is_active;
    }

    public function create(Admin $admin): bool
    {
        return $admin->is_active;
    }

    public function update(Admin $admin, Prospect $prospect): bool
    {
        return $admin->is_active;
    }

    public function manageContact(Admin $admin, Prospect $prospect): bool
    {
        return $admin->is_active;
    }

    public function addSource(Admin $admin, Prospect $prospect): bool
    {
        return $admin->is_active;
    }
}
