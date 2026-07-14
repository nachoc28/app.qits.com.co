<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlow;
use App\Models\AiFlowVersion;
use App\Services\AiFlows\AiFlowVersionService;
use App\Support\AiFlowLabels;
use InvalidArgumentException;
use Livewire\Component;

class AiFlowVersionIndex extends Component
{
    /** @var int */
    public $flowId;

    /** @var array<int, string> */
    public $publicationErrors = [];

    public function mount(int $flowId): void
    {
        $this->authorizeAdmin();
        $this->flowId = $flowId;
    }

    public function createDraftVersion(): void
    {
        $this->authorizeAdmin();
        $flow = $this->flow();
        $nextVersion = ((int) $flow->versions()->max('version_number')) + 1;

        AiFlowVersion::query()->create([
            'ai_flow_id' => $flow->id,
            'version_number' => $nextVersion > 0 ? $nextVersion : 1,
            'status' => AiFlowVersion::STATUS_DRAFT,
        ]);

        session()->flash('ai_flow_version_success', 'Versión borrador creada correctamente.');
    }

    public function publishVersion(int $versionId, AiFlowVersionService $versionService): void
    {
        $this->authorizeAdmin();
        $this->publicationErrors = [];
        $version = $this->flow()->versions()->whereKey($versionId)->firstOrFail();

        try {
            $versionService->publish($version, auth()->user());
            session()->flash('ai_flow_version_success', 'Versión publicada correctamente.');
        } catch (InvalidArgumentException $exception) {
            $this->publicationErrors = [$exception->getMessage()];
        }
    }

    public function render()
    {
        $this->authorizeAdmin();
        $flow = $this->flow();

        return view('livewire.admin.ai-flows.ai-flow-version-index', [
            'flow' => $flow,
            'versions' => $flow->versions()->orderByDesc('version_number')->get(),
            'statusOptions' => AiFlowLabels::versionStatusOptions(),
        ]);
    }

    private function flow(): AiFlow
    {
        return AiFlow::query()->findOrFail($this->flowId);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
