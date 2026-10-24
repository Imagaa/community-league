<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 antialiased dark:bg-navy-950">
        <x-sandbox-banner />
        <x-mobile-header />
        <x-top-navbar />

        <main class="mx-auto max-w-6xl px-4 py-6 text-sm leading-relaxed text-zinc-700 md:px-6 lg:max-w-7xl dark:text-zinc-300">
            {{ $slot }}
        </main>

        <x-bottom-nav />
        <div class="h-24 md:hidden"></div> {{-- ruang agar konten tidak tertutup navigasi pill --}}

        <x-cookie-consent />

        @fluxScripts
    </body>
</html>
