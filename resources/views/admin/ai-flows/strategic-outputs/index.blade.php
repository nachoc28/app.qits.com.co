<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Resultados estrategicos
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Activos reutilizables marcados desde ejecuciones de Flujos IA.
                </p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <a
                    href="{{ route('admin.ai-flows.index') }}"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Flujos
                </a>
                <a
                    href="{{ route('admin.ai-flow-executions.index') }}"
                    class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
                >
                    Ejecuciones
                </a>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            @livewire(\App\Http\Livewire\Admin\AiFlows\AiFlowStrategicOutputIndex::class)
        </div>
    </div>
</x-app-layout>
