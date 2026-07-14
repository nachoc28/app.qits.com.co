<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Nueva ejecución de Flujo IA
                </h2>
                <p class="mt-1 text-sm text-gray-600">
                    Crea una ejecución congelada contra la versión publicada vigente del flujo.
                </p>
            </div>

            <a
                href="{{ route('admin.ai-flow-executions.index') }}"
                class="inline-flex items-center justify-center rounded-md border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-sm hover:bg-gray-50"
            >
                Volver a ejecuciones
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            @livewire(\App\Http\Livewire\Admin\AiFlows\AiFlowExecutionForm::class)
        </div>
    </div>
</x-app-layout>
