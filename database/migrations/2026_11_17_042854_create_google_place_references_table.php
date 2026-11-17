<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('google_place_references', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('place_id', 2048)->unique();
            $table->foreignUuid('saved_by')->constrained('admins')->restrictOnDelete();
            $table->timestampTz('queried_at');
            $table->foreignUuid('prospect_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignUuid('linked_by')->nullable()->constrained('admins')->restrictOnDelete();
            $table->timestampTz('linked_at')->nullable();
            $table->timestampsTz();
            $table->index(['created_at', 'id']);
            $table->index('prospect_id');
        });
        DB::statement("ALTER TABLE google_place_references ADD CONSTRAINT google_place_references_id_check CHECK (place_id ~ '^[A-Za-z0-9_-]+$' AND length(place_id) <= 2048)");
        DB::statement('ALTER TABLE google_place_references ADD CONSTRAINT google_place_references_link_check CHECK ((prospect_id IS NULL AND linked_by IS NULL AND linked_at IS NULL) OR (prospect_id IS NOT NULL AND linked_by IS NOT NULL AND linked_at IS NOT NULL))');
    }

    public function down(): void
    {
        // Destructive: application rollback in production must retain this table.
        Schema::dropIfExists('google_place_references');
    }
};
