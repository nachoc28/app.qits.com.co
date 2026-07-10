<div class="space-y-6">
    <nav
        class="sticky top-0 z-20 -mx-4 border-y border-emerald-100 bg-white/95 px-4 py-3 shadow-sm backdrop-blur sm:mx-0 sm:rounded-2xl sm:border"
        aria-label="Navegacion del flujo de gestion de contenidos"
    >
        <div class="flex gap-2 overflow-x-auto pb-1">
            @foreach($stepperSteps as $index => $stepperStep)
                @php
                    $stepperThemeClass = match ($stepperStep['theme']) {
                        'emerald' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
                        'blue' => 'border-blue-200 bg-blue-50 text-blue-800',
                        'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
                        default => 'border-slate-200 bg-slate-50 text-slate-700',
                    };
                @endphp

                <a
                    href="#{{ $stepperStep['target'] }}"
                    class="group flex min-w-max items-center gap-3 rounded-xl border border-gray-200 bg-white px-3 py-2 text-left text-sm shadow-sm transition hover:border-emerald-300 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2"
                >
                    <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-emerald-700 text-xs font-bold text-white">
                        {{ $index + 1 }}
                    </span>
                    <span class="flex flex-col leading-tight">
                        <span class="font-semibold text-gray-900">{{ $stepperStep['label'] }}</span>
                        <span class="mt-1 inline-flex w-fit items-center rounded-full border px-2 py-0.5 text-xs font-semibold {{ $stepperThemeClass }}">
                            <span class="mr-1" aria-hidden="true">&bull;</span>{{ $stepperStep['status'] }}
                        </span>
                    </span>
                </a>
            @endforeach
        </div>
    </nav>

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

    <div id="content-step-objective" class="scroll-mt-32 rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        @php
            $objectiveStatus = optional($objectiveStep)->step_status;
            $objectiveBadgeClass = match ($objectiveStatus) {
                \App\Models\ContentArticleStep::STATUS_READY => 'border-emerald-300 bg-emerald-50 text-emerald-800',
                \App\Models\ContentArticleStep::STATUS_IN_PROGRESS => 'border-blue-300 bg-blue-50 text-blue-800',
                default => 'border-slate-300 bg-slate-50 text-slate-700',
            };
        @endphp

        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="border-l-4 border-emerald-500 pl-4 [&>h3]:!font-bold [&>h3]:!tracking-tight [&>h3]:!text-emerald-800">
                <h3 class="text-lg font-semibold text-gray-900">Paso 1 &middot; Definir objetivo y público</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Genera el primer prompt, registra los resultados refinados y marca el paso como listo cuando ambos campos estén completos.
                </p>
            </div>

            <span class="inline-flex w-fit items-center rounded-full border px-3 py-1.5 text-sm font-semibold leading-none shadow-sm {{ $objectiveBadgeClass }}">
                Estado: {{ \App\Support\ContentManagementLabels::stepStatus(optional($objectiveStep)->step_status) }}
            </span>
        </div>

        @if (session()->has('content_objective_success'))
            <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <span class="font-semibold">Exito:</span> {{ session('content_objective_success') }}
            </div>
        @endif

        @if ($templateConfigurationMessage)
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span class="font-semibold">Error:</span> {{ $templateConfigurationMessage }}
            </div>
        @endif

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

                    <div class="mt-3 rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                            <div>
                                <p class="font-semibold">GPT recomendado</p>
                                <p class="mt-1 font-mono text-sm font-semibold text-blue-800">@consultormarketingdigital</p>
                            </div>
                            <p class="max-w-xl text-blue-800">
                                Abre este GPT en ChatGPT, pega el prompt generado y ejecuta la consulta.
                            </p>
                        </div>
                    </div>
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

                <details class="rounded-xl border border-gray-200 bg-white p-4 shadow-sm">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-3 text-sm font-semibold text-gray-900 marker:hidden">
                        <span>Historial de generaciones ({{ $generations->count() }})</span>
                        <span class="rounded-full border border-gray-200 bg-gray-50 px-2 py-0.5 text-xs font-semibold text-gray-600">Abrir</span>
                    </summary>
                    <p class="mt-2 text-sm text-gray-500">Cada clic en generar o regenerar crea una fila nueva.</p>

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
                </details>
            </div>
        </div>
    </div>

    <div id="content-step-drafting" class="scroll-mt-32">
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleDraftingPanel::class, ['articleId' => $article->id], key('content-drafting-' . $article->id))
    </div>

    <div id="content-step-video-instagram" class="scroll-mt-32">
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleVideoInstagramPanel::class, ['articleId' => $article->id], key('content-video-instagram-' . $article->id))
    </div>

    <div id="content-step-final-file" class="scroll-mt-32">
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleFinalFilePanel::class, ['articleId' => $article->id], key('content-final-file-' . $article->id))
    </div>

    <div id="content-step-release" class="scroll-mt-32">
        @livewire(\App\Http\Livewire\Admin\ContentManagement\ContentArticleDeliveryPublicationPanel::class, ['articleId' => $article->id], key('content-delivery-publication-' . $article->id))
    </div>
</div>
