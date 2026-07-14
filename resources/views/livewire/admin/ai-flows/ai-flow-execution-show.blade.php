<div class="space-y-6">
    @if (session()->has('ai_flow_execution_success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <span class="font-semibold">Exito:</span> {{ session('ai_flow_execution_success') }}
        </div>
    @endif

    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm ring-1 ring-cyan-100 sm:p-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h3 class="text-lg font-semibold text-cyan-900">{{ $execution->title }}</h3>
                <p class="mt-1 text-sm text-cyan-800">
                    {{ optional($execution->empresa)->nombre ?: '-' }} - {{ optional($execution->flow)->name ?: '-' }}
                </p>
            </div>

            <div class="rounded-xl border border-cyan-300 bg-white/80 px-4 py-3 text-sm text-cyan-900">
                <span class="font-semibold">Progreso:</span> {{ $completedSteps }} / {{ $totalSteps }} etapas - {{ $progressPercent }}%
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <dl class="grid grid-cols-1 gap-4 text-sm md:grid-cols-3">
            <div>
                <dt class="font-medium text-gray-500">Empresa</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ optional($execution->empresa)->nombre ?: '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Flujo</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ optional($execution->flow)->name ?: '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Version</dt>
                <dd class="mt-1 font-semibold text-gray-900">
                    @if($execution->version)
                        Version {{ $execution->version->version_number }}
                    @else
                        -
                    @endif
                </dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Estado general</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $executionStatusLabel }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Fecha de inicio</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ optional($execution->started_at)->format('Y-m-d H:i') ?: '-' }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Usuario creador</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ optional($execution->startedBy)->name ?: '-' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <h4 class="font-semibold text-gray-900">Avance por etapas</h4>
        <p class="mt-1 text-sm text-gray-500">
            El estado Bloqueada es visual y se calcula desde dependencias explicitas o secuencia por posicion.
        </p>

        <div class="mt-5 grid grid-cols-1 gap-4 lg:grid-cols-2">
            @forelse($stepRows as $row)
                @php($executionStep = $row['execution_step'])
                @php($step = $executionStep->step)
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                Etapa {{ optional($step)->position ?: '-' }}
                            </p>
                            <h5 class="mt-1 font-semibold text-gray-900">{{ optional($step)->name ?: 'Etapa no disponible' }}</h5>
                        </div>

                        @if($row['visual_status'] === 'blocked')
                            <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">Bloqueada</span>
                        @elseif($row['visual_status'] === \App\Models\AiFlowExecutionStep::STATUS_COMPLETED)
                            <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">Completada</span>
                        @elseif($row['visual_status'] === \App\Models\AiFlowExecutionStep::STATUS_IN_PROGRESS)
                            <span class="inline-flex rounded-full border border-blue-200 bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-800">En proceso</span>
                        @else
                            <span class="inline-flex rounded-full border border-gray-200 bg-white px-2.5 py-1 text-xs font-semibold text-gray-700">Pendiente</span>
                        @endif
                    </div>

                    <dl class="mt-4 grid grid-cols-1 gap-3 text-sm sm:grid-cols-2">
                        <div>
                            <dt class="font-medium text-gray-500">GPT recomendado</dt>
                            <dd class="mt-1 text-gray-900">{{ optional($step)->recommended_gpt ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Salida esperada</dt>
                            <dd class="mt-1 text-gray-900">{{ optional($step)->expected_output_name ?: '-' }}</dd>
                        </div>
                        <div>
                            <dt class="font-medium text-gray-500">Estado visual</dt>
                            <dd class="mt-1 font-semibold text-gray-900">{{ $row['visual_label'] }}</dd>
                        </div>
                    </dl>

                    @if(isset($stepMessages[$executionStep->id]))
                        <div class="mt-4 rounded-lg border border-green-200 bg-green-50 px-3 py-2 text-sm text-green-800">
                            <span class="font-semibold">Exito:</span> {{ $stepMessages[$executionStep->id] }}
                        </div>
                    @endif

                    @if(isset($stepErrors[$executionStep->id]))
                        <div class="mt-4 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                            <span class="font-semibold">Error:</span> {{ $stepErrors[$executionStep->id] }}
                        </div>
                    @endif

                    @if($row['is_blocked'])
                        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                            Esta etapa está bloqueada hasta completar las etapas requeridas.
                        </div>
                    @else
                        <div class="mt-5 rounded-lg border border-white bg-white p-4 shadow-sm">
                            <h6 class="font-semibold text-gray-900">Variables de esta etapa</h6>

                            @if($row['prompt_has_unconfigured_variables'])
                                <div class="mt-3 rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700">
                                    El prompt usa variables que no estan configuradas en la version.
                                </div>
                            @endif

                            @if(count($row['prompt_variables']) > 0)
                                <div class="mt-4 space-y-4">
                                    @foreach($row['prompt_variables'] as $variable)
                                        <div>
                                            <label class="text-sm font-medium text-gray-700" for="ai_flow_value_{{ $executionStep->id }}_{{ $variable->id }}">
                                                {{ $variable->label }}
                                                @if($variable->is_required)
                                                    <span class="text-red-600">*</span>
                                                @endif
                                            </label>

                                            @if($variable->help_text)
                                                <p class="mt-1 text-xs text-gray-500">{{ $variable->help_text }}</p>
                                            @endif

                                            @if($variable->scope === \App\Models\AiFlowVariable::SCOPE_OUTPUT)
                                                <div class="mt-2 rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">
                                                    Esta variable viene del resultado de una etapa anterior y estara disponible cuando exista un resultado guardado.
                                                </div>
                                            @elseif($variable->input_type === \App\Models\AiFlowVariable::INPUT_TYPE_TEXTAREA)
                                                <textarea
                                                    id="ai_flow_value_{{ $executionStep->id }}_{{ $variable->id }}"
                                                    rows="4"
                                                    wire:model.defer="variableValues.{{ $executionStep->id }}.{{ $variable->id }}"
                                                    placeholder="{{ $variable->placeholder }}"
                                                    class="mt-2 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                ></textarea>
                                            @else
                                                <input
                                                    id="ai_flow_value_{{ $executionStep->id }}_{{ $variable->id }}"
                                                    type="text"
                                                    wire:model.defer="variableValues.{{ $executionStep->id }}.{{ $variable->id }}"
                                                    placeholder="{{ $variable->placeholder }}"
                                                    class="mt-2 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                                >
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-600">
                                    Esta etapa no tiene variables configuradas para diligenciar.
                                </div>
                            @endif

                            <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                                @if(count($row['prompt_variables']) > 0)
                                    <button
                                        type="button"
                                        wire:click="saveVariables({{ $executionStep->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="saveVariables({{ $executionStep->id }})"
                                        class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-100 disabled:opacity-60"
                                    >
                                        <span wire:loading.remove wire:target="saveVariables({{ $executionStep->id }})">Guardar variables</span>
                                        <span wire:loading.flex wire:target="saveVariables({{ $executionStep->id }})" style="display: none;">Guardando...</span>
                                    </button>
                                @endif

                                <button
                                    type="button"
                                    wire:click="generatePrompt({{ $executionStep->id }})"
                                    wire:loading.attr="disabled"
                                    wire:target="generatePrompt({{ $executionStep->id }})"
                                    class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:opacity-60"
                                >
                                    <span wire:loading.remove wire:target="generatePrompt({{ $executionStep->id }})">{{ $row['latest_generation'] ? 'Regenerar prompt' : 'Generar prompt' }}</span>
                                    <span wire:loading.flex wire:target="generatePrompt({{ $executionStep->id }})" style="display: none;">Generando...</span>
                                </button>
                            </div>
                        </div>
                    @endif

                    @if($row['latest_generation'])
                        <div class="mt-5 rounded-lg border border-indigo-100 bg-white p-4">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h6 class="font-semibold text-gray-900">Ultimo prompt generado</h6>
                                    <p class="mt-1 text-xs text-gray-500">
                                        {{ optional($row['latest_generation']->generated_at)->format('Y-m-d H:i') ?: '-' }}
                                        - {{ optional($row['latest_generation']->generatedBy)->name ?: '-' }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    x-data
                                    x-on:click="
                                        if (navigator.clipboard) {
                                            navigator.clipboard.writeText($refs.promptText{{ $executionStep->id }}.value);
                                        } else {
                                            $refs.promptText{{ $executionStep->id }}.select();
                                        }
                                        $wire.copyPromptFeedback({{ $executionStep->id }});
                                    "
                                    class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-3 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100"
                                >
                                    Copiar prompt
                                </button>
                            </div>
                            <textarea x-ref="promptText{{ $executionStep->id }}" readonly rows="6" class="mt-3 w-full rounded-md border-gray-300 bg-gray-50 px-3 py-2 font-mono text-xs text-gray-800">{{ $row['latest_generation']->final_prompt_text }}</textarea>

                            @if(! $row['is_blocked'])
                                <div class="mt-5 rounded-lg border border-gray-100 bg-gray-50 p-4">
                                    <label class="text-sm font-semibold text-gray-900" for="ai_flow_result_{{ $executionStep->id }}">
                                        Resultado externo del GPT
                                    </label>
                                    <p class="mt-1 text-xs text-gray-500">Pega aqui la respuesta generada fuera del sistema.</p>
                                    <textarea
                                        id="ai_flow_result_{{ $executionStep->id }}"
                                        rows="5"
                                        wire:model.defer="resultTexts.{{ $executionStep->id }}"
                                        class="mt-3 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    ></textarea>

                                    <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:justify-end">
                                        <button
                                            type="button"
                                            wire:click="saveResult({{ $executionStep->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="saveResult({{ $executionStep->id }})"
                                            class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-100 disabled:opacity-60"
                                        >
                                            <span wire:loading.remove wire:target="saveResult({{ $executionStep->id }})">Guardar resultado</span>
                                            <span wire:loading.flex wire:target="saveResult({{ $executionStep->id }})" style="display: none;">Guardando...</span>
                                        </button>
                                        <button
                                            type="button"
                                            wire:click="completeStep({{ $executionStep->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="completeStep({{ $executionStep->id }})"
                                            class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100 disabled:opacity-60"
                                        >
                                            <span wire:loading.remove wire:target="completeStep({{ $executionStep->id }})">Marcar etapa como completada</span>
                                            <span wire:loading.flex wire:target="completeStep({{ $executionStep->id }})" style="display: none;">Completando...</span>
                                        </button>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif

                    @if($row['latest_result'])
                        <div class="mt-5 rounded-lg border border-green-100 bg-white p-4">
                            <h6 class="font-semibold text-gray-900">Ultimo resultado guardado</h6>
                            <p class="mt-1 text-xs text-gray-500">
                                {{ optional($row['latest_result']->saved_at)->format('Y-m-d H:i') ?: '-' }}
                                - {{ optional($row['latest_result']->savedBy)->name ?: '-' }}
                            </p>
                            <textarea readonly rows="5" class="mt-3 w-full rounded-md border-gray-300 bg-gray-50 px-3 py-2 text-sm text-gray-800">{{ $row['latest_result']->result_text }}</textarea>

                            <div class="mt-4 rounded-lg border border-emerald-100 bg-emerald-50/60 p-4">
                                <h6 class="font-semibold text-gray-900">Marcar como resultado estratégico</h6>
                                <div class="mt-3 grid grid-cols-1 gap-3 md:grid-cols-2">
                                    <div>
                                        <label class="text-sm font-medium text-gray-700" for="ai_flow_strategic_type_{{ $row['latest_result']->id }}">Tipo</label>
                                        <select
                                            id="ai_flow_strategic_type_{{ $row['latest_result']->id }}"
                                            wire:model.defer="strategicOutputTypes.{{ $row['latest_result']->id }}"
                                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                            <option value="strategic_report">Informe estratégico</option>
                                            <option value="executive_summary">Resumen ejecutivo</option>
                                            <option value="current_strategic_base">Base estratégica vigente</option>
                                        </select>
                                    </div>
                                    <div>
                                        <label class="text-sm font-medium text-gray-700" for="ai_flow_strategic_title_{{ $row['latest_result']->id }}">Título</label>
                                        <input
                                            id="ai_flow_strategic_title_{{ $row['latest_result']->id }}"
                                            type="text"
                                            wire:model.defer="strategicOutputTitles.{{ $row['latest_result']->id }}"
                                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        >
                                    </div>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button
                                        type="button"
                                        wire:click="markStrategicOutput({{ $row['latest_result']->id }})"
                                        wire:loading.attr="disabled"
                                        wire:target="markStrategicOutput({{ $row['latest_result']->id }})"
                                        class="inline-flex items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100 disabled:opacity-60"
                                    >
                                        <span wire:loading.remove wire:target="markStrategicOutput({{ $row['latest_result']->id }})">Marcar como resultado estratégico</span>
                                        <span wire:loading.flex wire:target="markStrategicOutput({{ $row['latest_result']->id }})" style="display: none;">Marcando...</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endif

                    <details class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800">
                            Historial de generaciones ({{ $executionStep->generations->count() }})
                        </summary>
                        <div class="mt-3 space-y-3">
                            @forelse($row['previous_generations'] as $generation)
                                <div class="rounded-md border border-gray-100 bg-gray-50 p-3">
                                    <p class="text-xs font-semibold text-gray-600">
                                        {{ optional($generation->generated_at)->format('Y-m-d H:i') ?: '-' }}
                                        - {{ optional($generation->generatedBy)->name ?: '-' }}
                                    </p>
                                    <textarea readonly rows="4" class="mt-2 w-full rounded-md border-gray-300 bg-white px-3 py-2 font-mono text-xs text-gray-800">{{ $generation->final_prompt_text }}</textarea>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No hay generaciones anteriores.</p>
                            @endforelse
                        </div>
                    </details>

                    <details class="mt-4 rounded-lg border border-gray-200 bg-white px-4 py-3">
                        <summary class="cursor-pointer text-sm font-semibold text-gray-800">
                            Historial de resultados ({{ $executionStep->results->count() }})
                        </summary>
                        <div class="mt-3 space-y-3">
                            @forelse($row['previous_results'] as $result)
                                <div class="rounded-md border border-gray-100 bg-gray-50 p-3">
                                    <p class="text-xs font-semibold text-gray-600">
                                        {{ optional($result->saved_at)->format('Y-m-d H:i') ?: '-' }}
                                        - {{ optional($result->savedBy)->name ?: '-' }}
                                    </p>
                                    <textarea readonly rows="4" class="mt-2 w-full rounded-md border-gray-300 bg-white px-3 py-2 text-sm text-gray-800">{{ $result->result_text }}</textarea>
                                </div>
                            @empty
                                <p class="text-sm text-gray-500">No hay resultados anteriores.</p>
                            @endforelse
                        </div>
                    </details>
                </div>
            @empty
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-6 text-center text-sm text-gray-500 lg:col-span-2">
                    Esta ejecucion no tiene etapas inicializadas.
                </div>
            @endforelse
        </div>
    </div>
</div>
