<div class="space-y-6">
    @if (session()->has('content_video_instagram_success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
            {{ session('content_video_instagram_success') }}
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Paso 3 &middot; Crear contenido para video e Instagram</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Prepara el prompt para guion de video y piezas de Instagram a partir del documento final del articulo.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
                @if(! $availability['allowed'])
                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-100 px-3 py-1 font-semibold text-amber-800">
                        Estado: Bloqueado
                    </span>
                @else
                    <span class="inline-flex rounded-full border border-pink-200 bg-pink-100 px-3 py-1 font-semibold text-pink-800">
                        Estado: {{ \App\Support\ContentManagementLabels::stepStatus(optional($videoStep)->step_status) }}
                    </span>
                @endif
            </div>
        </div>

        <div class="mt-5 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            <span class="font-semibold">Antes de usar este prompt:</span> {{ $attachmentInstruction }}
        </div>

        @if (! $availability['allowed'])
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span class="font-semibold">Bloqueado:</span> {{ $availability['message'] }}
            </div>
        @endif

        @error('video_instagram')
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                {{ $message }}
            </div>
        @enderror

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)]">
            <div class="space-y-6">
                <div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Prompt 3</h4>
                            <p class="mt-1 text-sm text-gray-500">Cada generación se guarda de forma independiente.</p>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('content_video_instagram_prompt_preview').value)"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Copiar prompt
                            </button>

                            <button
                                type="button"
                                wire:click="generatePrompt"
                                @if(! $availability['allowed']) disabled @endif
                                class="inline-flex items-center justify-center rounded-md border border-pink-200 bg-pink-50 px-4 py-2 text-sm font-semibold text-pink-700 shadow-sm hover:bg-pink-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-500"
                            >
                                {{ $generations->isEmpty() ? 'Generar Prompt 3' : 'Regenerar Prompt 3' }}
                            </button>
                        </div>
                    </div>

                    <textarea
                        id="content_video_instagram_prompt_preview"
                        rows="16"
                        readonly
                        class="mt-4 w-full rounded-xl border-gray-300 bg-gray-50 text-sm text-gray-800 shadow-sm focus:border-gray-300 focus:ring-0"
                    >{{ optional($selectedGeneration)->final_prompt_text ?: 'Todavia no existe ninguna generacion de Video e Instagram para este articulo.' }}</textarea>
                </div>

                <div>
                    <button
                        type="button"
                        wire:click="markVideoInstagramReady"
                        class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100"
                    >
                        Marcar paso como listo
                    </button>
                </div>
            </div>

            <div class="space-y-6">
                @if($videoStep)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h4 class="text-sm font-semibold text-gray-900">Estado del paso</h4>

                        <dl class="mt-4 space-y-3 text-sm text-gray-700">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-medium text-gray-600">Estado</dt>
                                <dd class="text-right">{{ \App\Support\ContentManagementLabels::stepStatus($videoStep->step_status) }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-medium text-gray-600">Marcado por</dt>
                                <dd class="text-right">{{ optional($videoStep->readyBy)->name ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-medium text-gray-600">Fecha</dt>
                                <dd class="text-right">{{ optional($videoStep->ready_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Historial de generaciones</h4>
                    <p class="mt-1 text-sm text-gray-500">Se conserva el historial completo sin sobrescritura.</p>

                    <div class="mt-4 space-y-3">
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
                                            Plantilla: {{ \App\Support\ContentManagementLabels::stepType(optional(optional($generation->templateVersion)->masterTemplate)->key ?: \App\Models\ContentArticleStep::TYPE_VIDEO_INSTAGRAM) }}
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
                                Aun no hay generaciones para este paso.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
