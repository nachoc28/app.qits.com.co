<div class="space-y-6">
    @if (session()->has('content_final_file_success'))
        <div class="rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 shadow-sm">
            {{ session('content_final_file_success') }}
        </div>
    @endif

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div class="border-l-4 border-emerald-500 pl-4">
                <h3 class="text-lg font-bold tracking-tight text-emerald-800">Archivos finales</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Carga manual privada del archivo final del articulo en versiones consecutivas.
                </p>
            </div>

            <div class="flex flex-wrap gap-2">
                <span class="inline-flex w-fit items-center rounded-full border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-sm font-semibold leading-none text-emerald-800 shadow-sm">
                    Etapa: {{ \App\Support\ContentManagementLabels::operationalStage($article->operational_stage) }}
                </span>
                <span class="inline-flex w-fit items-center rounded-full border border-slate-300 bg-slate-50 px-3 py-1.5 text-sm font-semibold leading-none text-slate-700 shadow-sm">
                    Estado principal: {{ \App\Support\ContentManagementLabels::mainStatus($article->main_status) }}
                </span>
            </div>
        </div>

        @if (! $availability['allowed'])
            <div class="mt-5 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                {{ $availability['message'] }}
            </div>
        @endif

        <div class="mt-5 rounded-xl border border-gray-200 bg-gray-50 p-4">
            <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_220px]">
                <div>
                    <label for="content_final_file_upload" class="text-sm font-medium text-gray-700">Archivo final (.docx o .pdf)</label>
                    <input
                        id="content_final_file_upload"
                        type="file"
                        wire:model="uploadFile"
                        accept=".docx,.pdf,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/pdf"
                        class="mt-2 block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    >
                    @error('uploadFile')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                    <p class="mt-2 text-xs text-gray-500">
                        El nombre original se conserva como metadata. El archivo fisico se guarda con nombre interno seguro y privado.
                    </p>
                </div>

                <div class="flex items-end">
                    <button
                        type="button"
                        wire:click="uploadFinalFile"
                        @if(! $availability['allowed']) disabled @endif
                        class="inline-flex w-full items-center justify-center rounded-md border border-emerald-200 bg-emerald-50 px-4 py-2 text-sm font-semibold text-emerald-700 shadow-sm hover:bg-emerald-100 disabled:cursor-not-allowed disabled:border-gray-200 disabled:bg-gray-100 disabled:text-gray-500"
                    >
                        Subir nueva version
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="rounded-2xl bg-white p-6 shadow-sm ring-1 ring-black/5">
        <div class="flex items-start justify-between gap-4">
            <div>
                <h3 class="text-base font-semibold text-gray-900">Historial de archivos</h3>
                <p class="mt-1 text-sm text-gray-500">
                    Cada carga crea una version independiente y nunca sobrescribe versiones previas.
                </p>
            </div>
        </div>

        <div class="mt-5 space-y-3">
            @forelse($files as $file)
                @php
                    $sizeLabel = $file->file_size >= 1048576
                        ? number_format($file->file_size / 1048576, 2) . ' MB'
                        : number_format($file->file_size / 1024, 2) . ' KB';
                @endphp
                <div class="rounded-xl border border-gray-200 bg-gray-50 p-4">
                    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                        <div class="min-w-0 space-y-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900">Version {{ $file->version_number }}</span>
                                @if($loop->first)
                                    <span class="inline-flex rounded-full border border-emerald-200 bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">
                                        Mas reciente
                                    </span>
                                @endif
                            </div>
                            <div class="text-sm text-gray-800 break-all">{{ $file->file_name }}</div>
                            <div class="text-xs text-gray-600">
                                Tipo: {{ $file->mime_type ?: '-' }} | Tamaño: {{ $sizeLabel }}
                            </div>
                            <div class="text-xs text-gray-600">
                                Usuario: {{ optional($file->uploadedBy)->name ?: 'Sistema' }} | Fecha: {{ optional($file->uploaded_at)->format('Y-m-d H:i') ?: '-' }}
                            </div>
                        </div>

                        <div>
                            <a
                                href="{{ route('admin.content-management.articles.files.download', ['article' => $article, 'file' => $file]) }}"
                                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                            >
                                Descargar
                            </a>
                        </div>
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-gray-300 bg-gray-50 px-4 py-6 text-sm text-gray-500">
                    Aun no hay archivos finales cargados para este articulo.
                </div>
            @endforelse
        </div>
    </div>
</div>
