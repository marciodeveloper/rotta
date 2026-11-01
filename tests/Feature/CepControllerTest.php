<?php

namespace Tests\Feature;

use App\Contracts\CepLookup;
use RuntimeException;
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

    public function test_returns_friendly_not_found_response(): void
    {
        $this->mock(CepLookup::class)->shouldReceive('find')->once()->andReturnNull();

        $this->getJson(route('cep.lookup', ['postal_code' => '00000-000']))
            ->assertNotFound()
            ->assertJsonPath('message', 'CEP não encontrado. Use o preenchimento manual.');
    }

    public function test_returns_friendly_service_unavailable_response_without_leaking_details(): void
    {
        $this->mock(CepLookup::class)->shouldReceive('find')->once()->andThrow(new RuntimeException('synthetic upstream details'));

        $this->getJson(route('cep.lookup', ['postal_code' => '12604-250']))
            ->assertStatus(503)
            ->assertExactJson([
                'message' => 'Não foi possível consultar o CEP agora. Tente novamente ou preencha o endereço manualmente.',
            ]);
    }
}
