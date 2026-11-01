<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_storage_usages', function (Blueprint $table): void {
            $table->unsignedSmallInteger('id')->primary();
            $table->unsignedBigInteger('used_bytes')->default(0);
            $table->unsignedBigInteger('free_bytes')->nullable();
            $table->string('level', 16)->default('normal');
            $table->timestampTz('checked_at')->nullable();
            $table->timestamps();
        });

        DB::table('media_storage_usages')->insert([
            'id' => 1,
            'used_bytes' => 0,
            'level' => 'normal',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::statement("ALTER TABLE media_storage_usages ADD CONSTRAINT media_storage_usages_level_check CHECK (level IN ('normal', 'alert', 'blocked'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('media_storage_usages');
    }
};
