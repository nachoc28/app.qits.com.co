<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlow;
use Livewire\Component;
use Livewire\WithPagination;

class AiFlowIndex extends Component
{
    use WithPagination;

    protected $paginationTheme = 'tailwind';

    /** @var int */
    public $perPage = 10;

    public function mount(): void
    {
        $this->authorizeAdmin();
    }

    public function render()
    {
        $this->authorizeAdmin();

        return view('livewire.admin.ai-flows.ai-flow-index', [
            'flows' => AiFlow::query()
                ->with(['versions' => function ($query): void {
                    $query->orderByDesc('version_number');
                }])
                ->orderBy('name')
                ->paginate($this->perPage),
        ]);
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
