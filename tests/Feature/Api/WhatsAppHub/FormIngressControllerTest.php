<?php

namespace Tests\Feature\Api\WhatsAppHub;

use App\Exceptions\WhatsAppHub\InvalidSiteKeyException;
use App\Services\WhatsAppHub\FormIngressResult;
use App\Services\WhatsAppHub\FormIngressService;
use Tests\TestCase;

class FormIngressControllerTest extends TestCase
{
    public function test_controller_returns_structured_response_on_success(): void
    {
        $this->mock(FormIngressService::class, function ($mock) {
            $mock->shouldReceive('process')
                ->once()
                ->andReturn(new FormIngressResult(55, 10, 'site_abc', true, false, true, false, true));
        });

        $response = $this->postJson('/api/form-ingress/site_abc', [
            'nombre' => 'Ana Perez',
            'telefono' => '3001234567',
            'domain' => 'example.com',
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lead_id', 55)
            ->assertJsonPath('data.whatsapp_sent', true)
            ->assertJsonPath('data.outbound_logged', true);
    }

    public function test_controller_maps_invalid_site_key_to_403(): void
    {
        $this->mock(FormIngressService::class, function ($mock) {
            $mock->shouldReceive('process')
                ->once()
                ->andThrow(new InvalidSiteKeyException('invalid'));
        });

        $response = $this->postJson('/api/form-ingress/invalid', [
            'nombre' => 'Ana Perez',
            'telefono' => '3001234567',
        ]);

        $response->assertStatus(403)
            ->assertJsonPath('success', false);
    }
}
