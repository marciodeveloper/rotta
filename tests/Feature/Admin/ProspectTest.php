<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\Prospect;
use App\Models\ProspectActivity;
use App\Services\AddProspectSource;
use App\Services\CreateProspect;
use App\Services\ProspectAudit;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProspectTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function input(array $extra = []): array
    {
        return [...['business_name' => 'Café sintético QA', 'city' => 'Vassouras', 'state' => 'RJ', 'priority' => 'medium', 'submission_token' => (string) Str::uuid()], ...$extra];
    }

    public function test_manual_creation_normalizes_encrypts_records_origin_and_is_idempotent_without_formal_registration(): void
    {
        Mail::fake();
        $admin = Admin::factory()->create();
        $input = $this->input(['business_name' => '  Café   sintético QA  ', 'state' => 'rj', 'email' => 'CONTATO@example.invalid', 'phone' => '+55 (24) 99999-1234', 'street' => 'Rua sintética', 'confirmed_fields' => ['business_name'], 'origin_reference' => 'Contato comercial fornecido manualmente']);
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.store'), $input)->assertRedirect();
        $prospect = Prospect::sole();
        $this->assertSame('Café sintético QA', $prospect->business_name);
        $this->assertSame('RJ', $prospect->state);
        $this->assertSame('contato@example.invalid', $prospect->email);
        $this->assertSame('5524999991234', $prospect->phone);
        $this->assertSame('identified', $prospect->commercial_status);
        $this->assertSame($admin->id, $prospect->confirmed_fields['business_name']['admin_id']);
        $this->assertNotSame($prospect->email, DB::table('prospects')->value('email'));
        $this->assertNotSame($prospect->street, DB::table('prospects')->value('street'));
        $this->assertArrayNotHasKey('email', $prospect->toArray());
        $this->assertSame('manual', $prospect->sources()->sole()->entry_type);
        $this->assertNull($prospect->sources()->sole()->verified_at);
        $this->assertNull($prospect->sources()->sole()->queried_at);
        $this->post(route('admin.prospects.store'), $input)->assertRedirect();
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('prospect_sources', 1);
        $this->assertDatabaseCount('prospect_activities', 1);
        $this->assertDatabaseHas('admin_audit_events', ['admin_id' => $admin->id, 'action' => 'prospect.created']);
        $this->assertDatabaseCount('pre_registrations', 0);
        $this->assertDatabaseCount('email_outbox_messages', 0);
        Mail::assertNothingSent();
    }

    public function test_creation_token_cannot_be_reused_for_other_data_or_admin(): void
    {
        $admin = Admin::factory()->create();
        $input = $this->input();
        app(CreateProspect::class)->handle($input, $admin);
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.store'), [...$input, 'city' => 'Outra cidade'])->assertSessionHasErrors('submission_token');
        $this->actingAs(Admin::factory()->create(), 'admin')->post(route('admin.prospects.store'), $input)->assertSessionHasErrors('submission_token');
        $this->assertDatabaseCount('prospects', 1);
    }

    public function test_reordered_fields_and_confirmations_keep_creation_idempotent(): void
    {
        $admin = Admin::factory()->create();
        $input = $this->input(['confirmed_fields' => ['business_name', 'city']]);
        $prospect = app(CreateProspect::class)->handle($input, $admin);
        $reordered = array_reverse($input, true);
        $reordered['confirmed_fields'] = ['city', 'business_name'];
        $this->assertSame($prospect->id, app(CreateProspect::class)->handle($reordered, $admin)->id);
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('prospect_activities', 1);
    }

    public function test_invalid_list_context_cannot_write_and_validation_preserves_return_context(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')
            ->post(route('admin.prospects.store', ['sort' => 'password']), $this->input())->assertSessionHasErrors('sort');
        $this->assertDatabaseCount('prospects', 0);
        $this->post(route('admin.prospects.store', ['city' => 'Vassouras', 'page' => 2]), $this->input(['email' => 'invalid']))
            ->assertRedirect(route('admin.prospects.create', ['city' => 'Vassouras', 'page' => 2]))->assertSessionHasErrors('email');
    }

    public static function invalidInputs(): array
    {
        return [
            'name' => ['business_name', ''], 'city' => ['city', ''], 'state' => ['state', 'ZZ'], 'email' => ['email', 'não é e-mail'],
            'phone' => ['phone', '123'], 'url' => ['website', 'javascript:alert(1)'], 'instagram' => ['instagram', 'https://evil.invalid/account'],
            'postal code' => ['postal_code', '123'], 'priority' => ['priority', 'urgent'], 'owner' => ['assigned_to', 'invalid'],
            'future date' => ['collected_at', '2099-01-01'], 'confirmed field' => ['confirmed_fields', ['password']],
            'short reason' => ['change_reason', 'curta'], 'array name' => ['business_name', ['invalid']],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_validation_is_authoritative_and_preserves_input(string $field, mixed $value): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')->from(route('admin.prospects.create'))
            ->post(route('admin.prospects.store'), $this->input([$field => $value]))
            ->assertRedirect(route('admin.prospects.create'))->assertSessionHasErrors($field === 'confirmed_fields' ? 'confirmed_fields.0' : $field)
            ->assertSessionHasInput($field === 'city' ? 'state' : 'city', $field === 'city' ? 'RJ' : 'Vassouras');
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('prospect_activities', 0);
    }

    public function test_inactive_assignee_and_invalid_source_cannot_be_saved(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.store'), $this->input(['assigned_to' => Admin::factory()->create(['is_active' => false])->id]))->assertSessionHasErrors('assigned_to');
        $prospect = app(CreateProspect::class)->handle($this->input(), $admin);
        $this->post(route('admin.prospects.sources.store', $prospect), ['submission_token' => (string) Str::uuid(), 'lock_version' => 1, 'origin_reference' => '', 'verified_at' => '2099-01-01'])->assertSessionHasErrors(['origin_reference', 'verified_at']);
        $this->assertDatabaseCount('prospect_sources', 1);
    }

    public function test_guests_and_inactive_admins_cannot_access_any_module_operation(): void
    {
        $prospect = Prospect::factory()->create();
        $gets = ['index' => [], 'create' => [], 'show' => ['prospect' => $prospect], 'edit' => ['prospect' => $prospect]];
        $posts = ['store' => [], 'contact' => ['prospect' => $prospect], 'sources.store' => ['prospect' => $prospect]];
        foreach ($gets as $route => $params) {
            $this->get(route('admin.prospects.'.$route, $params))->assertRedirect(route('admin.login'));
        }
        foreach ($posts as $route => $params) {
            $this->post(route('admin.prospects.'.$route, $params), [])->assertRedirect(route('admin.login'));
        }
        $this->patch(route('admin.prospects.update', $prospect), [])->assertRedirect(route('admin.login'));
        $inactive = Admin::factory()->create(['is_active' => false]);
        foreach ($gets as $route => $params) {
            $this->actingAs($inactive, 'admin')->get(route('admin.prospects.'.$route, $params))->assertRedirect(route('admin.login'));
        }
        foreach ($posts as $route => $params) {
            $this->actingAs($inactive, 'admin')->post(route('admin.prospects.'.$route, $params), [])->assertRedirect(route('admin.login'));
        }
        $this->actingAs($inactive, 'admin')->patch(route('admin.prospects.update', $prospect), [])->assertRedirect(route('admin.login'));
    }

    public function test_services_enforce_policy_without_http_controller(): void
    {
        $this->expectException(AuthorizationException::class);
        app(CreateProspect::class)->handle($this->input(), Admin::factory()->create(['is_active' => false]));
    }

    public function test_services_enforce_validation_without_http_controller(): void
    {
        $this->expectException(ValidationException::class);
        app(CreateProspect::class)->handle($this->input(['email' => 'invalid']), Admin::factory()->create());
    }

    public function test_direct_services_normalize_empty_source_dates_without_http_middleware(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(['collected_at' => '', 'verified_at' => ' ']), $admin);
        $this->assertNull($prospect->sources()->sole()->collected_at);
        $this->assertNull($prospect->sources()->sole()->verified_at);
        app(AddProspectSource::class)->handle($prospect, $admin, ['submission_token' => (string) Str::uuid(), 'lock_version' => 1, 'origin_reference' => '  Outra origem manual  ', 'collected_at' => '', 'verified_at' => '']);
        $this->assertSame(2, $prospect->sources()->count());
        $source = $prospect->sources()->where('origin_reference', '!=', null)->sole();
        $this->assertSame('Outra origem manual', $source->origin_reference);
        $this->assertNull($source->verified_at);
    }

    public function test_duplicate_review_is_explicit_audited_and_never_merges(): void
    {
        $admin = Admin::factory()->create();
        $first = app(CreateProspect::class)->handle($this->input(['phone' => '24999991234']), $admin);
        $input = $this->input(['business_name' => 'Outro nome sintético', 'city' => 'Outra cidade', 'phone' => '+55 (24) 99999-1234']);
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.store'), $input)->assertSessionHasErrors('duplicate_acknowledged');
        $this->get(route('admin.prospects.create'))->assertOk()->assertSee($first->business_name)->assertSee('possíveis duplicidades');
        $this->post(route('admin.prospects.store'), [...$input, 'duplicate_acknowledged' => 1, 'duplicate_reason' => 'Unidade distinta revisada manualmente.'])->assertRedirect();
        $this->assertDatabaseCount('prospects', 2);
        $activity = ProspectActivity::latest()->where('event', 'created')->where('prospect_id', '!=', $first->id)->sole();
        $this->assertTrue($activity->metadata['duplicate_reviewed']);
        $this->assertSame('Unidade distinta revisada manualmente.', $activity->reason);
    }

    public function test_confirmed_fields_require_explicit_decision_and_audit_and_stale_edits_do_not_write(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(['email' => 'first@example.invalid', 'confirmed_fields' => ['email']]), $admin);
        $data = $this->input(['email' => 'changed@example.invalid', 'lock_version' => 1, 'confirmed_fields' => ['email']]);
        $this->actingAs($admin, 'admin')->patch(route('admin.prospects.update', $prospect), $data)->assertSessionHasErrors('override_confirmed');
        $this->assertSame('first@example.invalid', $prospect->fresh()->email);
        $this->patch(route('admin.prospects.update', $prospect), [...$data, 'override_confirmed' => 1, 'change_reason' => 'Informação conferida diretamente no local.'])->assertRedirect();
        $this->assertSame('changed@example.invalid', $prospect->fresh()->email);
        $this->assertSame(2, $prospect->fresh()->lock_version);
        $this->patch(route('admin.prospects.update', $prospect), [...$data, 'business_name' => 'Não deve salvar'])->assertSessionHasErrors('lock_version');
        $this->assertSame('Café sintético QA', $prospect->fresh()->business_name);
        $this->assertDatabaseCount('prospect_activities', 2);
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'prospect.updated', 'target_id' => $prospect->id]);
    }

    public function test_removing_confirmation_requires_explicit_decision(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(['confirmed_fields' => ['business_name']]), $admin);
        $this->actingAs($admin, 'admin')->patch(route('admin.prospects.update', $prospect), $this->input(['lock_version' => 1]))->assertSessionHasErrors('override_confirmed');
        $this->patch(route('admin.prospects.update', $prospect), $this->input(['lock_version' => 1, 'override_confirmed' => 1, 'change_reason' => 'Confirmação anterior necessita nova verificação.']))->assertRedirect();
        $this->assertSame([], $prospect->fresh()->confirmed_fields);
    }

    public function test_contact_block_survives_edit_new_origin_and_duplicate_attempt_and_can_be_revoked_explicitly(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(), $admin);
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.contact', $prospect), ['blocked' => 1, 'lock_version' => 1, 'reason' => 'Não realizar contato conforme solicitação.'])->assertRedirect();
        $this->assertNotNull($prospect->fresh()->contact_blocked_at);
        $this->patch(route('admin.prospects.update', $prospect), $this->input(['lock_version' => 2, 'notes' => 'Observação sintética']))->assertRedirect();
        $sourceInput = ['submission_token' => (string) Str::uuid(), 'lock_version' => 3, 'origin_reference' => 'Segunda referência comercial manual'];
        $this->post(route('admin.prospects.sources.store', $prospect), $sourceInput)->assertRedirect();
        $this->post(route('admin.prospects.sources.store', $prospect), $sourceInput)->assertRedirect();
        $this->assertSame(2, $prospect->sources()->count());
        $this->assertNotNull($prospect->fresh()->contact_blocked_at);
        $this->post(route('admin.prospects.store'), $this->input(['duplicate_acknowledged' => 1, 'duplicate_reason' => 'Não deve contornar um bloqueio.']))->assertSessionHasErrors('blocked_match_acknowledged');
        $this->assertDatabaseCount('prospects', 1);
        $this->post(route('admin.prospects.contact', $prospect), ['blocked' => 0, 'lock_version' => 4, 'reason' => 'Revogação explicitamente revisada e autorizada.'])->assertRedirect();
        $this->assertNull($prospect->fresh()->contact_blocked_at);
        $this->assertDatabaseHas('prospect_activities', ['event' => 'contact_unblocked']);
        $this->assertDatabaseCount('prospect_activities', 5);
    }

    public function test_block_and_source_require_current_version_and_reason(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(), $admin);
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.contact', $prospect), ['blocked' => 1, 'lock_version' => 1, 'reason' => 'curta'])->assertSessionHasErrors('reason');
        $this->post(route('admin.prospects.contact', $prospect), ['blocked' => 1, 'lock_version' => 2, 'reason' => 'Justificativa válida sintética.'])->assertSessionHasErrors('lock_version');
        $this->post(route('admin.prospects.sources.store', $prospect), ['submission_token' => (string) Str::uuid(), 'lock_version' => 2, 'origin_reference' => 'Origem sintética'])->assertSessionHasErrors('lock_version');
        $this->assertDatabaseCount('prospect_activities', 1);
    }

    public function test_list_filters_sorting_details_and_safe_output_preserve_context(): void
    {
        $admin = Admin::factory()->create();
        $target = app(CreateProspect::class)->handle($this->input(['business_name' => '<script>alert(1)</script>', 'category' => 'Gastronomia', 'priority' => 'high', 'assigned_to' => $admin->id]), $admin);
        Prospect::factory()->count(12)->create(['city' => 'Outra cidade']);
        $params = ['city' => 'Vassouras', 'category' => 'Gastronomia', 'assigned_to' => $admin->id, 'priority' => 'high', 'commercial_status' => 'identified', 'state' => 'RJ', 'sort' => 'business_name', 'direction' => 'asc', 'page' => 1, 'per_page' => 10];
        $this->actingAs($admin, 'admin')->get(route('admin.prospects.index', $params))->assertOk()->assertSee('1 resultado(s)')->assertSee($target->business_name)->assertDontSee($target->business_name, false);
        $this->get(route('admin.prospects.show', ['prospect' => $target, ...$params]))->assertOk()->assertSee('per_page=10')->assertSee('Origens e referências')->assertSee('Histórico de relacionamento')->assertDontSee($target->creation_token);
        $this->get(route('admin.prospects.edit', ['prospect' => $target, ...$params]))->assertOk()->assertSee('Editar estabelecimento');
        $this->get(route('admin.prospects.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
        $this->get(route('admin.prospects.index', ['blocked' => 'yes']))->assertOk()->assertSee('0 resultado(s)');
        $this->get(route('admin.prospects.index', ['per_page' => 10, 'page' => 2]))->assertOk()->assertSee('Página 2');
    }

    public function test_creation_is_atomic_when_audit_fails(): void
    {
        $admin = Admin::factory()->create();
        $audit = $this->mock(ProspectAudit::class);
        $audit->shouldReceive('record')->once()->andThrow(new \RuntimeException('Synthetic audit failure'));
        try {
            app(CreateProspect::class)->handle($this->input(), $admin);
            $this->fail('Expected audit failure');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Synthetic audit failure', $exception->getMessage());
        }
        $this->assertDatabaseCount('prospects', 0);
        $this->assertDatabaseCount('prospect_sources', 0);
    }

    public function test_database_rejects_invalid_status(): void
    {
        $prospect = Prospect::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('prospects')->where('id', $prospect->id)->update(['commercial_status' => 'converted']);
    }

    public function test_database_rejects_partial_contact_block(): void
    {
        $prospect = Prospect::factory()->create();
        $this->expectException(QueryException::class);
        DB::table('prospects')->where('id', $prospect->id)->update(['contact_blocked_at' => now()]);
    }

    public function test_write_rate_limit_is_per_admin_and_does_not_create_records(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        for ($i = 0; $i < 30; $i++) {
            $this->post(route('admin.prospects.store'), [])->assertSessionHasErrors('business_name');
        }
        $this->post(route('admin.prospects.store'), $this->input())->assertTooManyRequests();
        $this->assertDatabaseCount('prospects', 0);
    }
}
