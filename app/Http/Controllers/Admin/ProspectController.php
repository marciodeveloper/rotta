<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListProspectsRequest;
use App\Http\Requests\Admin\ProspectContactRequest;
use App\Http\Requests\Admin\SaveProspectRequest;
use App\Http\Requests\Admin\StoreProspectSourceRequest;
use App\Models\Admin;
use App\Models\Prospect;
use App\Services\AddProspectSource;
use App\Services\CreateProspect;
use App\Services\DetectProspectDuplicates;
use App\Services\ManageProspectContact;
use App\Services\NormalizeProspect;
use App\Services\UpdateProspect;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ProspectController extends Controller
{
    public function index(ListProspectsRequest $request): View
    {
        $filters = $request->validated();
        $query = Prospect::with('assignee:id,name')->select(array_map(fn ($column) => 'prospects.'.$column, ['id', 'business_name', 'category', 'city', 'state', 'priority', 'assigned_to', 'commercial_status', 'contact_blocked_at', 'created_at']));
        foreach (['state', 'category', 'assigned_to', 'priority', 'commercial_status'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        foreach (['q' => 'business_name', 'city' => 'city'] as $filter => $field) {
            if (! empty($filters[$filter])) {
                $query->where($field, 'ilike', '%'.addcslashes($filters[$filter], '%_\\').'%');
            }
        }
        if (! empty($filters['blocked'])) {
            $filters['blocked'] === 'yes' ? $query->whereNotNull('contact_blocked_at') : $query->whereNull('contact_blocked_at');
        }

        $direction = $filters['direction'] ?? 'desc';
        if (($filters['sort'] ?? 'created_at') === 'assigned_to') {
            $query->leftJoin('admins as assigned_admin', 'assigned_admin.id', '=', 'prospects.assigned_to')->orderByRaw('assigned_admin.name '.($direction === 'asc' ? 'ASC' : 'DESC').' NULLS LAST');
        } else {
            $query->orderBy('prospects.'.($filters['sort'] ?? 'created_at'), $direction);
        }

        return view('admin.prospects.index', ['prospects' => $query->orderByDesc('prospects.id')->paginate((int) ($filters['per_page'] ?? 25))->appends($filters), 'filters' => $filters, 'admins' => $this->admins()]);
    }

    public function create(ListProspectsRequest $request, DetectProspectDuplicates $duplicates, NormalizeProspect $normalize): View
    {
        Gate::authorize('create', Prospect::class);

        return $this->form($request, new Prospect, $duplicates, $normalize);
    }

    public function edit(ListProspectsRequest $request, Prospect $prospect, DetectProspectDuplicates $duplicates, NormalizeProspect $normalize): View
    {
        Gate::authorize('update', $prospect);

        return $this->form($request, $prospect, $duplicates, $normalize);
    }

    private function form(ListProspectsRequest $request, Prospect $prospect, DetectProspectDuplicates $duplicates, NormalizeProspect $normalize): View
    {
        $candidates = collect();
        if ($request->session()->hasOldInput('business_name')) {
            $data = $normalize->handle($request->session()->getOldInput());
            if (is_string($data['business_name']) && is_string($data['city'])) {
                $candidates = $duplicates->find($data, $prospect->exists ? $prospect->id : null);
            }
        }

        return view('admin.prospects.form', ['prospect' => $prospect, 'filters' => $request->validated(), 'admins' => $this->admins(), 'candidates' => $candidates, 'submissionToken' => (string) Str::uuid()]);
    }

    public function store(SaveProspectRequest $request, CreateProspect $create): RedirectResponse
    {
        $context = $this->context($request);
        try {
            $prospect = $create->handle($request->validated(), $request->user('admin'));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('admin.prospects.create', $context));
        }

        return redirect()->route('admin.prospects.show', ['prospect' => $prospect, ...$context])->with('status', 'Prospect cadastrado com segurança.');
    }

    public function update(SaveProspectRequest $request, Prospect $prospect, UpdateProspect $update): RedirectResponse
    {
        $context = $this->context($request);
        try {
            $update->handle($prospect, $request->validated(), $request->user('admin'));
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('admin.prospects.edit', ['prospect' => $prospect, ...$context]));
        }

        return redirect()->route('admin.prospects.show', ['prospect' => $prospect, ...$context])->with('status', 'Dados atualizados.');
    }

    public function show(ListProspectsRequest $request, Prospect $prospect): View
    {
        Gate::authorize('view', $prospect);
        $prospect->load(['assignee:id,name']);
        $confirmers = Admin::whereIn('id', collect($prospect->confirmed_fields ?? [])->pluck('admin_id'))->pluck('name', 'id');

        return view('admin.prospects.show', ['prospect' => $prospect, 'filters' => $request->validated(), 'sources' => $prospect->sources()->with('author:id,name')->latest()->paginate(10, ['*'], 'sources_page')->withQueryString(), 'activities' => $prospect->activities()->with('admin:id,name')->orderByDesc('created_at')->orderByDesc('id')->paginate(20, ['*'], 'activities_page')->withQueryString(), 'confirmers' => $confirmers, 'submissionToken' => (string) Str::uuid()]);
    }

    public function contact(ProspectContactRequest $request, Prospect $prospect, ManageProspectContact $manage): RedirectResponse
    {
        $context = $this->context($request);
        try {
            $manage->handle($prospect, $request->user('admin'), $request->validated());
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('admin.prospects.show', ['prospect' => $prospect, ...$context]));
        }

        return redirect()->route('admin.prospects.show', ['prospect' => $prospect, ...$context])->with('status', 'Preferência de contato atualizada.');
    }

    public function source(StoreProspectSourceRequest $request, Prospect $prospect, AddProspectSource $add): RedirectResponse
    {
        $context = $this->context($request);
        try {
            $add->handle($prospect, $request->user('admin'), $request->validated());
        } catch (ValidationException $exception) {
            throw $exception->redirectTo(route('admin.prospects.show', ['prospect' => $prospect, ...$context]));
        }

        return redirect()->route('admin.prospects.show', ['prospect' => $prospect, ...$context])->with('status', 'Origem adicionada. Os dados e o bloqueio foram preservados.');
    }

    private function context(Request $request): array
    {
        return Validator::make($request->query(), ListProspectsRequest::filterRules())->validate();
    }

    private function admins(): Collection
    {
        return Admin::where('is_active', true)->orderBy('name')->get(['id', 'name']);
    }
}
