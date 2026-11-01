<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\AdminAuditEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AdminAuditEvent>
 */
class AdminAuditEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'admin_id' => Admin::factory(),
            'action' => 'test.event',
            'metadata' => [],
            'ip_hash' => hash('sha256', '127.0.0.1'),
        ];
    }
}
