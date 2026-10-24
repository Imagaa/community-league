@php
    // Selalu gelap (tema dark mode web utama), tidak ikut setelan tampilan -- makanya tanpa @fluxAppearance.
    $menunggu = \App\Models\Booking::where('status', 'awaiting_verification')->count();
    $menu = [
        ['route' => 'admin.verifikasi', 'match' => 'admin.verifikasi', 'icon' => 'check-badge', 'label' => 'Verifikasi', 'badge' => $menunggu, 'primary' => true],
        ['route' => 'admin.match', 'match' => 'admin.match*', 'icon' => 'trophy', 'label' => 'Match', 'primary' => true],
        ['route' => 'admin.keuangan', 'match' => 'admin.keuangan', 'icon' => 'banknotes', 'label' => 'Keuangan', 'primary' => true],
        ['route' => 'admin.berita', 'match' => 'admin.berita*', 'icon' => 'newspaper', 'label' => 'Berita', 'primary' => true],
        ['route' => 'admin.akun', 'match' => 'admin.akun*', 'icon' => 'users', 'label' => 'Akun'],
        ['route' => 'admin.membership', 'match' => 'admin.membership*', 'icon' => 'star', 'label' => 'Membership'],
        ['route' => 'admin.card-scanner', 'match' => 'admin.card-scanner', 'icon' => 'qr-code', 'label' => 'Scanner Kartu', 'navigate' => false],
        ['route' => 'admin.setelan', 'match' => 'admin.setelan', 'icon' => 'cog-6-tooth', 'label' => 'Setelan'],
    ];
@endphp
<!DOCTYPE html>
<html lang="id" class="dark">
    <head>
        <meta charset="utf-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover" />
        <meta name="robots" content="noindex, nofollow" />
        <meta name="theme-color" content="#10132d" />
        <title>{{ filled($title ?? null) ? $title.' - ' : '' }}Admin {{ config('app.name') }}</title>
        <link rel="icon" href="/favicon.ico" sizes="any">
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-navy-950 text-sm text-zinc-300 antialiased">
        <x-sandbox-banner />

        <div class="flex min-h-screen">
            <aside class="sticky top-0 hidden h-screen w-60 shrink-0 flex-col gap-1 border-r border-navy-800 bg-navy-900 p-4 md:flex">
                <img src="{{ asset('images/secondary-logo-landscape.webp') }}" alt="{{ config('app.name') }}" class="mb-6 ml-2 h-9 w-auto self-start object-contain">

                @foreach ($menu as $item)
                    <a href="{{ route($item['route']) }}" @if ($item['navigate'] ?? true) wire:navigate @endif @class([
                        'flex items-center gap-3 rounded-lg px-3 py-2 font-medium transition',
                        'bg-orange-600 text-white' => request()->routeIs($item['match']),
                        'text-zinc-400 hover:bg-navy-800 hover:text-white' => ! request()->routeIs($item['match']),
                    ])>
                        <flux:icon :name="$item['icon']" class="size-5" />
                        <span class="flex-1">{{ $item['label'] }}</span>
                        @if (($item['badge'] ?? 0) > 0)
                            <span class="rounded-full bg-white/20 px-2 text-xs font-bold">{{ $item['badge'] }}</span>
                        @endif
                    </a>
                @endforeach

                <form method="POST" action="{{ route('admin.logout') }}" class="mt-auto">
                    @csrf
                    <button type="submit" class="flex w-full items-center gap-3 rounded-lg px-3 py-2 text-zinc-400 hover:bg-navy-800 hover:text-white">
                        <flux:icon name="arrow-left-start-on-rectangle" class="size-5" />
                        Keluar
                    </button>
                </form>
            </aside>

            <div class="flex min-w-0 flex-1 flex-col">
                <header class="sticky top-0 z-20 flex items-center justify-between border-b border-navy-800 bg-navy-900 px-4 py-3 md:hidden">
                    <img src="{{ asset('images/secondary-logo-landscape.webp') }}" alt="{{ config('app.name') }}" class="h-7 w-auto object-contain">
                    <div class="flex items-center gap-2">
                        @if ($menunggu > 0)
                            <a href="{{ route('admin.verifikasi') }}" wire:navigate class="rounded-full bg-orange-600 px-2.5 py-0.5 text-xs font-bold text-white">{{ $menunggu }} menunggu</a>
                        @endif
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="rounded-lg p-1.5 text-zinc-400 hover:bg-navy-800" aria-label="Keluar">
                                <flux:icon name="arrow-left-start-on-rectangle" class="size-5" />
                            </button>
                        </form>
                    </div>
                </header>

                <main class="mx-auto w-full max-w-5xl flex-1 px-4 py-5 pb-28 md:px-8 md:py-8 md:pb-8">
                    @if (session('status'))
                        <div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 4000)" x-transition
                             class="mb-4 flex items-center gap-2 rounded-xl border border-emerald-800 bg-emerald-950 px-4 py-3 text-emerald-300">
                            <flux:icon name="check-circle" class="size-5 shrink-0" />
                            {{ session('status') }}
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>

        @php
            // Menu bawah HP muat 4 ikon + "Lainnya"; sisanya di lembar "Lainnya".
            $lainnya = array_filter($menu, fn ($item) => ! ($item['primary'] ?? false));
            $lainnyaAktif = collect($lainnya)->contains(fn ($item) => request()->routeIs($item['match']));
        @endphp
        <nav x-data="{ more: false }" class="fixed inset-x-0 bottom-0 z-20 flex border-t border-navy-800 bg-navy-900 pb-[env(safe-area-inset-bottom)] md:hidden">
            @foreach (array_filter($menu, fn ($item) => $item['primary'] ?? false) as $item)
                <a href="{{ route($item['route']) }}" wire:navigate @class([
                    'relative flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px]',
                    'font-bold text-orange-400' => request()->routeIs($item['match']),
                    'text-zinc-400' => ! request()->routeIs($item['match']),
                ])>
                    <flux:icon :name="$item['icon']" class="size-6" />
                    {{ $item['short'] ?? $item['label'] }}
                    @if (($item['badge'] ?? 0) > 0)
                        <span class="absolute top-1 left-1/2 ml-2 rounded-full bg-orange-600 px-1.5 text-[10px] font-bold text-white">{{ $item['badge'] }}</span>
                    @endif
                </a>
            @endforeach

            <button type="button" @click="more = ! more" @class([
                'relative flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px]',
                'font-bold text-orange-400' => $lainnyaAktif,
                'text-zinc-400' => ! $lainnyaAktif,
            ])>
                <flux:icon name="squares-2x2" class="size-6" />
                Lainnya
            </button>

            <div x-show="more" x-cloak x-transition.opacity @click.outside="more = false"
                 class="absolute right-2 bottom-full mb-2 w-52 overflow-hidden rounded-2xl border border-navy-800 bg-navy-900 shadow-xl">
                @foreach ($lainnya as $item)
                    <a href="{{ route($item['route']) }}" @if ($item['navigate'] ?? true) wire:navigate @endif @class([
                        'flex items-center gap-3 px-4 py-3 text-sm',
                        'bg-orange-600 font-semibold text-white' => request()->routeIs($item['match']),
                        'text-zinc-300 hover:bg-navy-800' => ! request()->routeIs($item['match']),
                    ])>
                        <flux:icon :name="$item['icon']" class="size-5" />
                        {{ $item['label'] }}
                    </a>
                @endforeach
            </div>
        </nav>

        @fluxScripts
    </body>
</html>
