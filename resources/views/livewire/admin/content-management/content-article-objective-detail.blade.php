<div class="space-y-6">
    @if (session()->has('content_objective_success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
            {{ session('content_objective_success') }}
        </div>
    @endif

    @if ($templateConfigurationMessage)
        <div class="rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 shadow-sm">
            {{ $templateConfigurationMessage }}
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Detalle operativo del articulo</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Resumen del articulo y avance general del flujo de contenidos.
                </p>
            </div>

            <div class="flex flex-wrap gap-2 text-xs">
                <span class="inline-flex rounded-full border border-yellow-200 bg-yellow-100 px-3 py-1 font-semibold text-yellow-800">
                    Estado principal: {{ \App\Support\ContentManagementLabels::mainStatus($article->main_status) }}
                </span>
                <span class="inline-flex rounded-full border border-blue-200 bg-blue-100 px-3 py-1 font-semibold text-blue-800">
                    Etapa: {{ \App\Support\ContentManagementLabels::operationalStage($article->operational_stage) }}
                </span>
            </div>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-4 md:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Empresa</p>
                <p class="mt-1 text-sm text-gray-900">{{ optional(optional($article->contentImport)->empresa)->nombre ?: '-' }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Fecha</p>
                <p class="mt-1 text-sm text-gray-900">{{ optional($article->article_date)->format('Y-m-d') ?: '-' }}</p>
            </div>

            <div class="md:col-span-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Tema</p>
                <p class="mt-1 text-sm text-gray-900">{{ $article->topic }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Objetivo estrategico general</p>
                <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $article->strategic_objective_general }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">Publico objetivo general</p>
                <p class="mt-1 text-sm text-gray-900 whitespace-pre-line">{{ $article->target_audience_general }}</p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-gray-900">Paso 1 &middot; Definir objetivo y público</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Genera el primer prompt, registra los resultados refinados y marca el paso como listo cuando ambos campos estén completos.
                </p>
            </div>

            <span class="inline-flex w-fit rounded-full border border-gray-200 bg-gray-100 px-3 py-1 text-xs font-semibold text-gray-800">
                Estado: {{ \App\Support\ContentManagementLabels::stepStatus(optional($objectiveStep)->step_status) }}
            </span>
        </div>

        <div class="mt-6 grid grid-cols-1 gap-6 xl:grid-cols-[minmax(0,1.35fr)_minmax(320px,0.95fr)]">
            <div class="space-y-6">
                <div>
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h4 class="text-sm font-semibold text-gray-900">Prompt 1</h4>
                            <p class="mt-1 text-sm text-gray-500">Cada generación conserva una copia exacta del prompt usado.</p>
                        </div>

                        <div class="flex flex-col gap-2 sm:flex-row">
                            <button
                                type="button"
                                onclick="navigator.clipboard.writeText(document.getElementById('content_objective_prompt_preview').value)"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Copiar prompt
                            </button>

                            <button
                                type="button"
                                wire:click="generatePrompt"
                                class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100"
                            >
                                {{ $generations->isEmpty() ? 'Generar Prompt 1' : 'Regenerar Prompt 1' }}
                            </button>
                        </div>
                    </div>

                    <textarea
                        id="content_objective_prompt_preview"
                        rows="14"
                        readonly
                        class="mt-4 w-full rounded-xl border-gray-300 bg-gray-50 text-sm text-gray-800 shadow-sm focus:border-gray-300 focus:ring-0"
                    >{{ optional($selectedGeneration)->final_prompt_text ?: 'Todavia no existe ninguna generacion para este articulo.' }}</textarea>
                </div>

                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <h4 class="text-sm font-semibold text-gray-900">Resultados refinados</h4>
                    <p class="mt-1 text-sm text-gray-500">
                        Estos campos no modifican la información general importada.
                    </p>

                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <div>
                            <label for="content_refined_objective" class="text-sm font-medium text-gray-700">Objetivo refinado</label>
                            <textarea
                                id="content_refined_objective"
                                wire:model.defer="refinedObjective"
                                rows="5"
                                class="mt-2 w-full rounded-xl border-gray-300 text-sm text-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            @error('refinedObjective')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <label for="content_refined_target_audience" class="text-sm font-medium text-gray-700">Publico objetivo refinado</label>
                            <textarea
                                id="content_refined_target_audience"
                                wire:model.defer="refinedTargetAudience"
                                rows="5"
                                class="mt-2 w-full rounded-xl border-gray-300 text-sm text-gray-800 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            ></textarea>
                            @error('refinedTargetAudience')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-4 flex flex-col gap-3 sm:flex-row">
                        <button
                            type="button"
                            wire:click="saveRefinedResults"
                            class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                        >
                            Guardar refinados
                        </button>

                        <button
                            type="button"
                            wire:click="markObjectiveReady"
                            class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100"
                        >
                            Marcar paso como listo
                        </button>
                    </div>
                </div>
            </div>

            <div class="space-y-6">
                @if($objectiveStep)
                    <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                        <h4 class="text-sm font-semibold text-gray-900">Estado del paso</h4>

                        <dl class="mt-4 space-y-3 text-sm text-gray-700">
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-medium text-gray-600">Estado</dt>
                                <dd class="text-right">{{ \App\Support\ContentManagementLabels::stepStatus($objectiveStep->step_status) }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-medium text-gray-600">Marcado por</dt>
                                <dd class="text-right">{{ optional($objectiveStep->readyBy)->name ?: '-' }}</dd>
                            </div>
                            <div class="flex items-start justify-between gap-4">
                                <dt class="font-medium text-gray-600">Fecha</dt>
                                <dd class="text-right">{{ optional($objectiveStep->ready_at)->format('Y-m-d H:i') ?: '-' }}</dd>
                            </div>
                        </dl>
                    </div>
                @endif

                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Historial de generaciones</h4>
                    <p class="mt-1 text-sm text-gray-500">Cada clic en generar o regenerar crea una fila nueva.</p>

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
                                            Plantilla: {{ \App\Support\ContentManagementLabels::stepType(optional(optional($generation->templateVersion)->masterTemplate)->key ?: \App\Models\ContentArticleStep::TYPE_OBJECTIVE) }}
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

    <div>
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleDraftingPanel::class, ['articleId' => $article->id], key('content-drafting-' . $article->id))
    </div>

    <div>
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleVideoInstagramPanel::class, ['articleId' => $article->id], key('content-video-instagram-' . $article->id))
    </div>

    <div>
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleFinalFilePanel::class, ['articleId' => $article->id], key('content-final-file-' . $article->id))
    </div>

    <div>
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->id], key('content-delivery-publication-' . $article->id))
    </div>
</div>
