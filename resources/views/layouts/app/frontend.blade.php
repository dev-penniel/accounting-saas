<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <title>{{ config('app.name', 'StarterKit') }}</title>

        @include('partials.head')

        @livewireStyles

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
                    
                    <flux:menu.item
                        href="{{ route('transactions') }}"
                        icon=""
                        wire:navigate
                    >
                        Transactions
                    </flux:menu.item>

                    <flux:menu.item
                        href="{{ route('opening-balances') }}"
                        icon=""
                        wire:navigate
                    >
                        Balances
                    </flux:menu.item>

                    <flux:menu.item
                        href="{{ route('categories') }}"
                        icon=""
                        wire:navigate
                    >
                        Categories
                    </flux:menu.item>

                    <flux:menu.item
                        href="{{ route('reports') }}"
                        icon=""
                        wire:navigate
                    >
                        Reports
                    </flux:menu.item>

                    <flux:dropdown position="bottom" align="end">
                        <flux:button variant="ghost" class="flex items-center gap-2">
                            <div class="flex size-9 items-center justify-center rounded-full bg-zinc-200 text-sm font-semibold text-zinc-700 dark:bg-zinc-700 dark:text-white">
                                {{ collect(explode(' ', trim(auth()->user()->name)))
                                    ->filter()
                                    ->take(2)
                                    ->map(fn ($part) => strtoupper(substr($part, 0, 1)))
                                    ->implode('') }}
                            </div>

                            <span class="hidden sm:block">
                                {{ auth()->user()->name }}
                            </span>

                            <flux:icon.chevron-down variant="micro" />
                        </flux:button>

                        <flux:menu class="min-w-56">
                            <div class="px-3 py-3">
                                <div class="text-sm font-semibold">
                                    {{ auth()->user()->name }}
                                </div>

                                <div class="text-xs text-zinc-500">
                                    {{ auth()->user()->email }}
                                </div>
                            </div>

                            <flux:menu.separator />

                            <flux:menu.item
                                href="{{ route('subscription') }}"
                                icon="credit-card"
                                wire:navigate
                            >
                                Subscription
                            </flux:menu.item>

                            <flux:menu.item
                                href="{{ route('profile.edit') }}"
                                icon="user"
                                wire:navigate
                            >
                                Profile settings
                            </flux:menu.item>

                            <flux:menu.separator />

                            <div class="px-3 py-2">
                                <livewire:notification-nav-item />
                            </div>

                            <flux:menu.separator />

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <flux:menu.item
                                    as="button"
                                    type="submit"
                                    icon="arrow-right-start-on-rectangle"
                                >
                                    Log out
                                </flux:menu.item>
                            </form>
                        </flux:menu>
                    </flux:dropdown>

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

                @auth

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                @else

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

                @endauth

            </div>

        </div>

    </footer>

    @livewireScripts
    @fluxScripts
</body>

</html>