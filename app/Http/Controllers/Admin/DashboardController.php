<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPreRegistrationsRequest;
use App\Models\PreRegistration;
use App\Services\PreRegistrationFilters;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ListPreRegistrationsRequest $request, PreRegistrationFilters $filterQuery): View
    {
        $filters = $request->validated();

        $query = $filterQuery->apply(PreRegistration::query(), $filters)
            ->select(['id', 'business_name', 'category', 'city', 'state', 'status', 'status_version', 'document_last_four', 'email_confirmed_at', 'created_at'])
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->orderByDesc('id');

        return view('admin.dashboard', [
            'registrations' => $query->paginate((int) ($filters['per_page'] ?? 25))->appends($filters),
            'counts' => PreRegistration::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => $filters,
            'categories' => config('prelaunch.categories'),
        ]);
    }
}
