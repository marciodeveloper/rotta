<?php

namespace Database\Factories;

use App\Models\Admin;
use App\Models\Prospect;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Prospect> */
class ProspectFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Estabelecimento sintético '.Str::uuid();

        return ['business_name' => $name, 'normalized_name' => Str::lower($name), 'city' => 'Vassouras', 'normalized_city' => 'vassouras', 'state' => 'RJ', 'priority' => 'medium', 'commercial_status' => 'identified', 'lock_version' => 1, 'confirmed_fields' => (object) [], 'creation_token' => Str::uuid(), 'creation_digest' => str_repeat('a', 64), 'created_by' => Admin::factory(), 'updated_by' => Admin::factory()];
    }
}
