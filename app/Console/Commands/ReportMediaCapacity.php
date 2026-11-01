<?php

namespace App\Console\Commands;

use App\Services\MediaStorageCapacity;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prelaunch:media:capacity {--json : Emit machine-readable output}')]
#[Description('Measure private media usage and report quota or host capacity pressure')]
class ReportMediaCapacity extends Command
{
    public function handle(MediaStorageCapacity $capacity): int
    {
        $snapshot = $capacity->inspect();
        if ($this->option('json')) {
            $this->line((string) json_encode($snapshot, JSON_THROW_ON_ERROR));
        } else {
            $this->table(['Metric', 'Bytes'], [
                ['Used', $snapshot['used_bytes']],
                ['Free on host', $snapshot['free_bytes'] ?? 'not-applicable'],
                ['Alert threshold', $snapshot['alert_bytes']],
                ['Block threshold', $snapshot['block_bytes']],
                ['Minimum host free', $snapshot['minimum_free_bytes']],
                ['Level', $snapshot['level']],
            ]);
        }

        return $snapshot['level'] === 'blocked' ? self::FAILURE : self::SUCCESS;
    }
}
