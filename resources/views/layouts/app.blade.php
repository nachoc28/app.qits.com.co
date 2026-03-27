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
    @livewireStyles
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body class="min-h-screen overflow-y-auto overflow-x-hidden bg-gray-100 font-sans antialiased">
    <x-jet-banner />

    <div class="min-h-screen">
        @livewire('navigation-menu')

        @if (isset($header))
            <header class="bg-[#23545B] shadow [&_h1]:text-white [&_h2]:text-white [&_h3]:text-white [&_h4]:text-white [&_h5]:text-white [&_h6]:text-white">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <main class="flex-1">
            <form id="auto-logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                @csrf
            </form>

            {{ $slot }}
        </main>
    </div>

    @stack('modals')

    {{-- Livewire primero --}}
    @livewireScripts
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.onError((status) => {
                if (status === 419) {
                    window.location = "{{ route('login') }}";
                    return false;
                }
            });
        });
    </script>

    {{-- Alpine + tu JS (empaquetado con Mix) después --}}
    <script src="{{ mix('js/app.js') }}" defer></script>

    @stack('scripts')
    <script>
        (function () {
            const minutes = {{ (int) config('session.lifetime', 120) }};
            const safetySeconds = 360;

            let timeoutMs = Math.max((minutes * 60 - safetySeconds), 15) * 1000;
            let timer = null;

            const resetTimer = () => {
                if (timer) clearTimeout(timer);
                timer = setTimeout(() => {
                    const f = document.getElementById('auto-logout-form');
                    if (f) f.submit();
                }, timeoutMs);
            };

            ['click','mousemove','keydown','scroll','touchstart'].forEach(evt =>
                window.addEventListener(evt, resetTimer, { passive: true })
            );

        })();
    </script>
</body>
</html>
