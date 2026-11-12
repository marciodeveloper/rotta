<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospect_identifiers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->restrictOnDelete();
            $table->string('kind', 24);
            $table->char('fingerprint', 64);
            $table->foreignUuid('recorded_by')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestampTz('created_at')->useCurrent();
            $table->unique(['prospect_id', 'kind', 'fingerprint']);
            $table->index(['kind', 'fingerprint', 'prospect_id']);
        });
        DB::statement("ALTER TABLE prospect_identifiers ADD CONSTRAINT prospect_identifiers_values_check CHECK (kind IN ('identity_location','email','phone') AND fingerprint ~ '^[a-f0-9]{64}$')");
        Schema::table('prospect_activities', function (Blueprint $table): void {
            $table->text('decision_reasons')->nullable();
        });
        if (DB::table('prospects')->exists()) {
            $pepper = config('prelaunch.data_pepper');
            if (! is_string($pepper) || $pepper === '') {
                throw new RuntimeException('Data pepper is required for prospect identifier backfill.');
            }
            DB::table('prospects')->orderBy('id')->chunkById(200, function ($rows) use ($pepper): void {
                foreach ($rows as $row) {
                    // Immutable v1 HMAC protocol; no plaintext contact is read or persisted.
                    $keys = [
                        'identity_location' => hash_hmac('sha256', "rotta-prelaunch\0prospect-identity\0".json_encode([$row->normalized_name, $row->normalized_city, $row->state], JSON_THROW_ON_ERROR), $pepper),
                        'email' => $row->email_hash,
                        'phone' => $row->phone_hash,
                    ];
                    foreach ($keys as $kind => $fingerprint) {
                        if ($fingerprint !== null) {
                            DB::table('prospect_identifiers')->insert(['id' => (string) Str::uuid(), 'prospect_id' => $row->id, 'kind' => $kind, 'fingerprint' => $fingerprint, 'recorded_by' => null, 'created_at' => now()]);
                        }
                    }
                }
            });
        }
    }

    public function down(): void
    {
        Schema::table('prospect_activities', function (Blueprint $table): void {
            $table->dropColumn('decision_reasons');
        });
        Schema::dropIfExists('prospect_identifiers');
    }
};
