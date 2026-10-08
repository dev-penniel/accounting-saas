<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'StarterKit') }}</title>

        @include('partials.head')


    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-white text-zinc-900 antialiased dark:bg-zinc-950 dark:text-white">

    {{-- Navigation --}}
    <header class="border-b border-zinc-200 dark:border-zinc-800 mb-5">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-6 lg:px-8">

            {{-- Logo --}}
            <a wire:navigate href="{{ route('home') }}" class="flex items-center gap-2">
                
                    <img src="/fav.png" alt="" class="w-8">

                <span class="font-semibold tracking-tight">
                    {{ config('app.name', 'StarterKit') }}
                </span>
            </a>

            {{-- Navigation --}}
            <nav class="flex items-center gap-2">

                @auth
                

                    {{-- <flux:button
                        href="{{ route('dashboard') }}"
                        variant="ghost"
                    >
                        Dashboard
                    </flux:button> --}}

                    <flux:button
                        href="{{ route('c-notifications') }}"
                        variant="ghost"
                        :current="request()->routeIs('c-notifications')"
                        wire:navigate
                    >
                        Notifications
                    </flux:button>

                @else

                    <flux:button
                        href="{{ route('login') }}"
                        variant="ghost"
                    >
                        Log in
                    </flux:button>

                    <flux:button
                        href="{{ route('register') }}"
                        variant="primary"
                    >
                        Sign up
                    </flux:button>

                @endauth

            </nav>

        </div>
    </header>


    {{-- Hero --}}
    <main class="p-10 mx-auto max-w-7xl ">
        {{ $slot }}
    </main>


    {{-- Footer --}}
    <footer class="border-t border-zinc-200 dark:border-zinc-800 mt-5">

        <div class="mx-auto flex max-w-7xl flex-col gap-3 px-6 py-8 text-sm text-zinc-500 sm:flex-row sm:items-center sm:justify-between lg:px-8">

            <div>
                © {{ date('Y') }} {{ config('app.name', 'StarterKit') }}.
                All rights reserved.
            </div>

            <div class="flex items-center gap-4">

                <a
                    href="{{ route('login') }}"
                    class="transition hover:text-zinc-900 dark:hover:text-white"
                >
                    Login
                </a>

                <a
                    href="{{ route('register') }}"
                    class="transition hover:text-zinc-900 dark:hover:text-white"
                >
                    Register
                </a>

            </div>

        </div>

    </footer>

</body>

</html>