<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditEvent;
use App\Models\PreRegistration;
use App\Services\SensitiveHash;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, SensitiveHash $hash): StreamedResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', 'string', 'in:'.implode(',', PreRegistration::STATUSES)],
            'state' => ['nullable', 'string', 'size:2', 'alpha'],
            'category' => ['nullable', 'string', 'max:100'],
        ]);

        AdminAuditEvent::query()->create([
            'admin_id' => $request->user('admin')->id,
            'action' => 'pre_registration.exported',
            'metadata' => ['filters' => $filters, 'contract_version' => '1.0'],
            'ip_hash' => $hash->make((string) $request->ip()),
        ]);

        return response()->streamDownload(function () use ($filters): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                throw new \RuntimeException('Unable to open export stream.');
            }

            fputcsv($stream, ['contract_version', 'source_id', 'applicant_type', 'document', 'business_name', 'category', 'description', 'responsible_name', 'email', 'phone', 'website', 'instagram', 'address_mode', 'postal_code', 'street', 'number', 'complement', 'neighborhood', 'city', 'state', 'service_area', 'status', 'email_confirmed_at', 'privacy_notice_version', 'source', 'created_at']);

            PreRegistration::query()
                ->when($filters['status'] ?? null, fn (Builder $query, string $status): Builder => $query->where('status', $status))
                ->when($filters['state'] ?? null, fn (Builder $query, string $state): Builder => $query->where('state', strtoupper($state)))
                ->when($filters['category'] ?? null, fn (Builder $query, string $category): Builder => $query->where('category', $category))
                ->orderBy('id')
                ->lazyById(200)
                ->each(function (PreRegistration $registration) use ($stream): void {
                    fputcsv($stream, array_map($this->safeCsv(...), [
                        '1.0', $registration->id, $registration->applicant_type, $registration->document,
                        $registration->business_name, $registration->category, $registration->short_description,
                        $registration->responsible_name, $registration->email, $registration->phone,
                        $registration->website, $registration->instagram, $registration->address_mode,
                        $registration->postal_code, $registration->street, $registration->street_number,
                        $registration->complement, $registration->neighborhood, $registration->city,
                        $registration->state, $registration->service_area, $registration->status,
                        $registration->email_confirmed_at?->toIso8601String(), $registration->privacy_notice_version,
                        $registration->source, $registration->created_at->toIso8601String(),
                    ]));
                });

            fclose($stream);
        }, 'rotta-pre-cadastros-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-ROTTA-Contract-Version' => '1.0',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function safeCsv(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@]/', $value) ? "'".$value : $value;
    }
}
