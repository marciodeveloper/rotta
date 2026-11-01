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
}
