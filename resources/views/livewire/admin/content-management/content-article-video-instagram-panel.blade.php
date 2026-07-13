<div class="space-y-6">
    <div class="rounded-2xl border border-indigo-100 bg-indigo-50 p-6 shadow-sm ring-1 ring-indigo-100">
        @php
            $videoStatus = optional($videoStep)->step_status;
            $videoBadgeClass = ! $availability['allowed']
                ? 'border-amber-300 bg-amber-50 text-amber-800'
                : match ($videoStatus) {
                    \App\Models\ContentArticleStep::STATUS_READY => 'border-emerald-300 bg-emerald-50 text-emerald-800',
                    \App\Models\ContentArticleStep::STATUS_IN_PROGRESS => 'border-blue-300 bg-blue-50 text-blue-800',
                    default => 'border-slate-300 bg-slate-50 text-slate-700',
                };
            $videoBadgeLabel = ! $availability['allowed']
                ? 'Bloqueado'
                : \App\Support\ContentManagementLabels::stepStatus(optional($videoStep)->step_status);
        @endphp
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="border-l-4 border-blue-500 pl-4 [&>h3]:!font-bold [&>h3]:!tracking-tight [&>h3]:!text-blue-800">
                <h3 class="text-lg font-semibold text-gray-900">Paso 3 &middot; Crear contenido para video e Instagram</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Prepara el prompt para guion de video y piezas de Instagram a partir del documento final del articulo.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex w-fit items-center rounded-full border px-3 py-1.5 text-sm font-semibold leading-none shadow-sm {{ $videoBadgeClass }}">
                    Estado: {{ $videoBadgeLabel }}
                </span>
            </div>
        </div>

        @if (! $availability['allowed'])
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span class="font-semibold">Bloqueado:</span> {{ $availability['message'] }}
            </div>
        @endif

        @error('video_instagram')
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span class="font-semibold">Error:</span> {{ $message }}
            </div>
        @enderror

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)]">
            <div class="space-y-6">
                <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <p class="font-semibold">GPT recomendado</p>
                    <p class="mt-1 font-mono text-sm font-semibold text-blue-800">@StorytellingCorporativo</p>
                    <ol class="mt-3 list-decimal space-y-1 pl-5 text-blue-800">
                        <li>Abre este GPT en ChatGPT.</li>
                        <li>Adjunta primero el documento final del artículo en Word o PDF.</li>
                        <li>Pega el prompt generado.</li>
                        <li>Ejecuta la consulta.</li>
                    </ol>
                </div>

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
                                wire:loading.attr="disabled"
                                wire:target="generatePrompt"
                                @if(! $availability['allowed']) disabled @endif
                                class="inline-flex items-center justify-center rounded-md border border-pink-200 bg-pink-50 px-4 py-2 text-sm font-semibold text-pink-700 shadow-sm hover:bg-pink-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-500"
                            >
                                <span wire:loading.remove wire:target="generatePrompt">{{ $generations->isEmpty() ? 'Generar Prompt 3' : 'Regenerar Prompt 3' }}</span>
                                <span wire:loading.flex wire:target="generatePrompt" style="display: none;" class="items-center gap-2">
                                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                    </svg>
                                    Generando prompt...
                                </span>
                            </button>
                        </div>
                    </div>

                    @if (session()->has('content_video_instagram_prompt_success'))
                        <div class="mt-3 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            <span class="font-semibold">Exito:</span> {{ session('content_video_instagram_prompt_success') }}
                        </div>
                    @endif

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
                        wire:loading.attr="disabled"
                        wire:target="markVideoInstagramReady"
                        class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100"
                    >
                        <span wire:loading.remove wire:target="markVideoInstagramReady">Marcar paso como listo</span>
                        <span wire:loading.flex wire:target="markVideoInstagramReady" style="display: none;" class="items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Procesando...
                        </span>
                    </button>

                    @if (session()->has('content_video_instagram_ready_success'))
                        <div class="mt-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                            <span class="font-semibold">Exito:</span> {{ session('content_video_instagram_ready_success') }}
                        </div>
                    @endif
                </div>
            </div>

            <div class="space-y-6">
                @if($videoStep)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h4 class="text-sm font-semibold text-gray-900">Estado del paso</h4>

                        <dl class="mt-4 space-y-3 text-sm text-gray-700">
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

                <details class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-gray-900 marker:hidden">
                        <span>Historial de generaciones ({{ $generations->count() }})</span>
                        <span class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-semibold text-gray-600">Abrir</span>
                    </summary>
                    <p class="mt-2 text-sm text-gray-500">Se conserva el historial completo sin sobrescritura.</p>

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
                </details>
            </div>
        </div>
    </div>
</div>
