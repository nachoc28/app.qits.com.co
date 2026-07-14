<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlowExecution;
use App\Services\AiFlows\AiFlowAccessService;
use App\Support\AiFlowLabels;
use Livewire\Component;
use Livewire\WithPagination;

class AiFlowExecutionIndex extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    /** @var int */
    public $perPage = 10;

    public function mount(AiFlowAccessService $accessService): void
    {
        $this->authorizeAccess($accessService);
    }

    public function render(AiFlowAccessService $accessService)
    {
        $this->authorizeAccess($accessService);

        return view('livewire.admin.ai-flows.ai-flow-execution-index', [
            'executions' => AiFlowExecution::query()
                ->with(['empresa', 'flow', 'version', 'startedBy'])
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->paginate($this->perPage),
            'statusLabels' => AiFlowLabels::executionStatusOptions(),
        ]);
    }

    private function authorizeAccess(AiFlowAccessService $accessService): void
    {
        abort_unless($accessService->canExecuteFlows(auth()->user()), 403);
    }
}
