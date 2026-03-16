<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    Integración Google
                </h2>
                <p class="mt-1 text-sm text-gray-500">
                    Visibilidad global del estado OAuth del sistema.
                </p>
            </div>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:admin.system.google-integration-status />
        </div>
    </div>
</x-app-layout>
