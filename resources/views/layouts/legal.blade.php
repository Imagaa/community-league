<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-white antialiased dark:bg-navy-950">
        <x-sandbox-banner />
        <div class="mx-auto flex min-h-svh max-w-3xl flex-col gap-8 p-6 md:p-10">
            <a href="{{ route('home') }}" class="flex items-center gap-2 font-medium text-zinc-900 dark:text-white" wire:navigate>
                <span class="flex h-9 w-9 items-center justify-center rounded-md">
                    <x-app-logo-icon class="size-9 object-contain" />
                </span>
                <span>{{ config('app.name') }}</span>
            </a>

            <article class="flex flex-col gap-4 text-sm leading-relaxed text-zinc-700 dark:text-zinc-300 [&_h1]:text-2xl [&_h1]:font-semibold [&_h1]:text-zinc-900 [&_h1]:dark:text-white [&_h2]:mt-4 [&_h2]:text-lg [&_h2]:font-semibold [&_h2]:text-zinc-900 [&_h2]:dark:text-white [&_ol]:list-decimal [&_ol]:pl-6 [&_ol]:flex [&_ol]:flex-col [&_ol]:gap-2 [&_ul]:list-disc [&_ul]:pl-6 [&_ul]:flex [&_ul]:flex-col [&_ul]:gap-1 [&_a]:underline">
                {{ $slot }}
            </article>

            <footer class="text-sm text-zinc-500 dark:text-zinc-400">
                <flux:link :href="route('home')" wire:navigate>{{ __('Kembali ke beranda') }}</flux:link>
            </footer>
        </div>

        <x-cookie-consent />

        @fluxScripts
    </body>
</html>
