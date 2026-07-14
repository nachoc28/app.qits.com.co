<div class="space-y-6">
    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm ring-1 ring-cyan-100 sm:p-6">
        <h3 class="text-base font-semibold text-cyan-900">Datos de la ejecución</h3>
        <p class="mt-1 text-sm text-cyan-800">
            Solo aparecen flujos activos con una versión publicada disponible.
        </p>
    </div>

    @if($formError)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <span class="font-semibold">Error:</span> {{ $formError }}
        </div>
    @endif

    <form wire:submit.prevent="createExecution" class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <label for="ai_flow_execution_empresa" class="text-sm font-medium text-gray-700">Empresa</label>
                <select
                    id="ai_flow_execution_empresa"
                    wire:model.defer="empresa_id"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Selecciona una empresa</option>
                    @foreach($empresas as $empresa)
                        <option value="{{ $empresa->id }}">{{ $empresa->nombre }}</option>
                    @endforeach
                </select>
                @error('empresa_id')
                    <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="ai_flow_execution_flow" class="text-sm font-medium text-gray-700">Flujo publicado</label>
                <select
                    id="ai_flow_execution_flow"
                    wire:model.defer="ai_flow_id"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                >
                    <option value="">Selecciona un flujo</option>
                    @foreach($flows as $flow)
                        @php($publishedVersion = $flow->versions->first())
                        <option value="{{ $flow->id }}">
                            {{ $flow->name }} @if($publishedVersion) · Versión {{ $publishedVersion->version_number }} @endif
                        </option>
                    @endforeach
                </select>
                @error('ai_flow_id')
                    <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                @enderror
            </div>

            <div class="md:col-span-2">
                <label for="ai_flow_execution_title" class="text-sm font-medium text-gray-700">Título de la ejecución</label>
                <input
                    id="ai_flow_execution_title"
                    type="text"
                    wire:model.defer="title"
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    placeholder="Investigación de mercado - Cliente"
                >
                @error('title')
                    <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                @enderror
            </div>
        </div>

        @if($flows->isEmpty())
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span class="font-semibold">Aviso:</span> No hay flujos activos con versión publicada para iniciar ejecuciones.
            </div>
        @endif

        <div class="mt-6 flex justify-end">
            <button
                type="submit"
                wire:loading.attr="disabled"
                wire:target="createExecution"
                class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="createExecution">Crear ejecución</span>
                <span wire:loading.flex wire:target="createExecution" style="display: none;" class="items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Creando...
                </span>
            </button>
        </div>
    </form>
</div>
