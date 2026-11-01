<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admins', function (Blueprint $table): void {
            $table->unsignedBigInteger('last_totp_time_step')->nullable();
        });
        Schema::table('pre_registrations', function (Blueprint $table): void {
            $table->char('document_hash_v2', 64)->nullable()->unique();
            $table->char('email_hash_v2', 64)->nullable()->unique();
            $table->char('phone_hash_v2', 64)->nullable();
        });

        Schema::create('admin_recovery_codes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->constrained()->cascadeOnDelete();
            $table->char('code_hash', 64)->unique();
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();
            $table->index(['admin_id', 'used_at']);
        });

        Schema::create('pre_registration_media', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pre_registration_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->unsignedSmallInteger('position')->default(0);
            $table->string('alt_text', 240);
            $table->char('checksum', 64);
            $table->string('mime_type', 32);
            $table->string('extension', 5);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->string('status', 24)->default('pending_review');
            $table->string('storage_disk', 64);
            $table->string('storage_key', 500)->unique();
            $table->string('thumbnail_storage_key', 500)->unique();
            $table->timestampTz('rights_confirmed_at');
            $table->timestamps();
            $table->unique(['pre_registration_id', 'type', 'position']);
            $table->index(['pre_registration_id', 'status']);
        });

        DB::statement('ALTER TABLE admins ADD CONSTRAINT admins_last_totp_time_step_check CHECK (last_totp_time_step IS NULL OR last_totp_time_step >= 0)');
        DB::statement("ALTER TABLE pre_registrations ADD CONSTRAINT pre_registrations_applicant_type_check CHECK (applicant_type IN ('company', 'professional'))");
        DB::statement("ALTER TABLE pre_registrations ADD CONSTRAINT pre_registrations_address_mode_check CHECK (address_mode IN ('public_address', 'service_area'))");
        DB::statement("ALTER TABLE pre_registrations ADD CONSTRAINT pre_registrations_state_check CHECK (state ~ '^[A-Z]{2}$')");
        DB::statement('ALTER TABLE pre_registrations ADD CONSTRAINT pre_registrations_coordinates_check CHECK ((latitude IS NULL OR latitude BETWEEN -90 AND 90) AND (longitude IS NULL OR longitude BETWEEN -180 AND 180))');
        DB::statement("ALTER TABLE pre_registration_media ADD CONSTRAINT pre_registration_media_type_check CHECK (type IN ('logo', 'cover', 'gallery'))");
        DB::statement("ALTER TABLE pre_registration_media ADD CONSTRAINT pre_registration_media_status_check CHECK (status IN ('pending_review', 'approved', 'rejected', 'quarantined'))");
        DB::statement("ALTER TABLE pre_registration_media ADD CONSTRAINT pre_registration_media_mime_check CHECK (mime_type IN ('image/jpeg', 'image/png', 'image/webp'))");
        DB::statement('ALTER TABLE pre_registration_media ADD CONSTRAINT pre_registration_media_dimensions_check CHECK (width > 0 AND height > 0 AND size_bytes > 0)');
        DB::statement("ALTER TABLE pre_registration_media ADD CONSTRAINT pre_registration_media_position_check CHECK ((type IN ('logo', 'cover') AND position = 0) OR (type = 'gallery' AND position BETWEEN 0 AND 5))");
    }

    public function down(): void
    {
        Schema::dropIfExists('pre_registration_media');
        Schema::dropIfExists('admin_recovery_codes');
        DB::statement('ALTER TABLE pre_registrations DROP CONSTRAINT IF EXISTS pre_registrations_coordinates_check');
        DB::statement('ALTER TABLE pre_registrations DROP CONSTRAINT IF EXISTS pre_registrations_state_check');
        DB::statement('ALTER TABLE pre_registrations DROP CONSTRAINT IF EXISTS pre_registrations_address_mode_check');
        DB::statement('ALTER TABLE pre_registrations DROP CONSTRAINT IF EXISTS pre_registrations_applicant_type_check');
        DB::statement('ALTER TABLE admins DROP CONSTRAINT IF EXISTS admins_last_totp_time_step_check');
        Schema::table('pre_registrations', function (Blueprint $table): void {
            $table->dropColumn(['document_hash_v2', 'email_hash_v2', 'phone_hash_v2']);
        });
        Schema::table('admins', function (Blueprint $table): void {
            $table->dropColumn('last_totp_time_step');
        });
    }
};
