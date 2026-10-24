@php
    // Sama persis pola warna dengan <x-bottom-nav> (mobile) -- merah/putih di light, navy/putih
    // di dark -- supaya header desktop dan nav mobile konsisten, bukan dua bahasa desain beda
    // (putih polos vs merah). Ditulis custom (bukan flux:navbar/flux:link/flux:button) supaya
    // warna teks/ikon bisa 100% dikontrol di atas latar merah -- komponen Flux itu defaultnya
    // dirancang buat latar putih/navy, warna tekstnya (termasuk state "current") bentrok/hilang
    // kalau dipaksa di atas merah tanpa override yang rumit.
    $tabs = [
        ['route' => 'home',       'label' => 'Home',       'icon' => 'home'],
        ['route' => 'match',      'label' => 'Match',      'icon' => 'trophy'],
        ['route' => 'membership', 'label' => 'Membership', 'icon' => 'identification'],
        ['route' => 'feed',       'label' => 'Feed',       'icon' => 'squares-2x2'],
        ['route' => 'akun',       'label' => 'Akun',       'icon' => 'user'],
    ];
@endphp

<header class="sticky {{ config('services.sandbox_gate.enabled') ? 'top-8' : 'top-0' }} z-30 hidden items-center gap-6 bg-red-600 px-6 py-4 shadow-md md:flex lg:px-8 dark:bg-navy-900">
    <a href="{{ route('home') }}" wire:navigate class="flex shrink-0 items-center gap-2">
        {{-- Sama seperti mobile-header: logo transparan butuh latar putih di light mode
             (merah-di-atas-merah nyaris hilang tanpa kontras), dark mode sudah kontras
             (oranye di atas navy) jadi transparan. --}}
        <span class="flex size-9 items-center justify-center rounded-lg bg-white p-1 dark:bg-transparent">
            <x-app-logo-icon class="h-full w-full object-contain" />
        </span>
        <span class="font-display text-xl font-extrabold uppercase tracking-wide text-white">{{ config('app.name') }}</span>
    </a>

    <nav class="flex items-center gap-1">
        @foreach ($tabs as $tab)
            <a
                href="{{ Route::has($tab['route']) ? route($tab['route']) : '#' }}"
                wire:navigate
                class="flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-medium {{ request()->routeIs($tab['route']) ? 'bg-white/15 text-white' : 'text-red-100 hover:bg-white/10 hover:text-white dark:text-white/60 dark:hover:bg-white/10 dark:hover:text-white' }}"
                @if (request()->routeIs($tab['route'])) aria-current="page" @endif
            >
                <flux:icon :name="$tab['icon']" class="size-5" />
                {{ $tab['label'] }}
            </a>
        @endforeach
    </nav>

    <div class="ms-auto flex shrink-0 items-center gap-3">
        {{--
            Toggle terang/gelap/sistem -- dipindah dari tab Akun ke sini KHUSUS desktop (item
            request user). $flux.appearance murni client-side (Alpine + localStorage), jadi
            selalu tampil terlepas status login (sama seperti <x-akun-tampilan-section>, yang
            sekarang disembunyikan di md: ke atas supaya tidak dobel dengan toggle ini).
        --}}
        <div x-data class="flex items-center gap-0.5 rounded-full bg-white/10 p-1">
            <button
                type="button"
                @click="$flux.appearance = 'light'"
                :class="$flux.appearance === 'light' ? 'bg-white text-red-600' : 'text-white/70 hover:text-white'"
                class="flex size-7 items-center justify-center rounded-full"
                :aria-pressed="$flux.appearance === 'light'"
                aria-label="{{ __('Mode terang') }}"
            >
                <flux:icon name="sun" class="size-4" />
            </button>
            <button
                type="button"
                @click="$flux.appearance = 'dark'"
                :class="$flux.appearance === 'dark' ? 'bg-white text-red-600' : 'text-white/70 hover:text-white'"
                class="flex size-7 items-center justify-center rounded-full"
                :aria-pressed="$flux.appearance === 'dark'"
                aria-label="{{ __('Mode gelap') }}"
            >
                <flux:icon name="moon" class="size-4" />
            </button>
            <button
                type="button"
                @click="$flux.appearance = 'system'"
                :class="$flux.appearance === 'system' ? 'bg-white text-red-600' : 'text-white/70 hover:text-white'"
                class="flex size-7 items-center justify-center rounded-full"
                :aria-pressed="$flux.appearance === 'system'"
                aria-label="{{ __('Ikuti sistem') }}"
            >
                <flux:icon name="computer-desktop" class="size-4" />
            </button>
        </div>

        @auth
            <livewire:pages::notifications.bell variant="mobile" />

            <a href="{{ route('akun') }}" wire:navigate class="inline-flex items-center gap-1.5 text-sm font-medium text-white hover:underline">
                {{ auth()->user()->name }}
                <x-membership-badge class="h-4 w-4" />
            </a>
        @else
            <a href="{{ route('login') }}" wire:navigate class="text-sm font-medium text-white hover:underline">
                {{ __('Masuk') }}
            </a>
            <a
                href="{{ route('register') }}"
                wire:navigate
                class="rounded-lg bg-white px-3 py-1.5 text-sm font-semibold text-red-600 hover:bg-red-50 dark:bg-orange-600 dark:text-white dark:hover:bg-orange-700"
            >
                {{ __('Daftar') }}
            </a>
        @endauth
    </div>
</header>
