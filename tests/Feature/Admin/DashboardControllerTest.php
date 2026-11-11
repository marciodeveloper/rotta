<?php

namespace Tests\Feature\Admin;

use App\Models\Admin;
use App\Models\PreRegistration;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

class DashboardControllerTest extends TestCase
{
    use LazilyRefreshDatabase;

    public function test_unauthenticated_request_redirects_to_admin_login(): void
    {
        $response = $this->get(route('admin.dashboard'));

        $response->assertRedirect(route('admin.login'));
    }

    public function test_authenticated_active_admin_can_filter_registrations(): void
    {
        $admin = Admin::factory()->create();
        PreRegistration::factory()->create(['business_name' => 'Negócio Visível', 'state' => 'RJ']);
        PreRegistration::factory()->create(['business_name' => 'Negócio Oculto', 'state' => 'SP']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard', ['state' => 'RJ']));

        $response->assertOk()->assertSee('Negócio Visível')->assertDontSee('Negócio Oculto');
    }

    public function test_sorting_and_pagination_apply_to_all_results_and_hide_private_fields(): void
    {
        $admin = Admin::factory()->create();
        PreRegistration::factory()->count(11)->create(['business_name' => 'AAA Negócio']);
        $last = PreRegistration::factory()->create(['business_name' => 'ZZZ Primeiro', 'email' => 'private@example.invalid']);

        $response = $this->actingAs($admin, 'admin')->get(route('admin.dashboard', ['sort' => 'business_name', 'direction' => 'desc', 'per_page' => 10]));

        $response->assertOk()->assertSee('ZZZ Primeiro')->assertDontSee('private@example.invalid');
        $response->assertViewHas('registrations', fn ($page): bool => $page->total() === 12 && $page->count() === 10 && $page->first()->id === $last->id);
        $this->get(route('admin.dashboard', ['per_page' => 10, 'page' => 2]))->assertViewHas('registrations', fn ($page): bool => $page->count() === 2);
    }

    public function test_table_rejects_unapproved_sort_columns_and_page_sizes(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        foreach ([['sort' => 'email'], ['per_page' => 10000], ['direction' => 'drop'], ['columns' => ['document_hash']]] as $filters) {
            $this->getJson(route('admin.dashboard', $filters))->assertUnprocessable();
        }
    }

    public function test_inactive_admin_is_not_allowed_to_read_table(): void
    {
        $this->actingAs(Admin::factory()->create(['is_active' => false]), 'admin')->get(route('admin.dashboard'))->assertRedirect(route('admin.login'));
    }
}
