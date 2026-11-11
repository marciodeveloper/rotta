<?php

namespace App\Services;

use Illuminate\Database\Eloquent\Builder;

class PreRegistrationFilters
{
    public function apply(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status): Builder => $builder->where('status', $status))
            ->when($filters['state'] ?? null, fn (Builder $builder, string $state): Builder => $builder->where('state', strtoupper($state)))
            ->when($filters['category'] ?? null, fn (Builder $builder, string $category): Builder => $builder->where('category', $category))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $search): Builder => $builder->where('business_name', 'ilike', '%'.addcslashes($search, '%_\\').'%'));
    }
}
