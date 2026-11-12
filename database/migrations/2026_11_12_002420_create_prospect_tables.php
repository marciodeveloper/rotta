<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prospects', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->uuid('creation_token')->unique();
            $table->char('creation_digest', 64);
            $table->string('business_name', 180);
            $table->string('normalized_name', 540);
            $table->string('category', 100)->nullable();
            $table->string('city', 100);
            $table->string('normalized_city', 300);
            $table->char('state', 2);
            foreach (['postal_code', 'street', 'street_number', 'complement', 'neighborhood', 'email', 'phone', 'website', 'instagram', 'notes', 'contact_block_reason'] as $field) {
                $table->text($field)->nullable();
            }
            $table->char('email_hash', 64)->nullable()->index();
            $table->char('phone_hash', 64)->nullable()->index();
            $table->string('priority', 10)->default('medium');
            $table->string('commercial_status', 20)->default('identified');
            $table->unsignedInteger('lock_version')->default(1);
            $table->jsonb('confirmed_fields')->default('{}');
            $table->foreignUuid('assigned_to')->nullable()->constrained('admins')->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('admins')->restrictOnDelete();
            $table->foreignUuid('updated_by')->constrained('admins')->restrictOnDelete();
            $table->timestampTz('contact_blocked_at')->nullable();
            $table->foreignUuid('contact_blocked_by')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestampsTz();
            $table->index(['normalized_name', 'normalized_city', 'state']);
            $table->index(['state', 'city']);
            $table->index(['assigned_to', 'created_at']);
            $table->index(['created_at', 'id']);
        });
        Schema::create('prospect_sources', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('created_by')->constrained('admins')->restrictOnDelete();
            $table->uuid('submission_token')->unique();
            $table->char('submission_digest', 64);
            $table->string('entry_type', 20)->default('manual');
            $table->string('provider', 60)->nullable();
            $table->text('external_id')->nullable();
            $table->text('origin_reference')->nullable();
            $table->text('usage_restrictions')->nullable();
            $table->string('purpose')->default('Prospecção comercial e planejamento de visitas ROTTA');
            $table->string('storage_eligibility', 30)->default('manual_assertion');
            $table->date('collected_at')->nullable();
            $table->timestampTz('queried_at')->nullable();
            $table->date('verified_at')->nullable();
            $table->timestampsTz();
            $table->index(['prospect_id', 'created_at']);
        });
        Schema::create('prospect_activities', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('prospect_id')->constrained()->restrictOnDelete();
            $table->foreignUuid('admin_id')->constrained('admins')->restrictOnDelete();
            $table->string('event', 40);
            $table->text('reason')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['prospect_id', 'created_at', 'id']);
        });
        DB::statement("ALTER TABLE prospects ADD CONSTRAINT prospects_values_check CHECK (lock_version > 0 AND priority IN ('low','medium','high') AND commercial_status = 'identified' AND state ~ '^[A-Z]{2}$' AND jsonb_typeof(confirmed_fields) = 'object')");
        DB::statement('ALTER TABLE prospects ADD CONSTRAINT prospects_contact_block_check CHECK ((contact_blocked_at IS NULL AND contact_blocked_by IS NULL AND contact_block_reason IS NULL) OR (contact_blocked_at IS NOT NULL AND contact_blocked_by IS NOT NULL AND contact_block_reason IS NOT NULL))');
        DB::statement("ALTER TABLE prospect_sources ADD CONSTRAINT prospect_sources_manual_check CHECK (entry_type = 'manual' AND provider IS NULL AND external_id IS NULL AND storage_eligibility = 'manual_assertion')");
    }

    public function down(): void
    {
        Schema::dropIfExists('prospect_activities');
        Schema::dropIfExists('prospect_sources');
        Schema::dropIfExists('prospects');
    }
};
