<?php

namespace Tests\Feature\AiFlows;

use App\Models\AiFlow;
use App\Models\AiFlowExecution;
use App\Models\AiFlowStep;
use App\Models\AiFlowVariable;
use App\Models\AiFlowVersion;
use App\Models\Empresa;
use App\Models\TipoUsuario;
use App\Models\User;
use App\Services\AiFlows\AiFlowVariableParser;
use App\Services\AiFlows\AiFlowVersionService;
use App\Services\AiFlows\AiFlowVersionValidationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Tests\TestCase;

class AiFlowVersionPublicationTest extends TestCase
{
    use RefreshDatabase;

    public function test_parser_detects_valid_variables(): void
    {
        $result = app(AiFlowVariableParser::class)->parse(
            'Analiza {{pais}}, {{ciudad}}, {{sitio_web}}, {{objetivo_estrategico}} y {{publico_objetivo_preliminar}}.'
        );

        $this->assertSame([
            'pais',
            'ciudad',
            'sitio_web',
            'objetivo_estrategico',
            'publico_objetivo_preliminar',
        ], $result['variables']);
        $this->assertSame([], $result['invalid_tokens']);
    }

    public function test_parser_ignores_duplicates_preserving_first_appearance(): void
    {
        $result = app(AiFlowVariableParser::class)->parse(
            '{{pais}} {{ciudad}} {{pais}} {{sitio_web}} {{ciudad}}'
        );

        $this->assertSame(['pais', 'ciudad', 'sitio_web'], $result['variables']);
    }

    public function test_parser_detects_invalid_tokens(): void
    {
        $result = app(AiFlowVariableParser::class)->parse(
            '{{País}} {{nombre variable}} {{público_objetivo}} {{Objetivo Estratégico}} {{}}'
        );

        $this->assertSame([], $result['variables']);
        $this->assertSame([
            'País',
            'nombre variable',
            'público_objetivo',
            'Objetivo Estratégico',
            '',
        ], $result['invalid_tokens']);
    }

    public function test_parser_rejects_spaces_accents_and_uppercase(): void
    {
        $parser = app(AiFlowVariableParser::class);

        $this->assertSame(['nombre variable'], $parser->parse('{{nombre variable}}')['invalid_tokens']);
        $this->assertSame(['público_objetivo'], $parser->parse('{{público_objetivo}}')['invalid_tokens']);
        $this->assertSame(['Objetivo'], $parser->parse('{{Objetivo}}')['invalid_tokens']);
    }

    public function test_parser_works_with_long_prompt(): void
    {
        $prompt = str_repeat('Contexto largo sin variables. ', 500) . '{{pais}} ' . str_repeat('Mas contexto. ', 500) . '{{ciudad}}';
        $result = app(AiFlowVariableParser::class)->parse($prompt);

        $this->assertSame(['pais', 'ciudad'], $result['variables']);
        $this->assertSame([], $result['invalid_tokens']);
    }

    public function test_version_without_steps_cannot_be_published(): void
    {
        [, $version] = $this->createDraftFlow();

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertFalse($result['can_publish']);
        $this->assertContains('La versión debe tener al menos una etapa activa.', $result['errors']);
    }

    public function test_active_step_without_base_prompt_cannot_be_published(): void
    {
        [, $version] = $this->createDraftFlow();
        $this->createStep($version, ['base_prompt' => null]);

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertFalse($result['can_publish']);
        $this->assertStringContainsString('debe tener un prompt base', implode(' ', $result['errors']));
    }

    public function test_placeholder_without_configured_variable_cannot_be_published(): void
    {
        [, $version] = $this->createDraftFlow();
        $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertFalse($result['can_publish']);
        $this->assertContains('La variable "pais" aparece en prompts pero no está configurada.', $result['errors']);
    }

    public function test_invalid_token_in_prompt_cannot_be_published(): void
    {
        [, $version] = $this->createDraftFlow();
        $this->createStep($version, ['base_prompt' => 'Analiza {{País}}.']);

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertFalse($result['can_publish']);
        $this->assertStringContainsString('contiene una variable inválida', implode(' ', $result['errors']));
    }

    public function test_configured_variable_not_used_generates_warning(): void
    {
        [, $version] = $this->createDraftFlow();
        $step = $this->createStep($version, ['base_prompt' => 'Prompt sin variables.']);
        $this->createVariable($version, $step, 'pais');

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertTrue($result['can_publish']);
        $this->assertContains('La variable "pais" está configurada pero no aparece en ningún prompt activo.', $result['warnings']);
    }

    public function test_output_variable_without_source_step_cannot_be_published(): void
    {
        [, $version] = $this->createDraftFlow();
        $step = $this->createStep($version, ['base_prompt' => 'Usa {{resultado_analisis}}.']);
        AiFlowVariable::create([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => $step->id,
            'source_step_id' => null,
            'name' => 'resultado_analisis',
            'label' => 'Resultado analisis',
            'scope' => AiFlowVariable::SCOPE_OUTPUT,
            'input_type' => AiFlowVariable::INPUT_TYPE_TEXTAREA,
            'is_required' => true,
            'position' => 1,
        ]);

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertFalse($result['can_publish']);
        $this->assertContains('La variable de resultado "resultado_analisis" debe tener una etapa fuente.', $result['errors']);
    }

    public function test_output_variable_with_source_step_from_another_version_cannot_be_published(): void
    {
        [, $version] = $this->createDraftFlow('flujo_output');
        $step = $this->createStep($version, ['base_prompt' => 'Usa {{resultado_analisis}}.']);
        [, $otherVersion] = $this->createDraftFlow('flujo_output_otro');
        $otherStep = $this->createStep($otherVersion, ['base_prompt' => 'Otro prompt.']);

        DB::table('ai_flow_variables')->insert([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => $step->id,
            'source_step_id' => $otherStep->id,
            'name' => 'resultado_analisis',
            'label' => 'Resultado analisis',
            'scope' => AiFlowVariable::SCOPE_OUTPUT,
            'input_type' => AiFlowVariable::INPUT_TYPE_TEXTAREA,
            'is_required' => true,
            'position' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertFalse($result['can_publish']);
        $this->assertContains('La variable "resultado_analisis" referencia una etapa fuente de otra versión.', $result['errors']);
    }

    public function test_dependency_between_steps_from_different_versions_cannot_be_published(): void
    {
        [, $version] = $this->createDraftFlow('flujo_dep');
        $step = $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);
        $this->createVariable($version, $step, 'pais');
        [, $otherVersion] = $this->createDraftFlow('flujo_dep_otro');
        $otherStep = $this->createStep($otherVersion, ['base_prompt' => 'Otro prompt.']);

        DB::table('ai_flow_step_dependencies')->insert([
            'ai_flow_step_id' => $step->id,
            'depends_on_step_id' => $otherStep->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertFalse($result['can_publish']);
        $this->assertStringContainsString('referencia una etapa de otra versión', implode(' ', $result['errors']));
    }

    public function test_valid_version_can_be_published(): void
    {
        [, $version] = $this->createDraftFlow();
        $step = $this->createStep($version, ['base_prompt' => 'Analiza {{pais}} y {{ciudad}}.']);
        $this->createVariable($version, $step, 'pais');
        $this->createVariable($version, $step, 'ciudad', 2);

        $result = app(AiFlowVersionValidationService::class)->validateForPublication($version);

        $this->assertTrue($result['can_publish']);
        $this->assertSame([], $result['errors']);
        $this->assertSame(['pais', 'ciudad'], $result['detected_variables']);
    }

    public function test_publish_changes_version_status_to_published(): void
    {
        $admin = $this->createUser('Administrador');
        [, $version] = $this->createDraftFlow('flujo_publicar', $admin);
        $step = $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);
        $this->createVariable($version, $step, 'pais');

        $published = app(AiFlowVersionService::class)->publish($version, $admin);

        $this->assertSame(AiFlowVersion::STATUS_PUBLISHED, $published->status);
    }

    public function test_only_one_version_remains_published_per_flow(): void
    {
        $admin = $this->createUser('Administrador');
        [$flow, $publishedVersion] = $this->createDraftFlow('flujo_unico', $admin);
        $publishedVersion->forceFill([
            'status' => AiFlowVersion::STATUS_PUBLISHED,
            'published_at' => now()->subDay(),
            'published_by' => $admin->id,
        ])->save();

        $newVersion = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 2,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);
        $step = $this->createStep($newVersion, ['base_prompt' => 'Analiza {{pais}}.']);
        $this->createVariable($newVersion, $step, 'pais');

        app(AiFlowVersionService::class)->publish($newVersion, $admin);

        $this->assertSame(AiFlowVersion::STATUS_ARCHIVED, $publishedVersion->fresh()->status);
        $this->assertSame(AiFlowVersion::STATUS_PUBLISHED, $newVersion->fresh()->status);
        $this->assertSame(1, AiFlowVersion::query()
            ->where('ai_flow_id', $flow->id)
            ->where('status', AiFlowVersion::STATUS_PUBLISHED)
            ->count());
    }

    public function test_publish_stores_published_at_and_published_by(): void
    {
        $admin = $this->createUser('Administrador');
        [, $version] = $this->createDraftFlow('flujo_auditoria', $admin);
        $step = $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);
        $this->createVariable($version, $step, 'pais');

        $published = app(AiFlowVersionService::class)->publish($version, $admin);

        $this->assertNotNull($published->published_at);
        $this->assertSame((int) $admin->id, (int) $published->published_by);
    }

    public function test_publish_does_not_change_state_when_validation_has_errors(): void
    {
        $admin = $this->createUser('Administrador');
        [, $version] = $this->createDraftFlow('flujo_error', $admin);
        $this->createStep($version, ['base_prompt' => 'Analiza {{pais}}.']);

        try {
            app(AiFlowVersionService::class)->publish($version, $admin);
            $this->fail('Expected validation exception was not thrown.');
        } catch (InvalidArgumentException $exception) {
            $this->assertStringContainsString('no está configurada', $exception->getMessage());
        }

        $this->assertSame(AiFlowVersion::STATUS_DRAFT, $version->fresh()->status);
        $this->assertNull($version->fresh()->published_at);
    }

    public function test_published_version_with_historical_executions_is_protected_from_editing(): void
    {
        $admin = $this->createUser('Administrador');
        $empresa = $this->createEmpresa('Empresa Historial');
        [$flow, $version] = $this->createDraftFlow('flujo_historial', $admin);
        $version->forceFill([
            'status' => AiFlowVersion::STATUS_PUBLISHED,
            'published_at' => now(),
            'published_by' => $admin->id,
        ])->save();
        AiFlowExecution::create([
            'empresa_id' => $empresa->id,
            'ai_flow_id' => $flow->id,
            'ai_flow_version_id' => $version->id,
            'title' => 'Ejecucion historica',
            'status' => AiFlowExecution::STATUS_PENDING,
        ]);

        $this->expectException(InvalidArgumentException::class);

        app(AiFlowVersionService::class)->ensureVersionCanBeEdited($version);
    }

    /**
     * @return array{0: AiFlow, 1: AiFlowVersion}
     */
    private function createDraftFlow(string $key = 'investigacion_mercado', ?User $user = null): array
    {
        $user = $user ?: $this->createUser('Administrador');

        $flow = AiFlow::create([
            'key' => $key . '_' . uniqid(),
            'name' => 'Flujo ' . $key,
            'description' => 'Flujo de pruebas',
            'is_active' => true,
            'created_by' => $user->id,
        ]);

        $version = AiFlowVersion::create([
            'ai_flow_id' => $flow->id,
            'version_number' => 1,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);

        return [$flow, $version];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function createStep(AiFlowVersion $version, array $attributes = []): AiFlowStep
    {
        return AiFlowStep::create(array_merge([
            'ai_flow_version_id' => $version->id,
            'step_key' => 'diagnostico_' . uniqid(),
            'name' => 'Diagnostico',
            'description' => 'Etapa de diagnostico',
            'position' => (int) AiFlowStep::query()->where('ai_flow_version_id', $version->id)->count() + 1,
            'recommended_gpt' => '@InvestigadorMercado',
            'expected_output_name' => 'Diagnostico',
            'base_prompt' => 'Analiza {{pais}}.',
            'is_active' => true,
        ], $attributes));
    }

    private function createVariable(
        AiFlowVersion $version,
        AiFlowStep $step,
        string $name,
        int $position = 1
    ): AiFlowVariable {
        return AiFlowVariable::create([
            'ai_flow_version_id' => $version->id,
            'ai_flow_step_id' => $step->id,
            'source_step_id' => null,
            'name' => $name,
            'label' => ucfirst(str_replace('_', ' ', $name)),
            'scope' => AiFlowVariable::SCOPE_STEP,
            'input_type' => AiFlowVariable::INPUT_TYPE_INPUT,
            'is_required' => true,
            'position' => $position,
        ]);
    }

    private function createEmpresa(string $name): Empresa
    {
        $ciudadId = (int) DB::table('ciudades')->value('id');

        return Empresa::create([
            'nit' => 'NIT-' . uniqid('', true),
            'nombre' => $name,
            'direccion' => 'Calle 123',
            'ciudad_id' => $ciudadId,
            'telefono' => '3000000000',
            'email' => strtolower(str_replace(' ', '', $name)) . uniqid() . '@test.local',
            'active' => true,
        ]);
    }

    private function createUser(string $roleName, ?Empresa $empresa = null): User
    {
        $tipoUsuario = TipoUsuario::query()->firstOrCreate([
            'nombre' => $roleName,
        ]);

        return User::create([
            'name' => 'Usuario Test ' . uniqid(),
            'email' => 'user' . uniqid() . '@test.local',
            'email_verified_at' => now(),
            'password' => bcrypt('secret123'),
            'empresa_id' => $empresa ? $empresa->id : null,
            'tipo_usuario_id' => $tipoUsuario->id,
            'active' => true,
        ]);
    }
}
