<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlowStrategicOutput;
use App\Services\AiFlows\AiFlowAccessService;
use App\Support\AiFlowLabels;
use Livewire\Component;
use Livewire\WithPagination;

class AiFlowStrategicOutputIndex extends Component
{
    use WithPagination;

    public function mount(AiFlowAccessService $accessService): void
    {
        abort_unless($accessService->canViewStrategicOutputs(auth()->user()), 403);
    }

    public function render(AiFlowAccessService $accessService)
    {
        abort_unless($accessService->canViewStrategicOutputs(auth()->user()), 403);

        $outputs = AiFlowStrategicOutput::query()
            ->with([
                'empresa',
                'execution.flow',
                'executionStep.step',
                'markedBy',
            ])
            ->orderByDesc('marked_at')
            ->orderByDesc('id')
            ->paginate(10);

        return view('livewire.admin.ai-flows.ai-flow-strategic-output-index', [
            'outputs' => $outputs,
            'typeLabels' => AiFlowLabels::strategicOutputTypeOptions(),
        ]);
    }
}
