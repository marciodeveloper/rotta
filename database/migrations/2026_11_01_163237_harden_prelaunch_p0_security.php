<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('privacy_notices')->where('is_current', true)->where('status', '!=', 'published')->update(['is_current' => false]);

        Schema::table('admins', function (Blueprint $table): void {
            $table->boolean('can_export')->default(false);
        });
        Schema::table('pre_registrations', function (Blueprint $table): void {
            $table->unsignedInteger('status_version')->default(1);
        });
        Schema::table('email_confirmation_tokens', function (Blueprint $table): void {
            $table->boolean('is_active')->default(true);
        });

        DB::statement(<<<'SQL'
            WITH ranked_tokens AS (
                SELECT id, row_number() OVER (PARTITION BY pre_registration_id ORDER BY created_at DESC, id DESC) AS position
                FROM email_confirmation_tokens
                WHERE used_at IS NULL AND expires_at > CURRENT_TIMESTAMP
            )
            UPDATE email_confirmation_tokens AS tokens
            SET is_active = false
            WHERE tokens.used_at IS NOT NULL
               OR tokens.expires_at <= CURRENT_TIMESTAMP
               OR tokens.id IN (SELECT id FROM ranked_tokens WHERE position > 1)
            SQL);

        Schema::create('email_outbox_messages', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pre_registration_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('email_confirmation_token_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('confirmation_token');
            $table->string('status')->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at')->useCurrent();
            $table->timestampTz('claimed_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->string('last_error', 160)->nullable();
            $table->timestamps();
            $table->index(['status', 'available_at']);
        });

        DB::statement("ALTER TABLE privacy_notices ADD CONSTRAINT privacy_notices_status_check CHECK (status IN ('draft', 'published', 'retired'))");
        DB::statement("ALTER TABLE privacy_notices ADD CONSTRAINT privacy_notices_published_legal_check CHECK (status <> 'published' OR (published_at IS NOT NULL AND legal_basis IS NOT NULL AND btrim(legal_basis) <> '' AND retention_days IS NOT NULL AND retention_days > 0 AND btrim(controller_name) <> '' AND btrim(contact_email) <> '' AND btrim(purpose) <> ''))");
        DB::statement("ALTER TABLE privacy_notices ADD CONSTRAINT privacy_notices_current_published_check CHECK (NOT is_current OR status = 'published')");
        DB::statement('CREATE UNIQUE INDEX privacy_notices_one_current_unique ON privacy_notices (is_current) WHERE is_current = true');
        DB::statement("ALTER TABLE pre_registrations ADD CONSTRAINT pre_registrations_status_check CHECK (status IN ('pending_email_confirmation', 'under_review', 'approved', 'rejected', 'archived'))");
        DB::statement('ALTER TABLE email_confirmation_tokens ADD CONSTRAINT email_confirmation_tokens_active_unused_check CHECK (NOT is_active OR used_at IS NULL)');
        DB::statement('CREATE UNIQUE INDEX email_confirmation_tokens_one_active_unique ON email_confirmation_tokens (pre_registration_id) WHERE is_active = true');
        DB::statement("ALTER TABLE email_outbox_messages ADD CONSTRAINT email_outbox_messages_status_check CHECK (status IN ('pending', 'processing', 'sent', 'failed'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE email_outbox_messages DROP CONSTRAINT IF EXISTS email_outbox_messages_status_check');
        Schema::dropIfExists('email_outbox_messages');
        DB::statement('DROP INDEX IF EXISTS email_confirmation_tokens_one_active_unique');
        DB::statement('ALTER TABLE email_confirmation_tokens DROP CONSTRAINT IF EXISTS email_confirmation_tokens_active_unused_check');
        DB::statement('ALTER TABLE pre_registrations DROP CONSTRAINT IF EXISTS pre_registrations_status_check');
        DB::statement('DROP INDEX IF EXISTS privacy_notices_one_current_unique');
        DB::statement('ALTER TABLE privacy_notices DROP CONSTRAINT IF EXISTS privacy_notices_current_published_check');
        DB::statement('ALTER TABLE privacy_notices DROP CONSTRAINT IF EXISTS privacy_notices_published_legal_check');
        DB::statement('ALTER TABLE privacy_notices DROP CONSTRAINT IF EXISTS privacy_notices_status_check');

        Schema::table('email_confirmation_tokens', function (Blueprint $table): void {
            $table->dropColumn('is_active');
        });
        Schema::table('pre_registrations', function (Blueprint $table): void {
            $table->dropColumn('status_version');
        });
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn('can_export');
        });
    }
};
