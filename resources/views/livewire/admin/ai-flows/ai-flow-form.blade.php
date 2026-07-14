<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
    @if (session()->has('ai_flow_form_success'))
        <div class="mb-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <span class="font-semibold">Éxito:</span> {{ session('ai_flow_form_success') }}
        </div>
    @endif

    <form wire:submit.prevent="save" class="space-y-5">
        <div>
            <label for="ai_flow_name" class="text-sm font-medium text-gray-700">Nombre</label>
            <input
                id="ai_flow_name"
                type="text"
                wire:model.defer="name"
                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            @error('name')
                <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ai_flow_key" class="text-sm font-medium text-gray-700">Key</label>
            <input
                id="ai_flow_key"
                type="text"
                wire:model.defer="key"
                @if($isEditing) disabled @endif
                placeholder="investigacion_mercado"
                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
            >
            <p class="mt-1 text-xs text-gray-500">Use minúsculas, sin espacios ni tildes. Se permite guion medio o guion bajo.</p>
            @error('key')
                <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
            @enderror
        </div>

        <div>
            <label for="ai_flow_description" class="text-sm font-medium text-gray-700">Descripción</label>
            <textarea
                id="ai_flow_description"
                rows="4"
                wire:model.defer="description"
                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            ></textarea>
            @error('description')
                <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
            @enderror
        </div>

        <label class="flex items-center gap-3">
            <input
                type="checkbox"
                wire:model.defer="is_active"
                class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            >
            <span class="text-sm font-medium text-gray-700">Flujo activo</span>
        </label>

        <div class="flex justify-end">
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="save"
                class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="save">{{ $isEditing ? 'Guardar cambios' : 'Crear flujo' }}</span>
                <span wire:loading.flex wire:target="save" style="display: none;" class="items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Guardando...
                </span>
            </button>
        </div>
    </form>
</div>
