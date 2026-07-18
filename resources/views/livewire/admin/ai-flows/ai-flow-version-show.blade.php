<div class="space-y-6">
    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm ring-1 ring-cyan-100 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-cyan-900">Versión {{ $version->version_number }}</h3>
                <p class="mt-1 text-sm text-cyan-800">
                    Estado actual: <span class="font-semibold">{{ $statusLabel }}</span>
                </p>
            </div>

            @if($version->status === \App\Models\AiFlowVersion::STATUS_DRAFT)
                <button
                    type="button"
                    wire:click="publish"
                    wire:loading.attr="disabled"
                    wire:target="publish"
                    class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-4 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="publish">Publicar versión</span>
                    <span wire:loading.flex wire:target="publish" style="display: none;" class="items-center gap-2">
                        <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                        </svg>
                        Publicando...
                    </span>
                </button>
            @endif
        </div>
    </div>

    @if (session()->has('ai_flow_version_show_success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <span class="font-semibold">Éxito:</span> {{ session('ai_flow_version_show_success') }}
        </div>
    @endif

    @if ($publicationErrors)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Errores de publicación:</p>
            @foreach($publicationErrors as $error)
                <p class="mt-1">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    @if ($publicationWarnings)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            <p class="font-semibold">Advertencias:</p>
            @foreach($publicationWarnings as $warning)
                <p class="mt-1">{{ $warning }}</p>
            @endforeach
        </div>
    @endif

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <dl class="grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
            <div>
                <dt class="font-medium text-gray-500">Flujo</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $flow->name }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Estado</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ $statusLabel }}</dd>
            </div>
            <div>
                <dt class="font-medium text-gray-500">Publicado</dt>
                <dd class="mt-1 font-semibold text-gray-900">{{ optional($version->published_at)->format('Y-m-d H:i') ?: '-' }}</dd>
            </div>
        </dl>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h4 class="font-semibold text-gray-900">{{ $editingStepId ? 'Editar etapa' : 'Crear etapa' }}</h4>
                <p class="mt-1 text-sm text-gray-500">
                    Constructor básico de etapas. La configuración completa de variables se implementará después.
                </p>
            </div>

            @if($editingStepId)
                <button
                    type="button"
                    wire:click="startCreateStep"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Nueva etapa
                </button>
            @endif
        </div>

        @if(! $isDraft)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span class="font-semibold">Aviso:</span> Solo se pueden crear o editar etapas en versiones borrador.
            </div>
        @endif

        @if($stepFormError)
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span class="font-semibold">Error:</span> {{ $stepFormError }}
            </div>
        @endif

        @if (session()->has('ai_flow_step_success'))
            <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <span class="font-semibold">Éxito:</span> {{ session('ai_flow_step_success') }}
            </div>
        @endif

        <form wire:submit.prevent="saveStep" class="mt-5 space-y-5">
            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div>
                    <label for="ai_flow_step_key" class="text-sm font-medium text-gray-700">Key de etapa</label>
                    <input
                        id="ai_flow_step_key"
                        type="text"
                        wire:model.defer="step_key"
                        @if(! $isDraft) disabled @endif
                        placeholder="diagnostico_inicial"
                        class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                    >
                    @error('step_key')
                        <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ai_flow_step_name" class="text-sm font-medium text-gray-700">Nombre</label>
                    <input
                        id="ai_flow_step_name"
                        type="text"
                        wire:model.defer="name"
                        @if(! $isDraft) disabled @endif
                        class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                    >
                    @error('name')
                        <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ai_flow_step_position" class="text-sm font-medium text-gray-700">Posición</label>
                    <input
                        id="ai_flow_step_position"
                        type="number"
                        min="1"
                        wire:model="position"
                        @if(! $isDraft) disabled @endif
                        class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                    >
                    @error('position')
                        <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ai_flow_step_dependency" class="text-sm font-medium text-gray-700">Depende de</label>
                    <select
                        id="ai_flow_step_dependency"
                        wire:model.defer="depends_on_step_id"
                        @if(! $isDraft) disabled @endif
                        class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                    >
                        <option value="">Sin dependencia explícita</option>
                        @foreach($dependencyOptions as $dependencyOption)
                            <option value="{{ $dependencyOption['id'] }}">
                                {{ $dependencyOption['position'] }} · {{ $dependencyOption['name'] }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-gray-500">Solo se permiten etapas anteriores de esta misma versión.</p>
                    @error('depends_on_step_id')
                        <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ai_flow_step_gpt" class="text-sm font-medium text-gray-700">GPT recomendado</label>
                    <input
                        id="ai_flow_step_gpt"
                        type="text"
                        wire:model.defer="recommended_gpt"
                        @if(! $isDraft) disabled @endif
                        placeholder="@GPTRecomendado"
                        class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                    >
                    @error('recommended_gpt')
                        <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="ai_flow_step_output" class="text-sm font-medium text-gray-700">Salida esperada</label>
                    <input
                        id="ai_flow_step_output"
                        type="text"
                        wire:model.defer="expected_output_name"
                        @if(! $isDraft) disabled @endif
                        placeholder="Diagnóstico estratégico"
                        class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                    >
                    @error('expected_output_name')
                        <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                    @enderror
                </div>
            </div>

            <div>
                <label for="ai_flow_step_description" class="text-sm font-medium text-gray-700">Descripción</label>
                <textarea
                    id="ai_flow_step_description"
                    rows="3"
                    wire:model.defer="description"
                    @if(! $isDraft) disabled @endif
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                ></textarea>
                @error('description')
                    <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                @enderror
            </div>

            <div>
                <label for="ai_flow_step_prompt" class="text-sm font-medium text-gray-700">Prompt base</label>
                <textarea
                    id="ai_flow_step_prompt"
                    rows="12"
                    wire:model.debounce.400ms="base_prompt"
                    @if(! $isDraft) disabled @endif
                    placeholder="Escribe el prompt usando variables como @{{pais}} o @{{publico_objetivo}}."
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 font-mono text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                ></textarea>
                @error('base_prompt')
                    <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                @enderror
            </div>

            <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                    <p class="font-semibold">Variables detectadas ({{ count($promptPreview['variables']) }})</p>
                    @if(count($promptPreview['variables']) > 0)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($promptPreview['variables'] as $variableName)
                                <span class="rounded-full border border-blue-200 bg-white px-2.5 py-1 font-mono text-xs text-blue-800">{{ $variableName }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-2 text-blue-800">No hay variables válidas detectadas.</p>
                    @endif
                </div>

                <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    <p class="font-semibold">Tokens inválidos ({{ count($promptPreview['invalid_tokens']) }})</p>
                    @if(count($promptPreview['invalid_tokens']) > 0)
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach($promptPreview['invalid_tokens'] as $invalidToken)
                                <span class="rounded-full border border-red-200 bg-white px-2.5 py-1 font-mono text-xs text-red-800">{{ $invalidToken === '' ? 'vacío' : $invalidToken }}</span>
                            @endforeach
                        </div>
                    @else
                        <p class="mt-2 text-red-700">No hay tokens inválidos.</p>
                    @endif
                </div>
            </div>

            <label class="flex items-center gap-3">
                <input
                    type="checkbox"
                    wire:model.defer="is_active"
                    @if(! $isDraft) disabled @endif
                    class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                >
                <span class="text-sm font-medium text-gray-700">Etapa activa</span>
            </label>

            <div class="flex justify-end">
                <button
                    type="submit"
                    @if(! $isDraft) disabled @endif
                    wire:loading.attr="disabled"
                    wire:target="saveStep"
                    class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="saveStep">{{ $editingStepId ? 'Guardar etapa' : 'Crear etapa' }}</span>
                    <span wire:loading.flex wire:target="saveStep" style="display: none;" class="items-center gap-2">
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

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h4 class="font-semibold text-gray-900">Variables del flujo</h4>
                <p class="mt-1 text-sm text-gray-500">
                    Sincroniza y configura las variables detectadas en los prompts activos de esta versión.
                </p>
            </div>

            <button
                type="button"
                wire:click="syncVariables"
                wire:loading.attr="disabled"
                wire:target="syncVariables"
                @if(! $isDraft) disabled @endif
                class="inline-flex items-center justify-center rounded-md border border-blue-200 bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 shadow-sm hover:bg-blue-100 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="syncVariables">Sincronizar variables detectadas</span>
                <span wire:loading.flex wire:target="syncVariables" style="display: none;" class="items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Sincronizando...
                </span>
            </button>
        </div>

        @if(! $isDraft)
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                <span class="font-semibold">Aviso:</span> Las variables de versiones publicadas o archivadas se muestran en solo lectura.
            </div>
        @endif

        @if($variableFormError)
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                <span class="font-semibold">Error:</span> {{ $variableFormError }}
            </div>
        @endif

        @if (session()->has('ai_flow_variable_success'))
            <div class="mt-5 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
                <span class="font-semibold">Éxito:</span> {{ session('ai_flow_variable_success') }}
            </div>
        @endif

        @if(count($versionInvalidTokens) > 0)
            <div class="mt-5 rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Tokens inválidos detectados en prompts activos:</p>
                <div class="mt-2 flex flex-wrap gap-2">
                    @foreach($versionInvalidTokens as $invalidToken)
                        <span class="rounded-full border border-red-200 bg-white px-2.5 py-1 font-mono text-xs text-red-800">{{ $invalidToken === '' ? 'vacío' : $invalidToken }}</span>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-3">
            <div class="rounded-xl border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-900">
                <p class="font-semibold">Detectadas en prompts activos</p>
                <p class="mt-1 text-2xl font-bold">{{ count($detectedVersionVariables) }}</p>
            </div>
            <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-900">
                <p class="font-semibold">Configuradas</p>
                <p class="mt-1 text-2xl font-bold">{{ $variables->count() }}</p>
            </div>
            <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                <p class="font-semibold">No usadas</p>
                <p class="mt-1 text-2xl font-bold">{{ collect($variableRows)->where('is_used', false)->count() }}</p>
            </div>
        </div>

        @if($editingVariableId)
            <form wire:submit.prevent="saveVariable" class="mt-5 rounded-xl border border-indigo-100 bg-indigo-50/40 p-4">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h5 class="font-semibold text-gray-900">Configurar variable</h5>
                        <p class="mt-1 text-sm text-gray-600">
                            Nombre interno: <span class="font-mono font-semibold">{{ $variable_name }}</span>
                        </p>
                    </div>
                    <button
                        type="button"
                        wire:click="resetVariableForm"
                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                    >
                        Cancelar
                    </button>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="ai_flow_variable_label" class="text-sm font-medium text-gray-700">Etiqueta</label>
                        <input
                            id="ai_flow_variable_label"
                            type="text"
                            wire:model.defer="variable_label"
                            @if(! $isDraft) disabled @endif
                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                        >
                        @error('variable_label')
                            <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ai_flow_variable_position" class="text-sm font-medium text-gray-700">Posición</label>
                        <input
                            id="ai_flow_variable_position"
                            type="number"
                            min="1"
                            wire:model.defer="variable_position"
                            @if(! $isDraft) disabled @endif
                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                        >
                        @error('variable_position')
                            <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ai_flow_variable_input_type" class="text-sm font-medium text-gray-700">Tipo de campo</label>
                        <select
                            id="ai_flow_variable_input_type"
                            wire:model.defer="variable_input_type"
                            @if(! $isDraft) disabled @endif
                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                        >
                            <option value="input">Campo corto</option>
                            <option value="textarea">Texto largo</option>
                        </select>
                        @error('variable_input_type')
                            <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ai_flow_variable_scope" class="text-sm font-medium text-gray-700">Alcance</label>
                        <select
                            id="ai_flow_variable_scope"
                            wire:model="variable_scope"
                            @if(! $isDraft) disabled @endif
                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                        >
                            <option value="global">Global</option>
                            <option value="step">Etapa</option>
                            <option value="output">Resultado</option>
                        </select>
                        @error('variable_scope')
                            <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                        @enderror
                    </div>

                    @if($variable_scope === \App\Models\AiFlowVariable::SCOPE_STEP)
                        <div>
                            <label for="ai_flow_variable_step" class="text-sm font-medium text-gray-700">Etapa asociada</label>
                            <select
                                id="ai_flow_variable_step"
                                wire:model.defer="variable_ai_flow_step_id"
                                @if(! $isDraft) disabled @endif
                                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                            >
                                <option value="">Selecciona una etapa</option>
                                @foreach($variableStepOptions as $stepOption)
                                    <option value="{{ $stepOption['id'] }}">{{ $stepOption['position'] }} · {{ $stepOption['name'] }}</option>
                                @endforeach
                            </select>
                            @error('variable_ai_flow_step_id')
                                <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    @if($variable_scope === \App\Models\AiFlowVariable::SCOPE_OUTPUT)
                        <div>
                            <label for="ai_flow_variable_source_step" class="text-sm font-medium text-gray-700">Etapa fuente</label>
                            <select
                                id="ai_flow_variable_source_step"
                                wire:model.defer="variable_source_step_id"
                                @if(! $isDraft) disabled @endif
                                class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                            >
                                <option value="">Selecciona una etapa fuente</option>
                                @foreach($variableStepOptions as $stepOption)
                                    <option value="{{ $stepOption['id'] }}">{{ $stepOption['position'] }} · {{ $stepOption['name'] }}</option>
                                @endforeach
                            </select>
                            @error('variable_source_step_id')
                                <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                            @enderror
                        </div>
                    @endif

                    <div>
                        <label for="ai_flow_variable_placeholder" class="text-sm font-medium text-gray-700">Placeholder de ayuda</label>
                        <input
                            id="ai_flow_variable_placeholder"
                            type="text"
                            wire:model.defer="variable_placeholder"
                            @if(! $isDraft) disabled @endif
                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                        >
                        @error('variable_placeholder')
                            <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label for="ai_flow_variable_help" class="text-sm font-medium text-gray-700">Texto de ayuda</label>
                        <textarea
                            id="ai_flow_variable_help"
                            rows="3"
                            wire:model.defer="variable_help_text"
                            @if(! $isDraft) disabled @endif
                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                        ></textarea>
                        @error('variable_help_text')
                            <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                        @enderror
                    </div>

                    <div>
                        <label for="ai_flow_variable_default" class="text-sm font-medium text-gray-700">Valor por defecto</label>
                        <textarea
                            id="ai_flow_variable_default"
                            rows="3"
                            wire:model.defer="variable_default_value"
                            @if(! $isDraft) disabled @endif
                            class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                        ></textarea>
                        @error('variable_default_value')
                            <p class="mt-2 text-sm text-red-600"><span class="font-semibold">Error:</span> {{ $message }}</p>
                        @enderror
                    </div>
                </div>

                <label class="mt-4 flex items-center gap-3">
                    <input
                        type="checkbox"
                        wire:model.defer="variable_is_required"
                        @if(! $isDraft) disabled @endif
                        class="rounded border-gray-300 text-indigo-600 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:opacity-50"
                    >
                    <span class="text-sm font-medium text-gray-700">Obligatoria</span>
                </label>

                <div class="mt-4 flex justify-end">
                    <button
                        type="submit"
                        @if(! $isDraft) disabled @endif
                        wire:loading.attr="disabled"
                        wire:target="saveVariable"
                        class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="saveVariable">Guardar variable</span>
                        <span wire:loading.flex wire:target="saveVariable" style="display: none;" class="items-center gap-2">
                            <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                            </svg>
                            Guardando...
                        </span>
                    </button>
                </div>
            </form>
        @endif

        <div class="mt-5 overflow-x-auto rounded-xl border border-gray-200">
            <table class="min-w-[1180px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr class="text-left">
                        <th class="px-4 py-3">Variable</th>
                        <th class="px-4 py-3">Etiqueta</th>
                        <th class="px-4 py-3">Tipo</th>
                        <th class="px-4 py-3">Alcance</th>
                        <th class="px-4 py-3">Obligatoria</th>
                        <th class="px-4 py-3">Posición</th>
                        <th class="px-4 py-3">Uso</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($variableRows as $row)
                        @php($variable = $row['variable'])
                        <tr>
                            <td class="px-4 py-3 font-mono text-xs text-gray-800">{{ $variable->name }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $variable->label }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $variable->input_type === 'textarea' ? 'Texto largo' : 'Campo corto' }}</td>
                            <td class="px-4 py-3 text-gray-700">
                                @if($variable->scope === 'step')
                                    Etapa
                                @elseif($variable->scope === 'output')
                                    Resultado
                                @else
                                    Global
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ $variable->is_required ? 'Sí' : 'No' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $variable->position }}</td>
                            <td class="px-4 py-3">
                                @if($row['is_used'])
                                    <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">Usada</span>
                                @else
                                    <span class="inline-flex rounded-full border border-amber-200 bg-amber-50 px-2.5 py-1 text-xs font-semibold text-amber-800">No usada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    wire:click="editVariable({{ $variable->id }})"
                                    @if(! $isDraft) disabled @endif
                                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-60"
                                >
                                    Configurar
                                </button>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-10 text-center text-gray-500">
                                Aún no hay variables configuradas. Usa la sincronización para crearlas desde los prompts activos.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="border-b border-gray-100 px-4 py-3">
            <h4 class="font-semibold text-gray-900">Etapas existentes</h4>
            <p class="mt-1 text-sm text-gray-500">Las etapas se muestran ordenadas por posición.</p>
        </div>

        <div class="overflow-x-auto">
            <table class="min-w-[1120px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr class="text-left">
                        <th class="px-4 py-3">Orden</th>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Key</th>
                        <th class="px-4 py-3">GPT recomendado</th>
                        <th class="px-4 py-3">Salida esperada</th>
                        <th class="px-4 py-3">Variables</th>
                        <th class="px-4 py-3">Tokens inválidos</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($stepRows as $row)
                        @php($step = $row['step'])
                        <tr>
                            <td class="px-4 py-3 text-gray-700">{{ $step->position }}</td>
                            <td class="px-4 py-3 font-semibold text-gray-900">{{ $step->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-gray-700">{{ $step->step_key }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $step->recommended_gpt ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $step->expected_output_name ?: '-' }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row['variables_count'] }}</td>
                            <td class="px-4 py-3 text-gray-700">{{ $row['invalid_tokens_count'] }}</td>
                            <td class="px-4 py-3">
                                @if($step->is_active)
                                    <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">Activa</span>
                                @else
                                    <span class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">Inactiva</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col justify-end gap-2 sm:flex-row">
                                    <button
                                        type="button"
                                        wire:click="editStep({{ $step->id }})"
                                        @if(! $isDraft) disabled @endif
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:opacity-60"
                                    >
                                        Editar
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="toggleStepActive({{ $step->id }})"
                                        @if(! $isDraft) disabled @endif
                                        class="inline-flex items-center justify-center rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-semibold text-amber-700 shadow-sm hover:bg-amber-100 disabled:opacity-60"
                                    >
                                        {{ $step->is_active ? 'Inactivar' : 'Activar' }}
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-10 text-center text-gray-500">
                                Esta versión todavía no tiene etapas.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
