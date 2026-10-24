<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen overflow-x-hidden bg-zinc-50 antialiased dark:bg-navy-950">
        <x-sandbox-banner />
        <x-mobile-header />

        <div class="flex min-h-svh flex-col items-center gap-6 p-6 md:p-10 {{ ($topAlign ?? false) ? 'justify-start pt-16 md:justify-center md:pt-10' : 'justify-center' }}">
            <a href="{{ route('home') }}" class="hidden flex-col items-center gap-2 font-medium md:flex" wire:navigate>
                @if ($logoLandscape ?? false)
                    {{-- Khusus login & daftar (bukan semua halaman auth) -- logo landscape penuh
                         dengan wordmark, bukan mark kotak kecil seperti halaman auth lainnya. --}}
                    <img src="{{ asset('images/primary-logo-landscape.webp') }}" alt="{{ config('app.name') }}" class="h-12 w-auto object-contain dark:hidden">
                    <img src="{{ asset('images/secondary-logo-landscape.webp') }}" alt="{{ config('app.name') }}" class="hidden h-12 w-auto object-contain dark:block">
                @else
                    <span class="mb-1 flex h-9 w-9 items-center justify-center rounded-md">
                        <x-app-logo-icon class="size-9 object-contain" />
                    </span>
                    <span class="sr-only">{{ config('app.name') }}</span>
                @endif
            </a>

            <div class="flex w-full max-w-sm flex-col gap-2">
                <div class="flex flex-col gap-6 rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-navy-800 dark:bg-navy-900">
                    @if ($showBack ?? true)
                        {{-- Tombol kembali di dalam card, bukan fixed di seluruh layar -- fixed
                             sebelumnya menimpa mobile-header & logo-nya. Disembunyikan untuk
                             halaman momen selebrasi (mis. animasi pembayaran berhasil) lewat
                             #[Layout('layouts.auth', ['showBack' => false])] -- tidak masuk akal
                             "kembali" dari layar yang baru saja menyatakan pembayaran sukses. --}}
                        <button
                            type="button"
                            onclick="history.length > 1 ? history.back() : (window.location.href = '{{ route('home') }}')"
                            class="-ml-2 -mt-2 flex size-9 items-center justify-center self-start rounded-full text-zinc-500 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-navy-800"
                            aria-label="{{ __('Kembali') }}"
                        >
                            <flux:icon name="arrow-left" class="size-5" />
                        </button>
                    @endif

                    {{ $slot }}
                </div>
            </div>
        </div>

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        <x-cookie-consent />

        @fluxScripts
    </body>
</html>
