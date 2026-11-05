<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="dark">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-slate-50 text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
        <div class="relative flex min-h-svh flex-col items-center justify-center overflow-hidden px-5 py-10 sm:px-8">
            {{-- Ambient background wash --}}
            <div aria-hidden="true" class="pointer-events-none absolute inset-0 overflow-hidden">
                <div class="absolute -top-40 left-1/2 h-[28rem] w-[42rem] -translate-x-1/2 rounded-full bg-blue-500/10 blur-3xl dark:bg-blue-500/10"></div>
                <div class="absolute -right-32 -bottom-48 h-[26rem] w-[26rem] rounded-full bg-cyan-400/10 blur-3xl"></div>
            </div>

            <main class="relative w-full max-w-md">
                {{-- Brand --}}
                <a href="{{ route('home') }}" class="mb-7 flex flex-col items-center gap-3 text-center" wire:navigate>
                    <span class="flex size-12 items-center justify-center rounded-2xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-800 dark:bg-slate-900">
                        <x-app-logo-icon class="size-7 rounded-lg" />
                    </span>
                    <span class="text-base font-semibold tracking-tight text-slate-900 dark:text-white">
                        {{ config('app.name', 'Laravel') }}
                    </span>
                </a>

                {{-- Form card --}}
                <div class="rounded-2xl border border-slate-200/80 bg-white p-6 shadow-[0_24px_60px_-32px_rgba(15,23,42,0.45)] sm:p-8 dark:border-slate-800 dark:bg-slate-900 dark:shadow-black/40">
                    {{ $slot }}
                </div>

                <p class="mt-8 text-center text-xs text-slate-500 dark:text-slate-400">
                    &copy; {{ date('Y') }} {{ config('app.name', 'Laravel') }}. {{ __('All rights reserved.') }}
                </p>
            </main>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
