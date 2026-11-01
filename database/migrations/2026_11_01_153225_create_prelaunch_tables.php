<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('privacy_notices', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('version')->unique();
            $table->string('title');
            $table->text('content');
            $table->string('controller_name');
            $table->string('contact_email');
            $table->text('purpose');
            $table->string('legal_basis')->nullable();
            $table->unsignedInteger('retention_days')->nullable();
            $table->boolean('is_current')->default(false);
            $table->string('status')->default('draft');
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();
        });

        Schema::create('admins', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->text('totp_secret');
            $table->timestampTz('totp_confirmed_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('pre_registrations', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('applicant_type');
            $table->text('document');
            $table->char('document_hash', 64)->unique();
            $table->char('document_last_four', 4);
            $table->string('business_name');
            $table->string('category');
            $table->text('short_description');
            $table->text('responsible_name');
            $table->text('email');
            $table->char('email_hash', 64)->unique();
            $table->text('phone');
            $table->char('phone_hash', 64);
            $table->text('website')->nullable();
            $table->text('instagram')->nullable();
            $table->string('address_mode');
            $table->text('postal_code')->nullable();
            $table->text('street')->nullable();
            $table->text('street_number')->nullable();
            $table->text('complement')->nullable();
            $table->text('neighborhood')->nullable();
            $table->string('city');
            $table->char('state', 2);
            $table->text('service_area')->nullable();
            $table->text('google_place_id')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->boolean('marketing_opt_in')->default(false);
            $table->foreignUuid('privacy_notice_id')->constrained()->restrictOnDelete();
            $table->string('privacy_notice_version');
            $table->string('status')->default('pending_email_confirmation');
            $table->string('source')->default('prelaunch_web');
            $table->timestampTz('email_confirmed_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['state', 'city']);
            $table->index(['category', 'created_at']);
        });

        Schema::create('email_confirmation_tokens', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pre_registration_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestampTz('expires_at');
            $table->timestampTz('used_at')->nullable();
            $table->timestamps();
        });

        Schema::create('pre_registration_status_histories', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('pre_registration_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignUuid('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['pre_registration_id', 'created_at']);
        });

        Schema::create('admin_audit_events', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('admin_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->string('target_type')->nullable();
            $table->uuid('target_id')->nullable();
            $table->jsonb('metadata')->nullable();
            $table->char('ip_hash', 64)->nullable();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['action', 'created_at']);
            $table->index(['target_type', 'target_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_events');
        Schema::dropIfExists('pre_registration_status_histories');
        Schema::dropIfExists('email_confirmation_tokens');
        Schema::dropIfExists('pre_registrations');
        Schema::dropIfExists('admins');
        Schema::dropIfExists('privacy_notices');
    }
};
