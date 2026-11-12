<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\Prospect;

class ProspectAudit
{
    public function record(Prospect $prospect, Admin $admin, string $event, array $metadata = [], ?string $reason = null, array $decisions = []): void
    {
        $decisions = array_filter($decisions, fn ($value) => $value !== null);
        $activity = $prospect->activities()->create(['admin_id' => $admin->id, 'event' => $event, 'metadata' => $metadata, 'reason' => $reason, 'decision_reasons' => $decisions ?: null]);
        AdminAuditEvent::create(['admin_id' => $admin->id, 'target_type' => Prospect::class, 'target_id' => $prospect->id, 'action' => 'prospect.'.$event, 'metadata' => [...$metadata, 'activity_id' => $activity->id, 'decision_types' => array_keys($decisions)]]);
    }
}
