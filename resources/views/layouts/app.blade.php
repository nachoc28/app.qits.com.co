<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Fonts -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap">

    <!-- Styles -->
    @livewireStyles
    <link rel="stylesheet" href="{{ mix('css/app.css') }}">
</head>
<body class="font-sans antialiased">
    <x-jet-banner />

    <div class="min-h-screen bg-gray-100">
        @livewire('navigation-menu')

        @if (isset($header))
            <header class="bg-white shadow">
                <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <main>
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
