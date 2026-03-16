<?php

namespace Tests\Feature\IntegrationSecurity;

use App\Models\Empresa;
use App\Models\Servicio;
use App\Services\IntegrationSecurity\IntegrationCredentialService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class IntegrationSecurityMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    private string $uri = '/api/test/integration/module1';

    protected function setUp(): void
    {
        parent::setUp();

        Route::post($this->uri, function (Request $request) {
            $empresa = $request->attributes->get('empresa');
            $integration = $request->attributes->get('integration');

            return response()->json([
                'ok' => true,
                'empresa_id' => $empresa ? $empresa->id : null,
                'integration_id' => $integration ? $integration->id : null,
            ]);
        })->middleware('integration.auth:module1.form_ingress');

        Config::set('integration_security.rate_limit_profiles.strict', [
            'rpm' => 1,
            'burst' => 1,
            'burst_window_seconds' => 60,
        ]);

        Config::set('integration_security.rate_limit.default_profile', 'normal');

        // Evita contaminación entre tests cuando el RateLimiter usa cache persistente.
        cache()->flush();
    }

    public function test_valid_signed_request(): void
    {
        [$empresa, $integration, $plainSecret] = $this->prepareIntegration(true, true, 'active', 'normal');

        $payload = ['nombre' => 'Ana', 'telefono' => '3001234567'];
        $headers = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, $integration->public_key);

        $response = $this->postJson($this->uri, $payload, $headers);

        $response->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('empresa_id', $empresa->id)
            ->assertJsonPath('integration_id', $integration->id);
    }

    public function test_missing_headers(): void
    {
        $response = $this->postJson($this->uri, ['x' => 1], []);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Missing required authentication headers.');
    }

    public function test_invalid_public_key(): void
    {
        [$empresa, $integration, $plainSecret] = $this->prepareIntegration(true, true);

        $payload = ['nombre' => 'Ana'];
        $headers = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, 'qits_pk_invalid');

        $response = $this->postJson($this->uri, $payload, $headers);

        $response->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_inactive_integration(): void
    {
        [, $integration, $plainSecret] = $this->prepareIntegration(true, true, 'suspended');

        $payload = ['nombre' => 'Ana'];
        $headers = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, $integration->public_key);

        $this->postJson($this->uri, $payload, $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Integration is not active.');
    }

    public function test_invalid_signature(): void
    {
        [, $integration] = $this->prepareIntegration(true, true);

        $payload = ['nombre' => 'Ana'];
        $headers = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, 'wrong-secret', $integration->public_key);

        $this->postJson($this->uri, $payload, $headers)
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_expired_timestamp(): void
    {
        [, $integration, $plainSecret] = $this->prepareIntegration(true, true);

        $payload = ['nombre' => 'Ana'];
        $headers = $this->signedHeaders(
            'POST',
            ltrim($this->uri, '/'),
            $payload,
            $plainSecret,
            $integration->public_key,
            time() - 9999
        );

        $this->postJson($this->uri, $payload, $headers)
            ->assertStatus(401)
            ->assertJsonPath('message', 'Request timestamp expired.');
    }

    public function test_replayed_nonce(): void
    {
        [, $integration, $plainSecret] = $this->prepareIntegration(true, true);

        $payload = ['nombre' => 'Ana'];
        $fixedNonce = 'nonce-replayed-123';

        $headers = $this->signedHeaders(
            'POST',
            ltrim($this->uri, '/'),
            $payload,
            $plainSecret,
            $integration->public_key,
            time(),
            $fixedNonce
        );

        $this->postJson($this->uri, $payload, $headers)->assertStatus(200);

        $this->postJson($this->uri, $payload, $headers)
            ->assertStatus(401)
            ->assertJsonPath('message', 'Unauthorized.');
    }

    public function test_missing_scope(): void
    {
        [, $integration, $plainSecret] = $this->prepareIntegration(true, false);

        $payload = ['nombre' => 'Ana'];
        $headers = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, $integration->public_key);

        $this->postJson($this->uri, $payload, $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Access denied: insufficient scope.');
    }

    public function test_missing_required_business_service(): void
    {
        [, $integration, $plainSecret] = $this->prepareIntegration(false, true);

        $payload = ['nombre' => 'Ana'];
        $headers = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, $integration->public_key);

        $this->postJson($this->uri, $payload, $headers)
            ->assertStatus(403)
            ->assertJsonPath('message', 'Access denied: required service not active.');
    }

    public function test_successful_module1_access(): void
    {
        [$empresa, $integration, $plainSecret] = $this->prepareIntegration(true, true);

        $payload = ['nombre' => 'Ana'];
        $headers = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, $integration->public_key);

        $this->postJson($this->uri, $payload, $headers)
            ->assertStatus(200)
            ->assertJsonPath('ok', true)
            ->assertJsonPath('empresa_id', $empresa->id);
    }

    public function test_rate_limit_exceeded(): void
    {
        [, $integration, $plainSecret] = $this->prepareIntegration(true, true, 'active', 'strict');

        $payload = ['nombre' => 'Ana'];

        $h1 = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, $integration->public_key, time(), 'nonce-a');
        $h2 = $this->signedHeaders('POST', ltrim($this->uri, '/'), $payload, $plainSecret, $integration->public_key, time(), 'nonce-b');

        $this->postJson($this->uri, $payload, $h1)->assertStatus(200);

        $this->postJson($this->uri, $payload, $h2)
            ->assertStatus(429)
            ->assertJsonPath('message', 'Too Many Requests.');
    }

    /**
     * @return array{0: Empresa, 1: EmpresaIntegration, 2: string}
     */
    private function prepareIntegration(
        bool $attachBusinessService,
        bool $withScope,
        string $status = 'active',
        string $profile = 'normal'
    ): array {
        $empresa = $this->createEmpresa();

        $service = Servicio::create([
            'nombre' => 'Formularios API',
            'slug' => 'formularios-whatsapp-api',
            'descripcion' => 'Service for module 1',
            'activo' => true,
        ]);

        if ($attachBusinessService) {
            $empresa->servicios()->attach($service->id);
        }

        $moduleConfig = (array) config('integration_security.modules.module1.form_ingress');
        $moduleConfig['required_service_id'] = $service->id;
        config(['integration_security.modules.module1.form_ingress' => $moduleConfig]);

        $credentialService = app(IntegrationCredentialService::class);

        $issued = $credentialService->create($empresa, [
            'name' => 'WordPress Site',
            'provider_type' => 'wordpress',
            'status' => $status,
            'rate_limit_profile' => $profile,
            'scopes_json' => $withScope ? ['module1.form_ingress'] : ['module2.whatsapp_solution'],
        ]);

        return [$empresa, $issued->integration->fresh(), $issued->plainSecret];
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function signedHeaders(
        string $method,
        string $path,
        array $payload,
        string $plainSecret,
        string $publicKey,
        ?int $timestamp = null,
        ?string $nonce = null
    ): array {
        $timestamp = $timestamp ?? time();
        $nonce = $nonce ?? ('nonce-' . uniqid());

        $canonical = implode("\n", [
            strtoupper($method),
            '/' . ltrim($path, '/'),
            (string) $timestamp,
            $nonce,
            hash('sha256', json_encode($payload)),
        ]);

        $signingKey = hash('sha256', $plainSecret);
        $signature = hash_hmac('sha256', $canonical, $signingKey);

        return [
            'X-QITS-Key' => $publicKey,
            'X-QITS-Timestamp' => (string) $timestamp,
            'X-QITS-Nonce' => $nonce,
            'X-QITS-Signature' => $signature,
        ];
    }
}
