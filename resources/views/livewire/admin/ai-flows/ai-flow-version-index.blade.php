<div class="space-y-6">
    <div class="rounded-2xl border border-cyan-200 bg-cyan-50 p-4 shadow-sm ring-1 ring-cyan-100 sm:p-6">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h3 class="text-base font-semibold text-cyan-900">Versiones de {{ $flow->name }}</h3>
                <p class="mt-1 text-sm text-cyan-800">
                    Cree borradores y publique versiones usando las reglas de validación del módulo.
                </p>
            </div>

            <button
                type="button"
                wire:click="createDraftVersion"
                wire:loading.attr="disabled"
                wire:target="createDraftVersion"
                class="inline-flex items-center justify-center rounded-md border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-semibold text-indigo-700 shadow-sm hover:bg-indigo-100 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="createDraftVersion">Crear versión borrador</span>
                <span wire:loading.flex wire:target="createDraftVersion" style="display: none;" class="items-center gap-2">
                    <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                    </svg>
                    Creando...
                </span>
            </button>
        </div>
    </div>

    @if (session()->has('ai_flow_version_success'))
        <div class="rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800">
            <span class="font-semibold">Éxito:</span> {{ session('ai_flow_version_success') }}
        </div>
    @endif

    @if ($publicationErrors)
        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
            <p class="font-semibold">Error:</p>
            @foreach($publicationErrors as $error)
                <p class="mt-1">{{ $error }}</p>
            @endforeach
        </div>
    @endif

    <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
        <div class="overflow-x-auto">
            <table class="min-w-[760px] w-full text-sm">
                <thead class="bg-gray-50 text-gray-700">
                    <tr class="text-left">
                        <th class="px-4 py-3">Versión</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Publicada</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @forelse($versions as $version)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900">Versión {{ $version->version_number }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full border border-gray-200 bg-gray-100 px-2.5 py-1 text-xs font-semibold text-gray-700">
                                    {{ $statusOptions[$version->status] ?? $version->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-700">{{ optional($version->published_at)->format('Y-m-d H:i') ?: '-' }}</td>
                            <td class="px-4 py-3">
                                <div class="flex flex-col justify-end gap-2 sm:flex-row">
                                    <a
                                        href="{{ route('admin.ai-flows.versions.show', [$flow, $version]) }}"
                                        class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                                    >
                                        Ver detalle
                                    </a>
                                    @if($version->status === \App\Models\AiFlowVersion::STATUS_DRAFT)
                                        <button
                                            type="button"
                                            wire:click="publishVersion({{ $version->id }})"
                                            wire:loading.attr="disabled"
                                            wire:target="publishVersion({{ $version->id }})"
                                            class="inline-flex items-center justify-center rounded-md border border-green-200 bg-green-50 px-3 py-2 text-sm font-semibold text-green-700 shadow-sm hover:bg-green-100 disabled:opacity-60"
                                        >
                                            <span wire:loading.remove wire:target="publishVersion({{ $version->id }})">Publicar</span>
                                            <span wire:loading.flex wire:target="publishVersion({{ $version->id }})" style="display: none;" class="items-center gap-2">
                                                <svg class="h-4 w-4 animate-spin" viewBox="0 0 24 24" aria-hidden="true">
                                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" fill="none"></circle>
                                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v4a4 4 0 00-4 4H4z"></path>
                                                </svg>
                                                Publicando...
                                            </span>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-4 py-10 text-center text-gray-500">
                                Este flujo todavía no tiene versiones.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
