<?php

namespace Tests\Feature;

use App\Models\PreRegistration;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DatabaseConstraintsP1Test extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_database_rejects_invalid_applicant_type(): void
    {
        $registration = PreRegistration::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('pre_registrations')->where('id', $registration->id)->update(['applicant_type' => 'invalid']);
    }

    public function test_database_rejects_out_of_range_coordinates(): void
    {
        $registration = PreRegistration::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('pre_registrations')->where('id', $registration->id)->update(['latitude' => 91]);
    }
}
