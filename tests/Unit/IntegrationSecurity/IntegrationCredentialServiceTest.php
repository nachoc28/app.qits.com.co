<?php

namespace Tests\Unit\IntegrationSecurity;

use App\Models\Empresa;
use App\Services\IntegrationSecurity\IntegrationCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class IntegrationCredentialServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_creates_integration_and_returns_plain_secret_only_once(): void
    {
        $empresa = $this->createEmpresa();
        $service = app(IntegrationCredentialService::class);

        $issued = $service->create($empresa, [
            'name' => 'WordPress Main Site',
            'provider_type' => 'wordpress',
            'scopes_json' => ['module1.form_ingress'],
            'rate_limit_profile' => 'normal',
        ]);

        $this->assertNotEmpty($issued->plainSecret);
        $this->assertStringStartsWith('qits_sk_', $issued->plainSecret);

        $integration = $issued->integration->fresh();
        $this->assertSame($empresa->id, $integration->empresa_id);
        $this->assertSame('WordPress Main Site', $integration->name);
        $this->assertSame('wordpress', $integration->provider_type);
        $this->assertSame('active', $integration->status);
        $this->assertNotSame($issued->plainSecret, $integration->secret_hash);
        $this->assertSame(hash('sha256', $issued->plainSecret), $integration->secret_hash);
    }

    public function test_supports_multiple_integrations_per_empresa(): void
    {
        $empresa = $this->createEmpresa();
        $service = app(IntegrationCredentialService::class);

        $a = $service->create($empresa, ['name' => 'Site A', 'scopes_json' => ['module1.form_ingress']]);
        $b = $service->create($empresa, ['name' => 'Site B', 'scopes_json' => ['module2.whatsapp_solution']]);

        $this->assertNotSame($a->integration->id, $b->integration->id);
        $this->assertNotSame($a->integration->public_key, $b->integration->public_key);
        $this->assertCount(2, $empresa->fresh()->integrations);
    }

    public function test_rotates_secret_and_invalidates_previous_one(): void
    {
        $empresa = $this->createEmpresa();
        $service = app(IntegrationCredentialService::class);

        $issued = $service->create($empresa, ['name' => 'Site A', 'scopes_json' => ['module1.form_ingress']]);
        $oldHash = $issued->integration->secret_hash;

        $rotated = $service->rotateSecret($issued->integration);

        $this->assertNotSame($issued->plainSecret, $rotated->plainSecret);
        $this->assertNotSame($oldHash, $rotated->integration->secret_hash);
        $this->assertSame(hash('sha256', $rotated->plainSecret), $rotated->integration->secret_hash);
    }

    public function test_revoke_activate_and_deactivate_lifecycle(): void
    {
        $empresa = $this->createEmpresa();
        $service = app(IntegrationCredentialService::class);

        $issued = $service->create($empresa, ['name' => 'Site A', 'scopes_json' => ['module1.form_ingress']]);

        $suspended = $service->deactivate($issued->integration);
        $this->assertSame('suspended', $suspended->status);

        $active = $service->activate($suspended);
        $this->assertSame('active', $active->status);

        $revoked = $service->revoke($active);
        $this->assertSame('revoked', $revoked->status);
    }

    private function createEmpresa(): Empresa
    {
        $ciudadId = (int) DB::table('ciudades')->value('id');

        return Empresa::create([
            'nit' => 'NIT-' . uniqid('', true),
            'nombre' => 'Empresa Test',
            'direccion' => 'Calle 123',
            'ciudad_id' => $ciudadId,
            'telefono' => '3000000000',
            'email' => 'empresa' . uniqid() . '@test.local',
            'active' => true,
        ]);
    }
}
