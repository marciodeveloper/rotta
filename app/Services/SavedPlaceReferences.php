<?php

namespace App\Services;

use App\Models\GooglePlaceReference;
use App\Models\Prospect;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SavedPlaceReferences
{
    public static function rules(): array
    {
        return ['page' => ['sometimes', 'integer', 'min:1', 'max:100000'], 'per_page' => ['sometimes', 'integer', Rule::in([10, 25, 50])], 'q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', Rule::in(['pending', 'linked', 'possible_duplicate'])]];
    }

    public static function returnContext(array $input): array
    {
        $values = [];
        foreach (self::rules() as $key => $rules) {
            if (array_key_exists('explorer_'.$key, $input)) {
                $values[$key] = $input['explorer_'.$key];
            }
        }

        return Validator::make($values, self::rules())->validate();
    }

    public function names(): array
    {
        $names = [];
        foreach (config('business_explorer.independent_sources', []) as $id => $source) {
            if (is_string($id) && is_array($source) && app(AuthorizedBusinessWebsite::class)->availability($id)['available']) {
                $names[$id] = $source['expected_name'];
            }
        }

        return $names;
    }

    public function duplicateIds(array $names): array
    {
        $normalizer = new NormalizeProspect;
        $keys = array_map($normalizer->key(...), $names);
        $existing = Prospect::whereIn('normalized_name', array_values($keys))->pluck('normalized_name')->all();

        return array_keys(array_filter($keys, fn ($name) => in_array($name, $existing, true)));
    }

    public function paginate(array $filters, array $names, array $duplicates): LengthAwarePaginator
    {
        $query = GooglePlaceReference::with('prospect:id,business_name,contact_blocked_at');
        $term = $filters['q'] ?? '';
        if ($term !== '') {
            $normalizer = new NormalizeProspect;
            $key = $normalizer->key($term);
            $ids = array_keys(array_filter($names, fn ($name) => str_contains($normalizer->key($name), $key)));
            $query->where(function ($query) use ($term, $key, $ids): void {
                $query->where('place_id', 'ilike', '%'.$term.'%')->orWhereHas('prospect', fn ($prospect) => $prospect->where('normalized_name', 'like', '%'.$key.'%'))->orWhereIn('place_id', $ids);
            });
        }
        match ($filters['status'] ?? null) {
            'linked' => $query->whereNotNull('prospect_id'),
            'pending' => $query->whereNull('prospect_id'),
            'possible_duplicate' => $query->whereNull('prospect_id')->whereIn('place_id', $duplicates),
            default => null,
        };
        $perPage = (int) ($filters['per_page'] ?? 10);
        $total = (clone $query)->count();
        $page = min((int) ($filters['page'] ?? 1), max(1, (int) ceil($total / $perPage)));

        return $query->orderByDesc('created_at')->orderByDesc('id')->paginate($perPage, ['*'], 'page', $page)->withQueryString();
    }
}
