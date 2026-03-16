<?php

namespace Tests\Unit\WhatsAppHub;

use App\Exceptions\WhatsAppHub\DomainNotAllowedException;
use App\Exceptions\WhatsAppHub\InvalidSiteKeyException;
use App\Exceptions\WhatsAppHub\ModuleAccessDeniedException;
use App\Models\Empresa;
use App\Models\FormForwardingRule;
use App\Models\LeadDocument;
use App\Models\WaLead;
use App\Services\WhatsAppHub\FormIngressService;
use App\Services\WhatsAppHub\LeadNormalizerService;
use App\Services\WhatsAppHub\LeadPersistenceService;
use App\Services\WhatsAppHub\ModuleAccessService;
use App\Services\WhatsAppHub\NormalizedLeadData;
use App\Services\WhatsAppHub\PdfLeadService;
use App\Services\WhatsAppHub\TenantContext;
use App\Services\WhatsAppHub\TenantResolverService;
use App\Services\WhatsAppHub\WhatsAppDispatchResult;
use App\Services\WhatsAppHub\WhatsAppDispatchService;
use Mockery;
use PHPUnit\Framework\TestCase;

class FormIngressServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_valid_form_ingress_returns_structured_result(): void
    {
        [$service, $deps] = $this->makeService();
        [$empresa, $rule, $lead, $events] = $this->buildDomainObjects();

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')
            ->once()
            ->with('site_abc')
            ->andReturn(new TenantContext($empresa, $rule));

        $deps['moduleAccess']->shouldReceive('authorizeModule1')->once()->with($empresa);

        $normalized = $this->normalizedDto();
        $deps['normalizer']->shouldReceive('normalize')->once()->andReturn($normalized);
        $deps['persistence']->shouldReceive('persist')->once()->andReturn($lead);

        $deps['pdfService']->shouldReceive('generateAndPersistForLead')->once()->andReturn(new LeadDocument());

        $deps['dispatchService']->shouldReceive('dispatchForLead')->once()->andReturn(
            new WhatsAppDispatchResult(false, true, true, true, false, false, false, false, null)
        );

        $result = $service->process('site_abc', ['nombre' => 'Ana', 'telefono' => '3001234567'], [
            'origin' => 'https://www.example.com/form',
            'referer' => null,
        ]);

        self::assertSame(501, $result->leadId);
        self::assertSame(101, $result->empresaId);
        self::assertTrue($result->pdfGenerated);
        self::assertTrue($result->whatsAppSent);
        self::assertFalse($result->whatsAppFailed);
        self::assertTrue($result->outboundLogged);

    }

    public function test_invalid_site_key_throws_exception(): void
    {
        [$service, $deps] = $this->makeService();

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')
            ->once()
            ->with('bad_key')
            ->andThrow(new InvalidSiteKeyException('bad_key'));

        $this->expectException(InvalidSiteKeyException::class);
        $service->process('bad_key', ['nombre' => 'Ana', 'telefono' => '3001234567']);
    }

    public function test_company_without_required_service_throws_exception(): void
    {
        [$service, $deps] = $this->makeService();
        [$empresa, $rule] = $this->buildDomainObjects(false);

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')
            ->once()
            ->andReturn(new TenantContext($empresa, $rule));

        $deps['moduleAccess']->shouldReceive('authorizeModule1')
            ->once()
            ->andThrow(new ModuleAccessDeniedException($empresa, 'module_1', 'sin servicio activo 1'));

        $this->expectException(ModuleAccessDeniedException::class);
        $service->process('site_abc', ['nombre' => 'Ana', 'telefono' => '3001234567'], [
            'origin' => 'https://example.com',
        ]);
    }

    public function test_inactive_forwarding_rule_throws_invalid_site_key(): void
    {
        [$service, $deps] = $this->makeService();
        [$empresa, $rule] = $this->buildDomainObjects(false);
        $rule->is_active = false;

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')
            ->once()
            ->andReturn(new TenantContext($empresa, $rule));

        $this->expectException(InvalidSiteKeyException::class);
        $service->process('site_abc', ['nombre' => 'Ana', 'telefono' => '3001234567'], [
            'origin' => 'https://example.com',
        ]);
    }

    public function test_unauthorized_domain_throws_exception(): void
    {
        [$service, $deps] = $this->makeService();
        [$empresa, $rule] = $this->buildDomainObjects(false);

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')
            ->once()
            ->andReturn(new TenantContext($empresa, $rule));

        $deps['moduleAccess']->shouldReceive('authorizeModule1')->once()->with($empresa);

        $this->expectException(DomainNotAllowedException::class);
        $service->process('site_abc', ['nombre' => 'Ana', 'telefono' => '3001234567'], [
            'origin' => 'https://evil-domain.test/form',
        ]);
    }

    public function test_successful_lead_persistence_returns_lead_id(): void
    {
        [$service, $deps] = $this->makeService();
        [$empresa, $rule, $lead] = $this->buildDomainObjects();

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')->once()->andReturn(new TenantContext($empresa, $rule));
        $deps['moduleAccess']->shouldReceive('authorizeModule1')->once()->with($empresa);
        $deps['normalizer']->shouldReceive('normalize')->once()->andReturn($this->normalizedDto());
        $deps['persistence']->shouldReceive('persist')->once()->andReturn($lead);
        $deps['pdfService']->shouldReceive('generateAndPersistForLead')->once()->andReturn(null);
        $deps['dispatchService']->shouldReceive('dispatchForLead')->once()->andReturn(new WhatsAppDispatchResult());

        $result = $service->process('site_abc', ['nombre' => 'Ana', 'telefono' => '3001234567'], [
            'origin' => 'https://example.com/form',
        ]);

        self::assertSame(501, $result->leadId);
    }

    public function test_successful_outbound_logging_is_reflected_in_result(): void
    {
        [$service, $deps] = $this->makeService();
        [$empresa, $rule, $lead] = $this->buildDomainObjects();

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')->once()->andReturn(new TenantContext($empresa, $rule));
        $deps['moduleAccess']->shouldReceive('authorizeModule1')->once()->with($empresa);
        $deps['normalizer']->shouldReceive('normalize')->once()->andReturn($this->normalizedDto());
        $deps['persistence']->shouldReceive('persist')->once()->andReturn($lead);
        $deps['pdfService']->shouldReceive('generateAndPersistForLead')->once()->andReturn(null);
        $deps['dispatchService']->shouldReceive('dispatchForLead')->once()->andReturn(
            new WhatsAppDispatchResult(false, true, true, true, false, false, false, false, null)
        );

        $result = $service->process('site_abc', ['nombre' => 'Ana', 'telefono' => '3001234567'], [
            'origin' => 'https://example.com/form',
        ]);

        self::assertTrue($result->outboundLogged);
        self::assertTrue($result->whatsAppSent);
    }

    public function test_graceful_failure_when_whatsapp_sending_fails(): void
    {
        [$service, $deps] = $this->makeService();
        [$empresa, $rule, $lead, $events] = $this->buildDomainObjects();

        $deps['tenantResolver']->shouldReceive('resolveFromSiteKey')->once()->andReturn(new TenantContext($empresa, $rule));
        $deps['moduleAccess']->shouldReceive('authorizeModule1')->once()->with($empresa);
        $deps['normalizer']->shouldReceive('normalize')->once()->andReturn($this->normalizedDto());
        $deps['persistence']->shouldReceive('persist')->once()->andReturn($lead);
        $deps['pdfService']->shouldReceive('generateAndPersistForLead')->once()->andReturn(null);
        $deps['dispatchService']->shouldReceive('dispatchForLead')->once()->andReturn(
            new WhatsAppDispatchResult(false, true, true, false, true, false, false, false, 'provider timeout')
        );

        $result = $service->process('site_abc', ['nombre' => 'Ana', 'telefono' => '3001234567'], [
            'origin' => 'https://example.com/form',
        ]);

        self::assertFalse($result->whatsAppSent);
        self::assertTrue($result->whatsAppFailed);
    }

    /**
     * @return array{FormIngressService,array<string,mixed>}
     */
    private function makeService(): array
    {
        $tenantResolver = Mockery::mock(TenantResolverService::class);
        $moduleAccess = Mockery::mock(ModuleAccessService::class);
        $normalizer = Mockery::mock(LeadNormalizerService::class);
        $persistence = Mockery::mock(LeadPersistenceService::class);
        $pdfService = Mockery::mock(PdfLeadService::class);
        $dispatchService = Mockery::mock(WhatsAppDispatchService::class);

        $service = new FormIngressService(
            $tenantResolver,
            $moduleAccess,
            $normalizer,
            $persistence,
            $pdfService,
            $dispatchService
        );

        return [$service, [
            'tenantResolver' => $tenantResolver,
            'moduleAccess' => $moduleAccess,
            'normalizer' => $normalizer,
            'persistence' => $persistence,
            'pdfService' => $pdfService,
            'dispatchService' => $dispatchService,
        ]];
    }

    /**
     * @return array{Empresa,FormForwardingRule,WaLead,\Mockery\MockInterface|null}
     */
    private function buildDomainObjects(bool $withLead = true): array
    {
        $empresa = new Empresa();
        $empresa->id = 101;
        $empresa->nombre = 'Empresa Demo';
        $empresa->active = true;

        $rule = new FormForwardingRule();
        $rule->id = 201;
        $rule->site_key = 'site_abc';
        $rule->form_name = 'Formulario Web';
        $rule->allowed_domain = 'example.com';
        $rule->is_active = true;

        if (! $withLead) {
            return [$empresa, $rule, null, null];
        }

        $events = Mockery::mock();
        $events->shouldReceive('create')->andReturnNull()->byDefault();

        $lead = Mockery::mock(WaLead::class);
        $lead->id = 501;
        $lead->empresa_id = 101;
        $lead->shouldReceive('events')->andReturn($events)->byDefault();

        return [$empresa, $rule, $lead, $events];
    }

    private function normalizedDto(): NormalizedLeadData
    {
        return new NormalizedLeadData(
            'Ana Perez',
            '3001234567',
            'ana@example.com',
            'ACME',
            'Bogota',
            'Hola',
            'Formulario Web',
            'https://example.com',
            null,
            'example.com',
            'https://example.com/form',
            'google',
            'campania-1',
            'Campania',
            'cpc'
        );
    }
}
