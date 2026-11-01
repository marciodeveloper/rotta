<?php

namespace Tests\Feature;

use App\Contracts\CepLookup;
use Tests\TestCase;

class CepControllerTest extends TestCase
{
    public function test_returns_normalized_address_from_configured_lookup(): void
    {
        $this->mock(CepLookup::class)->shouldReceive('find')->once()->with('27700-000')->andReturn([
            'postal_code' => '27700000', 'street' => 'Rua de Teste', 'neighborhood' => 'Centro',
            'city' => 'Vassouras', 'state' => 'RJ',
        ]);

        $response = $this->getJson(route('cep.lookup', ['postal_code' => '27700-000']));

        $response->assertOk()->assertJsonPath('data.city', 'Vassouras')->assertJsonPath('data.state', 'RJ');
    }
}
