<div class="space-y-6">
    @if (session()->has('content_import_saved'))
        <div class="rounded-lg border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            {{ session('content_import_saved') }}
        </div>
    @endif

    <div class="rounded-2xl border border-blue-200 bg-blue-50 p-4 shadow-sm ring-1 ring-blue-100 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-blue-900">Importación XLSX de contenidos</h3>
                <p class="mt-1 text-sm text-blue-800">
                    El archivo se carga temporalmente, se valida completo antes de persistir y solo se confirma manualmente si no tiene errores.
                </p>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
        <div class="grid grid-cols-1 gap-4 lg:grid-cols-3">
            <div class="min-w-0">
                <label for="content_empresa_id" class="text-sm font-medium text-gray-700">Empresa <span class="text-red-600">*</span></label>
                <select
                    id="content_empresa_id"
                    wire:model="selectedEmpresaId"
                    @if(! $hasMultipleEmpresas) disabled @endif
                    class="mt-1 w-full rounded-md border-gray-300 px-3 py-2 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-600"
                >
                    @foreach($authorizedEmpresas as $empresaOption)
                        <option value="{{ $empresaOption['id'] }}">{{ $empresaOption['nombre'] }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-gray-500">
                    @if($hasMultipleEmpresas)
                        Solo se listan empresas visibles para tu usuario.
                    @else
                        Tu usuario solo puede importar para esta empresa.
                    @endif
                </p>
                @error('selectedEmpresaId') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <span class="text-sm font-medium text-gray-700">Tono <span class="text-red-600">*</span></span>
                <div class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                        <input type="radio" wire:model="tone" value="tuteo" class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">Tuteo</span>
                            <span class="block text-xs text-gray-500">Se aplicará a todos los artículos válidos de esta importación.</span>
                        </span>
                    </label>

                    <label class="flex items-start gap-3 rounded-lg border border-gray-200 p-3">
                        <input type="radio" wire:model="tone" value="usteo" class="mt-1 border-gray-300 text-indigo-600 focus:ring-indigo-500">
                        <span>
                            <span class="block text-sm font-medium text-gray-700">Usteo</span>
                            <span class="block text-xs text-gray-500">Se aplicará de forma uniforme durante el MVP.</span>
                        </span>
                    </label>
                </div>
                @error('tone') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="min-w-0">
                <label for="content_xlsx_file" class="text-sm font-medium text-gray-700">Archivo XLSX <span class="text-red-600">*</span></label>
                <input
                    id="content_xlsx_file"
                    type="file"
                    wire:model="xlsxFile"
                    accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet"
                    class="mt-1 block w-full text-sm text-gray-700"
                >
                <p class="mt-1 text-xs text-gray-500">Solo `.xlsx`. Máximo 10 MB. No se persiste nada si una sola fila falla.</p>
                @error('xlsxFile') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>

        <div wire:loading wire:target="xlsxFile" class="mt-4 rounded-lg border border-blue-200 bg-blue-50 px-4 py-3 text-sm text-blue-800">
            Subiendo archivo temporal para validación.
        </div>

        <div wire:loading wire:target="validateImport" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Validando archivo completo antes de permitir la importación.
        </div>

        <div wire:loading wire:target="confirmImport" class="mt-4 rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
            Persistiendo importación definitiva. Si algo falla, se revierte todo.
        </div>

        <div class="mt-6 flex flex-col gap-3 sm:flex-row sm:justify-end">
            <button
                type="button"
                wire:click="cancelImport"
                wire:loading.attr="disabled"
                wire:target="validateImport,confirmImport,cancelImport"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-5 py-2.5 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50 disabled:cursor-not-allowed disabled:opacity-60"
            >
                Cancelar
            </button>

            <button
                type="button"
                wire:click="validateImport"
                wire:loading.attr="disabled"
                wire:target="validateImport,xlsxFile"
                class="inline-flex items-center justify-center rounded-md bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="validateImport">Validar archivo</span>
                <span wire:loading wire:target="validateImport" class="inline-flex items-center">
                    <span class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-indigo-200 border-r-white"></span>
                    Validando...
                </span>
            </button>

            <button
                type="button"
                wire:click="confirmImport"
                wire:loading.attr="disabled"
                @if(! $canConfirmImport) disabled @endif
                wire:target="confirmImport"
                class="inline-flex items-center justify-center rounded-md bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="confirmImport">Confirmar importación</span>
                <span wire:loading wire:target="confirmImport" class="inline-flex items-center">
                    <span class="mr-2 inline-block h-4 w-4 animate-spin rounded-full border-2 border-emerald-200 border-r-white"></span>
                    Importando...
                </span>
            </button>
        </div>
    </div>

    @if($importError)
        <div class="rounded-lg border border-red-200 bg-red-50 p-4">
            <p class="text-sm font-medium text-red-800">Error de importación</p>
            <p class="mt-1 text-sm text-red-700">{{ $importError }}</p>
        </div>
    @endif

    @if($previewResult)
        @include('livewire.admin.content-management.partials.import-validation-summary', ['result' => $previewResult])
    @endif
</div>
