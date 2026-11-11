<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ExportPreRegistrationsRequest;
use App\Models\AdminAuditEvent;
use App\Models\PreRegistration;
use App\Services\PreRegistrationFilters;
use App\Services\SensitiveHash;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ExportController extends Controller
{
    public function __invoke(ExportPreRegistrationsRequest $request, SensitiveHash $hash, PreRegistrationFilters $filterQuery): StreamedResponse
    {
        $validated = $request->validated();
        $reason = $request->string('reason')->trim()->toString();
        $filters = array_filter([
            'status' => $validated['status'] ?? null,
            'state' => isset($validated['state']) ? strtoupper($validated['state']) : null,
            'category' => $validated['category'] ?? null,
            'q' => $validated['q'] ?? null,
        ]);
        $adminId = $request->user('admin')->id;
        $ipHash = $hash->makeFor('ip-address', (string) $request->ip());
        $requested = AdminAuditEvent::query()->create([
            'admin_id' => $adminId,
            'action' => 'pre_registration.export_requested',
            'metadata' => ['filters' => $filters, 'contract_version' => '1.1', 'reason' => $reason],
            'ip_hash' => $ipHash,
        ]);

        return response()->streamDownload(function () use ($filters, $adminId, $ipHash, $requested, $filterQuery): void {
            $stream = fopen('php://output', 'w');
            if ($stream === false) {
                $this->auditResult($adminId, $ipHash, $requested->id, 'failed', 0);
                throw new RuntimeException('Unable to open export stream.');
            }

            $rowCount = 0;
            try {
                fputcsv($stream, [
                    'contract_version', 'source_id', 'applicant_type', 'document_last_four', 'business_name',
                    'category', 'responsible_name', 'email', 'phone', 'city', 'state', 'status',
                    'email_confirmed_at', 'privacy_notice_version', 'created_at',
                ]);

                $filterQuery->apply(PreRegistration::query(), $filters)
                    ->select([
                        'id', 'applicant_type', 'document_last_four', 'business_name', 'category', 'responsible_name',
                        'email', 'phone', 'city', 'state', 'status', 'email_confirmed_at', 'privacy_notice_version', 'created_at',
                    ])
                    ->orderBy('id')
                    ->lazyById(200)
                    ->each(function (PreRegistration $registration) use ($stream, &$rowCount): void {
                        fputcsv($stream, array_map($this->safeCsv(...), [
                            '1.1', $registration->id, $registration->applicant_type, $registration->document_last_four,
                            $registration->business_name, $registration->category, $registration->responsible_name,
                            $registration->email, $registration->phone, $registration->city, $registration->state,
                            $registration->status, $registration->email_confirmed_at?->toIso8601String(),
                            $registration->privacy_notice_version, $registration->created_at->toIso8601String(),
                        ]));
                        $rowCount++;
                    });

                $this->auditResult($adminId, $ipHash, $requested->id, 'completed', $rowCount);
            } catch (Throwable $exception) {
                $this->auditResult($adminId, $ipHash, $requested->id, 'failed', $rowCount);
                throw $exception;
            } finally {
                fclose($stream);
            }
        }, 'rotta-pre-cadastros-'.now()->format('Y-m-d-His').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'X-ROTTA-Contract-Version' => '1.1',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    private function safeCsv(mixed $value): string
    {
        $value = str_replace("\0", '', (string) ($value ?? ''));

        return preg_match('/\A[\p{C}\p{Z}\s]*[=+\-@]/u', $value) === 1 ? "'".$value : $value;
    }

    private function auditResult(string $adminId, string $ipHash, string $requestId, string $result, int $rowCount): void
    {
        AdminAuditEvent::query()->create([
            'admin_id' => $adminId,
            'action' => 'pre_registration.export_'.$result,
            'metadata' => ['request_id' => $requestId, 'contract_version' => '1.1', 'row_count' => $rowCount],
            'ip_hash' => $ipHash,
        ]);
    }
}
