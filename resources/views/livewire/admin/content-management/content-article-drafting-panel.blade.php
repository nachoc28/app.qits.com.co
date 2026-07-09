<div class="space-y-6">
    @if (session()->has('content_drafting_success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
            {{ session('content_drafting_success') }}
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Paso 2 - Drafting</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Genera el Prompt 2 usando la plantilla activa de drafting y la URL principal del sitio de la empresa.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex rounded-full border border-indigo-200 bg-indigo-100 px-3 py-1 font-semibold text-indigo-800">
                    Paso drafting: {{ optional($draftingStep)->step_status ?? 'missing' }}
                </span>
                <span class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-3 py-1 font-semibold text-gray-800">
                    Site URL: {{ $availability['site_url'] ?: 'no configurado' }}
                </span>
            </div>
        </div>

        @if (! $availability['allowed'])
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $availability['message'] }}
            </div>
        @endif

        @error('drafting')
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $message }}
            </div>
        @enderror
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)]">
        <div class="space-y-6">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h3 class="text-base font-semibold text-gray-900">Generacion de Prompt 2</h3>
                        <p class="mt-1 text-sm text-gray-500">
                            Cada generacion o regeneracion guarda una fila independiente en el historial de drafting.
                        </p>
                    </div>

                    <button
                        type="button"
                        wire:click="generatePrompt"
                        @if(! $availability['allowed']) disabled @endif
                        class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-500"
                    >
                        {{ $generations->isEmpty() ? 'Generar Prompt 2' : 'Regenerar Prompt 2' }}
                    </button>
                </div>

                <div class="mt-5">
                    <label for="content_drafting_prompt_preview" class="text-sm font-medium text-gray-700">Prompt seleccionado</label>
                    <textarea
                        id="content_drafting_prompt_preview"
                        rows="18"
                        readonly
                        class="mt-2 w-full rounded-xl border-gray-300 bg-gray-50 text-sm text-gray-800 shadow-sm focus:border-gray-300 focus:ring-0"
                    >{{ optional($selectedGeneration)->final_prompt_text ?: 'Todavia no existe ninguna generacion drafting para este articulo.' }}</textarea>
                </div>

                <div class="mt-5">
                    <button
                        type="button"
                        wire:click="markDraftingReady"
                        class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100"
                    >
                        Marcar paso 2 como listo
                    </button>
                </div>
            </div>
        </div>

        <div class="space-y-6">
            <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                <h3 class="text-base font-semibold text-gray-900">Historial drafting</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Se conserva el historial completo sin sobrescritura.
                </p>

                <div class="mt-5 space-y-3">
                    @forelse($generations as $generation)
                        <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900">
                                        {{ optional($generation->generated_at)->format('Y-m-d H:i') ?: '-' }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-600">
                                        Usuario: {{ optional($generation->generatedBy)->name ?: 'Sistema' }}
                                    </div>
                                    <div class="mt-1 text-xs text-gray-600">
                                        Plantilla: {{ optional(optional($generation->templateVersion)->masterTemplate)->key ?: 'drafting' }}
                                        | Version: {{ optional($generation->templateVersion)->version_number ?: '-' }}
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="viewGeneration({{ $generation->id }})"
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                >
                                    Ver prompt
                                </button>
                            </div>
                        </div>
                    @empty
                        <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500">
                            Aun no hay generaciones para el paso drafting.
                        </div>
                    @endforelse
                </div>
            </div>

            @if($draftingStep)
                <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
                    <h3 class="text-base font-semibold text-gray-900">Estado del paso drafting</h3>

                    <dl class="mt-5 space-y-3 text-sm text-gray-700">
                        <div class="flex items-start justify-between gap-4">
                            <dt class="font-medium text-gray-600">Estado</dt>
                            <dd class="text-right">{{ $draftingStep->step_status }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="font-medium text-gray-600">Ready por</dt>
                            <dd class="text-right">{{ optional($draftingStep->readyBy)->name ?: '-' }}</dd>
                        </div>
                        <div class="flex items-start justify-between gap-4">
                            <dt class="font-medium text-gray-600">Ready at</dt>
                            <dd class="text-right">{{ optional($draftingStep->ready_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                        </div>
                    </dl>
                </div>
            @endif
        </div>
    </div>
</div>
