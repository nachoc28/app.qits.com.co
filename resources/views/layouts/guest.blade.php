<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <link rel="icon" type="image/svg+xml" href="/images/favicon-qits-2026.svg">
    <link rel="shortcut icon" href="/images/favicon-qits-2026.svg">

    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

    <!-- Styles -->
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body>
    <div class="font-sans text-gray-900 antialiased">
        {{ $slot }}
    </div>

    {{-- Livewire (por si algún guest usa componentes) --}}
    @livewireScripts

    <!-- Scripts (Alpine + tu JS empaquetado) -->
    <script src="{{ mix('js/app.js') }}" defer></script>
</body>
</html>
