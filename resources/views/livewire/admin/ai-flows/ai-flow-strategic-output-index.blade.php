<div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-black/5 sm:p-6">
    <div class="overflow-x-auto">
        <table class="min-w-full divide-y divide-gray-200 text-sm">
            <thead class="bg-gray-50">
                <tr>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Empresa</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Tipo</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Titulo</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Flujo / ejecucion</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Etapa origen</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Marcado por</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Fecha</th>
                    <th class="px-4 py-3 text-left font-semibold text-gray-600">Vigente</th>
                    <th class="px-4 py-3 text-right font-semibold text-gray-600">Accion</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 bg-white">
                @forelse($outputs as $output)
                    <tr>
                        <td class="px-4 py-3 text-gray-900">{{ optional($output->empresa)->nombre ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ $typeLabels[$output->type] ?? $output->type }}</td>
                        <td class="px-4 py-3 font-semibold text-gray-900">{{ $output->title }}</td>
                        <td class="px-4 py-3 text-gray-700">
                            {{ optional(optional($output->execution)->flow)->name ?: '-' }}
                            <span class="block text-xs text-gray-500">{{ optional($output->execution)->title ?: '-' }}</span>
                        </td>
                        <td class="px-4 py-3 text-gray-700">{{ optional(optional($output->executionStep)->step)->name ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ optional($output->markedBy)->name ?: '-' }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ optional($output->marked_at)->format('Y-m-d H:i') ?: '-' }}</td>
                        <td class="px-4 py-3">
                            @if($output->is_current)
                                <span class="inline-flex rounded-full border border-green-200 bg-green-50 px-2.5 py-1 text-xs font-semibold text-green-800">Si</span>
                            @else
                                <span class="inline-flex rounded-full border border-gray-200 bg-gray-50 px-2.5 py-1 text-xs font-semibold text-gray-700">No</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a
                                href="{{ route('admin.ai-flow-strategic-outputs.show', $output) }}"
                                class="font-semibold text-indigo-700 hover:text-indigo-900"
                            >
                                Ver detalle
                            </a>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                            No hay resultados estrategicos registrados.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">
        {{ $outputs->links() }}
    </div>
</div>
