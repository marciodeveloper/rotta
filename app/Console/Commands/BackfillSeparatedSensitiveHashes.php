<?php

namespace App\Console\Commands;

use App\Models\PreRegistration;
use App\Services\SensitiveHash;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prelaunch:hashes:backfill {--dry-run : Count rows without writing} {--chunk=100 : Rows per batch}')]
#[Description('Backfill domain-separated lookup hashes while retaining legacy hashes')]
class BackfillSeparatedSensitiveHashes extends Command
{
    public function handle(SensitiveHash $hash): int
    {
        $query = PreRegistration::query()->whereNull('document_hash_v2')->orWhereNull('email_hash_v2')->orWhereNull('phone_hash_v2');
        if ($this->option('dry-run')) {
            $this->info("{$query->count()} registrations require a backfill.");

            return self::SUCCESS;
        }

        $updated = 0;
        $query->orderBy('id')->chunk(max(1, (int) $this->option('chunk')), function ($registrations) use ($hash, &$updated): void {
            foreach ($registrations as $registration) {
                $registration->forceFill([
                    'document_hash_v2' => $hash->makeFor('document', $registration->document),
                    'email_hash_v2' => $hash->makeFor('email', $registration->email),
                    'phone_hash_v2' => $hash->makeFor('phone', $registration->phone),
                ])->save();
                $updated++;
            }
        });
        $this->info("Backfilled {$updated} registrations.");

        return self::SUCCESS;
    }
}
