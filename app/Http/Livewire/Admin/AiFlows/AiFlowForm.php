<?php

namespace App\Http\Livewire\Admin\AiFlows;

use App\Models\AiFlow;
use Illuminate\Validation\Rule;
use Livewire\Component;

class AiFlowForm extends Component
{
    /** @var int|null */
    public $flowId;

    /** @var string */
    public $name = '';

    /** @var string */
    public $key = '';

    /** @var string|null */
    public $description = '';

    /** @var bool */
    public $is_active = true;

    public function mount(?int $flowId = null): void
    {
        $this->authorizeAdmin();
        $this->flowId = $flowId;

        if ($flowId) {
            $flow = AiFlow::query()->findOrFail($flowId);
            $this->name = (string) $flow->name;
            $this->key = (string) $flow->key;
            $this->description = (string) $flow->description;
            $this->is_active = (bool) $flow->is_active;
        }
    }

    public function save(): void
    {
        $this->authorizeAdmin();
        $validated = $this->validate($this->rules(), $this->messages());

        if ($this->flowId) {
            $flow = AiFlow::query()->findOrFail($this->flowId);
            $flow->forceFill([
                'name' => $validated['name'],
                'description' => $validated['description'] ?? null,
                'is_active' => (bool) $validated['is_active'],
            ])->save();

            session()->flash('ai_flow_form_success', 'Flujo IA actualizado correctamente.');

            return;
        }

        $flow = AiFlow::query()->create([
            'name' => $validated['name'],
            'key' => $validated['key'],
            'description' => $validated['description'] ?? null,
            'is_active' => (bool) $validated['is_active'],
            'created_by' => auth()->id(),
        ]);

        $this->flowId = (int) $flow->id;
        session()->flash('ai_flow_form_success', 'Flujo IA creado correctamente.');
    }

    public function render()
    {
        return view('livewire.admin.ai-flows.ai-flow-form', [
            'isEditing' => $this->flowId !== null,
        ]);
    }

    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:180'],
            'key' => [
                'required',
                'string',
                'max:120',
                'regex:/^[a-z][a-z0-9_-]*$/',
                Rule::unique('ai_flows', 'key')->ignore($this->flowId),
            ],
            'description' => ['nullable', 'string'],
            'is_active' => ['boolean'],
        ];
    }

    private function messages(): array
    {
        return [
            'name.required' => 'El nombre del flujo es obligatorio.',
            'name.max' => 'El nombre no debe superar 180 caracteres.',
            'key.required' => 'La clave del flujo es obligatoria.',
            'key.unique' => 'Ya existe un flujo con esta clave.',
            'key.regex' => 'La clave debe estar en minúsculas, sin espacios ni tildes. Puede usar guion medio o guion bajo.',
            'key.max' => 'La clave no debe superar 120 caracteres.',
            'description.string' => 'La descripción debe ser texto.',
            'is_active.boolean' => 'El estado activo no es válido.',
        ];
    }

    private function authorizeAdmin(): void
    {
        abort_unless(auth()->check() && auth()->user()->isAdmin(), 403);
    }
}
