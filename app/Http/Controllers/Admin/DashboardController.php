<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PreRegistration;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', PreRegistration::STATUSES)],
            'state' => ['nullable', 'string', 'size:2', 'alpha'],
            'category' => ['nullable', 'string', 'max:100'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $query = PreRegistration::query()
            ->select(['id', 'business_name', 'category', 'city', 'state', 'status', 'status_version', 'document_last_four', 'email_confirmed_at', 'created_at'])
            ->when($filters['status'] ?? null, fn (Builder $builder, string $status): Builder => $builder->where('status', $status))
            ->when($filters['state'] ?? null, fn (Builder $builder, string $state): Builder => $builder->where('state', strtoupper($state)))
            ->when($filters['category'] ?? null, fn (Builder $builder, string $category): Builder => $builder->where('category', $category))
            ->when($filters['q'] ?? null, fn (Builder $builder, string $search): Builder => $builder->where('business_name', 'ilike', '%'.addcslashes($search, '%_').'%'))
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        return view('admin.dashboard', [
            'registrations' => $query->paginate(25)->withQueryString(),
            'counts' => PreRegistration::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => $filters,
            'categories' => config('prelaunch.categories'),
        ]);
    }
}
