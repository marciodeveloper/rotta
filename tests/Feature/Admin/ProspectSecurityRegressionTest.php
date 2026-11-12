<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use App\Models\Prospect;
use App\Policies\ProspectPolicy;
use App\Services\CreateProspect;
use App\Services\ManageProspectContact;
use App\Services\UpdateProspect;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ProspectSecurityRegressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private function input(array $extra = []): array
    {
        return [...['business_name' => 'QA histórico sintético', 'city' => 'Vassouras', 'state' => 'RJ', 'email' => 'old@example.invalid', 'phone' => '24999991234', 'priority' => 'medium', 'submission_token' => (string) Str::uuid()], ...$extra];
    }

    public static function identifierChanges(): array
    {
        return [
            'name' => [['business_name' => 'QA nome corrigido']],
            'email' => [['email' => 'new@example.invalid']],
            'phone' => [['phone' => '24999995678']],
            'location' => [['city' => 'Miguel Pereira', 'state' => 'SP']],
            'all' => [['business_name' => 'QA nome corrigido', 'city' => 'Outra cidade', 'email' => 'new@example.invalid', 'phone' => '24999995678']],
        ];
    }

    #[DataProvider('identifierChanges')]
    public function test_original_block_bypass_is_prevented_for_current_and_historical_identifiers(array $changes): void
    {
        $admin = Admin::factory()->create();
        $original = $this->input();
        $prospect = app(CreateProspect::class)->handle($original, $admin);
        app(ManageProspectContact::class)->handle($prospect, $admin, ['blocked' => 1, 'lock_version' => 1, 'reason' => 'Não realizar contato com este estabelecimento.']);
        app(UpdateProspect::class)->handle($prospect, [...$original, ...$changes, 'lock_version' => 2], $admin);
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.store'), [...$original, 'submission_token' => (string) Str::uuid(), 'duplicate_acknowledged' => 1, 'duplicate_reason' => 'Revisão genérica não pode remover o bloqueio.'])
            ->assertSessionHasErrors('blocked_match_acknowledged');
        $this->get(route('admin.prospects.create'))->assertOk()->assertSee('Correspondência histórica')->assertSee('Revisão de correspondências');
        $this->assertDatabaseCount('prospects', 1);
        $this->assertNotNull($prospect->fresh()->contact_blocked_at);
        $this->assertGreaterThan(3, $prospect->identifiers()->count());
        $this->assertDatabaseCount('prospect_activities', 3);
        foreach ($prospect->identifiers()->get() as $identifier) {
            $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $identifier->getRawOriginal('fingerprint'));
            $this->assertArrayNotHasKey('fingerprint', $identifier->toArray());
        }
    }

    public function test_distinct_establishment_with_shared_contact_can_be_reviewed_without_unblocking_original(): void
    {
        $admin = Admin::factory()->create();
        $original = app(CreateProspect::class)->handle($this->input(), $admin);
        app(ManageProspectContact::class)->handle($original, $admin, ['blocked' => 1, 'lock_version' => 1, 'reason' => 'Bloqueio da primeira unidade comercial.']);
        $input = $this->input(['business_name' => 'QA segunda unidade distinta', 'city' => 'Outra cidade', 'duplicate_acknowledged' => 1, 'duplicate_reason' => 'Outra unidade utiliza a mesma central comercial.', 'blocked_match_acknowledged' => 1, 'blocked_match_reason' => 'Verificadas as fichas; são estabelecimentos distintos.', 'blocked_match_versions' => [$original->id => 2]]);
        $created = app(CreateProspect::class)->handle($input, $admin);
        $this->assertNull($created->contact_blocked_at);
        $this->assertNotNull($original->fresh()->contact_blocked_at);
        $this->assertNotSame($created->id, $original->id);
        $activity = $created->activities()->sole();
        $this->assertSame($input['blocked_match_reason'], $activity->decision_reasons['blocked_match']);
        $this->assertSame([$original->id => 2], $activity->metadata['duplicate_review']['blocked_versions']);
        $this->assertDatabaseCount('prospects', 2);
    }

    public function test_blocked_review_requires_permission_and_current_candidate_versions(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(), $admin);
        app(ManageProspectContact::class)->handle($prospect, $admin, ['blocked' => 1, 'lock_version' => 1, 'reason' => 'Bloqueio solicitado para a primeira unidade.']);
        $input = $this->input(['business_name' => 'QA unidade diferente', 'duplicate_acknowledged' => 1, 'duplicate_reason' => 'Unidade diferente com contato compartilhado.', 'blocked_match_acknowledged' => 1, 'blocked_match_reason' => 'Revisão explícita com distinção de estabelecimentos.', 'blocked_match_versions' => [$prospect->id => 1]]);
        $this->actingAs($admin, 'admin')->post(route('admin.prospects.store'), $input)->assertSessionHasErrors('blocked_match_acknowledged');
        $input['blocked_match_versions'] = [$prospect->id => 2];
        $this->partialMock(ProspectPolicy::class)->shouldReceive('reviewBlockedMatch')->andReturn(false);
        $this->post(route('admin.prospects.store'), $input)->assertForbidden();
        $this->assertDatabaseCount('prospects', 1);
    }

    public function test_authorized_unblock_preserves_history_and_allows_normal_duplicate_review(): void
    {
        $admin = Admin::factory()->create();
        $input = $this->input();
        $prospect = app(CreateProspect::class)->handle($input, $admin);
        app(ManageProspectContact::class)->handle($prospect, $admin, ['blocked' => 1, 'lock_version' => 1, 'reason' => 'Bloqueio sintético para acompanhar o histórico.']);
        app(UpdateProspect::class)->handle($prospect, [...$input, 'business_name' => 'QA nome novo', 'email' => 'new@example.invalid', 'phone' => '24999995678', 'lock_version' => 2], $admin);
        $before = $prospect->identifiers()->count();
        app(ManageProspectContact::class)->handle($prospect, $admin, ['blocked' => 0, 'lock_version' => 3, 'reason' => 'Desbloqueio explicitamente autorizado e revisado.']);
        $created = app(CreateProspect::class)->handle([...$input, 'submission_token' => (string) Str::uuid(), 'duplicate_acknowledged' => 1, 'duplicate_reason' => 'Dados antigos revisados após desbloqueio autorizado.'], $admin);
        $this->assertNull($prospect->fresh()->contact_blocked_at);
        $this->assertNull($created->contact_blocked_at);
        $this->assertSame($before, $prospect->identifiers()->count());
        $this->assertDatabaseHas('admin_audit_events', ['action' => 'prospect.contact_unblocked', 'target_id' => $prospect->id]);
    }

    public function test_both_reasons_are_preserved_encrypted_and_referenced_by_audit(): void
    {
        $this->freezeTime();
        $admin = Admin::factory()->create();
        $first = app(CreateProspect::class)->handle($this->input(['phone' => null, 'confirmed_fields' => ['email']]), $admin);
        $second = app(CreateProspect::class)->handle($this->input(['business_name' => 'QA outro', 'email' => 'other@example.invalid', 'phone' => null]), $admin);
        $data = $this->input(['phone' => null, 'email' => $second->email, 'lock_version' => 1, 'confirmed_fields' => ['email'], 'override_confirmed' => 1, 'change_reason' => 'Motivo da alteração do campo confirmado.', 'duplicate_acknowledged' => 1, 'duplicate_reason' => 'Motivo da aceitação da possível duplicidade.']);
        app(UpdateProspect::class)->handle($first, $data, $admin);
        $activity = $first->activities()->where('event', 'updated')->sole();
        $this->assertSame(['change' => $data['change_reason'], 'duplicate' => $data['duplicate_reason']], $activity->decision_reasons);
        $this->assertSame($admin->id, $activity->admin_id);
        $this->assertSame(now()->timestamp, $activity->created_at->timestamp);
        $raw = DB::table('prospect_activities')->where('id', $activity->id)->value('decision_reasons');
        $this->assertStringNotContainsString($data['change_reason'], $raw);
        $this->assertStringNotContainsString($data['duplicate_reason'], $raw);
        $audit = AdminAuditEvent::where('action', 'prospect.updated')->where('target_id', $first->id)->sole();
        $this->assertSame($activity->id, $audit->metadata['activity_id']);
        $this->assertSame(['change', 'duplicate'], $audit->metadata['decision_types']);
        $this->assertStringNotContainsString($data['change_reason'], json_encode($audit->metadata));
        $this->actingAs($admin, 'admin')->get(route('admin.prospects.show', $first))->assertOk()->assertSee($data['change_reason'])->assertSee($data['duplicate_reason']);
    }

    public static function malformedInputs(): array
    {
        return [
            'input' => ['business_name', ['invalid']], 'textarea' => ['notes', ['invalid']], 'select' => ['priority', ['high']],
            'assignee' => ['assigned_to', ['invalid']], 'confirmations' => ['confirmed_fields', 'invalid'],
            'nested confirmations' => ['confirmed_fields', [['business_name']]], 'date' => ['collected_at', ['invalid']],
            'checkbox' => ['override_confirmed', ['invalid']], 'token' => ['submission_token', ['invalid']],
        ];
    }

    #[DataProvider('malformedInputs')]
    public function test_invalid_post_redirect_get_is_renderable_and_preserves_valid_fields(string $field, mixed $value): void
    {
        $admin = Admin::factory()->create();
        $post = $this->actingAs($admin, 'admin')->post(route('admin.prospects.store'), $this->input([$field => $value]));
        $post->assertRedirect(route('admin.prospects.create'))->assertSessionHasErrors();
        $this->withCookie(config('session.cookie'), session()->getId())->get($post->headers->get('Location'))->assertOk()->assertViewHas('errors', fn ($errors) => $errors->any())->assertSee('Revise os dados informados')->assertSee('Vassouras')->assertSee('old@example.invalid');
        $this->assertDatabaseCount('prospects', 0);
    }

    public function test_malformed_edit_and_source_return_to_renderable_forms(): void
    {
        $admin = Admin::factory()->create();
        $prospect = app(CreateProspect::class)->handle($this->input(), $admin);
        $response = $this->actingAs($admin, 'admin')->patch(route('admin.prospects.update', $prospect), $this->input(['lock_version' => ['invalid'], 'confirmed_fields' => 'invalid']));
        $response->assertSessionHasErrors(['lock_version', 'confirmed_fields']);
        $this->withCookie(config('session.cookie'), session()->getId())->get($response->headers->get('Location'))->assertOk()->assertSee('Revise os dados informados');
        $response = $this->post(route('admin.prospects.sources.store', $prospect), ['origin_reference' => ['invalid'], 'submission_token' => ['invalid'], 'lock_version' => ['invalid']]);
        $response->assertSessionHasErrors();
        $this->withCookie(config('session.cookie'), session()->getId())->get($response->headers->get('Location'))->assertOk()->assertSee('Revise os dados informados');
    }

    public static function blankReasons(): array
    {
        return ['empty' => [''], 'spaces' => ['                  '], 'controls' => ["\t\r\n\0\x1b"], 'unicode' => ["\u{200B}\u{00A0}\u{200D}\u{2028}"]];
    }

    #[DataProvider('blankReasons')]
    public function test_direct_services_reject_blank_normalized_reasons(string $reason): void
    {
        $admin = Admin::factory()->create();
        $original = $this->input(['confirmed_fields' => ['business_name']]);
        $prospect = app(CreateProspect::class)->handle($original, $admin);
        $operations = [
            fn () => app(UpdateProspect::class)->handle($prospect, [...$original, 'business_name' => 'QA nome revisado', 'lock_version' => 1, 'override_confirmed' => 1, 'change_reason' => $reason], $admin),
            fn () => app(CreateProspect::class)->handle($this->input(['duplicate_acknowledged' => 1, 'duplicate_reason' => $reason]), $admin),
            fn () => app(ManageProspectContact::class)->handle($prospect, $admin, ['blocked' => 1, 'lock_version' => 1, 'reason' => $reason]),
        ];
        foreach ($operations as $operation) {
            try {
                $operation();
                $this->fail('Blank reason accepted');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(1, $prospect->fresh()->lock_version);
        $this->assertDatabaseCount('prospects', 1);
        $this->assertDatabaseCount('prospect_activities', 1);
    }

    public function test_assignee_sorting_uses_names_nulls_last_ties_and_stable_pagination(): void
    {
        $admin = Admin::factory()->create();
        $zulu = Admin::factory()->create(['id' => '00000000-0000-4000-8000-000000000001', 'name' => 'Zulu']);
        $alpha = Admin::factory()->create(['id' => 'ffffffff-ffff-4fff-8fff-ffffffffffff', 'name' => 'Alfa']);
        $alpha2 = Admin::factory()->create(['name' => 'Alfa']);
        $items = collect([
            Prospect::factory()->create(['assigned_to' => $zulu->id]),
            Prospect::factory()->create(['assigned_to' => $alpha->id]),
            Prospect::factory()->create(['assigned_to' => $alpha2->id]),
            Prospect::factory()->create(['assigned_to' => null]),
        ]);
        foreach (['asc', 'desc'] as $direction) {
            $response = $this->actingAs($admin, 'admin')->get(route('admin.prospects.index', ['sort' => 'assigned_to', 'direction' => $direction]));
            $response->assertOk();
            $rows = $response->viewData('prospects')->getCollection();
            $names = $rows->map(fn ($p) => $p->assignee?->name)->all();
            $this->assertSame($direction === 'asc' ? ['Alfa', 'Alfa', 'Zulu', null] : ['Zulu', 'Alfa', 'Alfa', null], $names);
            $ties = $rows->filter(fn ($p) => $p->assignee?->name === 'Alfa')->pluck('id')->all();
            $expected = $items->whereIn('assigned_to', [$alpha->id, $alpha2->id])->sortByDesc('id')->pluck('id')->all();
            $this->assertSame($expected, $ties);
        }
        Prospect::factory()->count(12)->create(['assigned_to' => null]);
        $page1 = $this->get(route('admin.prospects.index', ['sort' => 'assigned_to', 'direction' => 'asc', 'per_page' => 10]))->assertOk()->viewData('prospects')->pluck('id')->all();
        $page2 = $this->get(route('admin.prospects.index', ['sort' => 'assigned_to', 'direction' => 'asc', 'per_page' => 10, 'page' => 2]))->assertOk()->viewData('prospects')->pluck('id')->all();
        $this->assertCount(10, $page1);
        $this->assertCount(6, $page2);
        $this->assertSame([], array_values(array_intersect($page1, $page2)));
        $this->get(route('admin.prospects.index', ['sort' => 'assigned_admin.password']))->assertSessionHasErrors('sort');
    }
}
