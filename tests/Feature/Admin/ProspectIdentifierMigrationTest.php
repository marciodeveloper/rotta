<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Prospect;
use App\Services\CreateProspect;
use App\Services\DetectProspectDuplicates;
use App\Services\SensitiveHash;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProspectIdentifierMigrationTest extends TestCase
{
    use DatabaseMigrations;

    public function test_additive_migration_backfills_current_identifiers_without_changing_existing_data(): void
    {
        $admin = Admin::factory()->create();
        $prospect = Prospect::factory()->create([
            'business_name' => 'QA legado', 'normalized_name' => 'qa legado', 'email' => 'legacy@example.invalid', 'phone' => '24999991234',
            'email_hash' => app(SensitiveHash::class)->makeFor('prospect-email', 'legacy@example.invalid'),
            'phone_hash' => app(SensitiveHash::class)->makeFor('prospect-phone', '24999991234'),
            'contact_blocked_at' => now(), 'contact_blocked_by' => $admin->id, 'contact_block_reason' => 'Bloqueio existente antes da migration.',
        ]);
        $activity = $prospect->activities()->create(['admin_id' => $admin->id, 'event' => 'created', 'reason' => 'Justificativa legada preservada.', 'metadata' => []]);
        $rawEmail = DB::table('prospects')->where('id', $prospect->id)->value('email');
        $this->artisan('migrate:rollback', ['--step' => 1, '--force' => true])->assertSuccessful();
        $this->assertFalse(Schema::hasTable('prospect_identifiers'));
        $this->assertDatabaseHas('prospects', ['id' => $prospect->id]);
        $this->artisan('migrate', ['--force' => true])->assertSuccessful();
        $this->assertSame($rawEmail, DB::table('prospects')->where('id', $prospect->id)->value('email'));
        $this->assertSame(3, $prospect->identifiers()->count());
        $this->assertSame('Justificativa legada preservada.', $activity->fresh()->reason);
        $this->assertNull($activity->fresh()->decision_reasons);
        $this->assertNotNull($prospect->fresh()->contact_blocked_at);
        $data = ['business_name' => 'QA legado', 'city' => 'Vassouras', 'state' => 'RJ', 'email' => 'legacy@example.invalid', 'phone' => '24999991234', 'priority' => 'medium'];
        $match = app(DetectProspectDuplicates::class)->find($data)->sole();
        $this->assertSame($prospect->id, $match->id);
        try {
            app(CreateProspect::class)->handle([...$data, 'submission_token' => (string) Str::uuid()], $admin);
            $this->fail('Backfilled blocked identifiers were bypassed');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('blocked_match_acknowledged', $exception->errors());
        }
        $this->assertDatabaseCount('prospects', 1);
    }
}
