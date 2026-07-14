<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlow;
use App\Models\Empresa;
use App\Services\AiFlows\AiFlowAccessService;
use App\Services\AiFlows\AiFlowExecutionService;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Livewire\Component;

class AiFlowExecutionForm extends Component
{
    /** @var int|string|null */
    public $empresa_id = '';

    /** @var int|string|null */
    public $ai_flow_id = '';

    /** @var string */
    public $title = '';

    /** @var string|null */
    public $formError;

    public function mount(AiFlowAccessService $accessService): void
    {
        $this->authorizeAccess($accessService);
    }

    public function createExecution(
        AiFlowAccessService $accessService,
        AiFlowExecutionService $executionService
    ): void {
        $this->authorizeAccess($accessService);

        $validated = $this->validate($this->rules(), $this->messages());
        $empresa = Empresa::query()->findOrFail((int) $validated['empresa_id']);
        $flow = AiFlow::query()->findOrFail((int) $validated['ai_flow_id']);

        abort_unless($accessService->canAccessEmpresa(auth()->user(), $empresa), 403);

        if (! $flow->is_active || ! $executionService->publishedVersionForFlow($flow)) {
            throw ValidationException::withMessages([
                'ai_flow_id' => 'Solo se pueden iniciar flujos activos con una versión publicada.',
            ]);
        }

        try {
            $execution = $executionService->createExecution($empresa, $flow, $validated['title'], auth()->user());
        } catch (InvalidArgumentException $exception) {
            $this->formError = $exception->getMessage();

            return;
        }

        session()->flash('ai_flow_execution_success', 'Ejecución creada correctamente.');
        redirect()->route('admin.ai-flow-executions.show', $execution);
    }

    public function render(AiFlowAccessService $accessService)
    {
        $this->authorizeAccess($accessService);

        $flows = AiFlow::query()
            ->where('is_active', true)
            ->with(['versions' => function ($query): void {
                $query->where('status', \App\Models\AiFlowVersion::STATUS_PUBLISHED)
                    ->orderByDesc('version_number');
            }])
            ->orderBy('name')
            ->get()
            ->filter(static function (AiFlow $flow): bool {
                return $flow->versions->isNotEmpty();
            })
            ->values();

        return view('livewire.admin.ai-flows.ai-flow-execution-form', [
            'empresas' => $accessService->authorizedEmpresas(auth()->user()),
            'flows' => $flows,
        ]);
    }

    private function rules(): array
    {
        return [
            'empresa_id' => ['required', 'integer', 'exists:empresas,id'],
            'ai_flow_id' => ['required', 'integer', 'exists:ai_flows,id'],
            'title' => ['required', 'string', 'max:180'],
        ];
    }

    private function messages(): array
    {
        return [
            'empresa_id.required' => 'La empresa es obligatoria.',
            'empresa_id.exists' => 'La empresa seleccionada no existe.',
            'ai_flow_id.required' => 'El flujo es obligatorio.',
            'ai_flow_id.exists' => 'El flujo seleccionado no existe.',
            'title.required' => 'El título de la ejecución es obligatorio.',
            'title.max' => 'El título no debe superar 180 caracteres.',
        ];
    }

    private function authorizeAccess(AiFlowAccessService $accessService): void
    {
        abort_unless($accessService->canExecuteFlows(auth()->user()), 403);
    }
}
