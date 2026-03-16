<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between gap-3">
            <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                Configuración SEO - {{ $empresa->nombre }}
            </h2>

            <a href="{{ route('admin.seo.empresa-dashboard', $empresa) }}"
               class="inline-flex items-center rounded-md border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 hover:bg-gray-50">
                Ir al dashboard
            </a>
        </div>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:admin.seo.empresa-seo-settings :empresa="$empresa" />
        </div>
    </div>
</x-app-layout>
