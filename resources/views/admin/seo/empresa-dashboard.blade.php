<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            SEO Dashboard - {{ $empresa->nombre }}
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <livewire:admin.seo.empresa-seo-dashboard :empresa="$empresa" />
        </div>
    </div>
</x-app-layout>
