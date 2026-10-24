<x-layouts::public :title="__('Akun')">
    <div class="flex flex-col gap-4">
        <section class="flex items-center justify-between gap-2 rounded-2xl border border-zinc-200 bg-white p-4 dark:border-navy-800 dark:bg-navy-900">
            <div>
                <h1 class="flex items-center gap-2 text-lg font-semibold text-zinc-900 dark:text-white">
                    {{ auth()->user()->name }}
                    <x-membership-badge class="h-4 w-4" />
                </h1>
                <p class="text-sm text-zinc-500 dark:text-zinc-400">{{ auth()->user()->email }}</p>
            </div>

            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit" class="shrink-0 text-xs font-semibold text-zinc-500 underline dark:text-zinc-400" data-test="logout-button">
                    {{ __('Keluar') }}
                </button>
            </form>
        </section>

        <x-akun-pending-deletion-banner />

        {{-- Wallet -- placeholder, fiturnya belum dibangun --}}
        <div class="flex items-center justify-between gap-2 rounded-2xl border border-dashed border-zinc-300 bg-zinc-50 p-4 text-zinc-400 dark:border-navy-700 dark:bg-navy-900/50 dark:text-zinc-500">
            <span class="flex items-center gap-2 text-sm font-semibold">
                <flux:icon name="wallet" class="size-5" />
                {{ __('Wallet') }}
            </span>
            <flux:badge color="zinc" size="sm">{{ __('Segera Hadir') }}</flux:badge>
        </div>

        {{-- Tiket Saya -- dipromosikan di atas list sub-tab, badge muncul kalau ada tiket aktif --}}
        <a href="{{ route('tiket-saya') }}" wire:navigate
           class="flex items-center justify-between gap-2 rounded-2xl border-2 border-red-200 bg-red-50 p-4 hover:border-red-300 dark:border-orange-900 dark:bg-navy-900 dark:hover:border-orange-700">
            <span class="flex items-center gap-2 text-sm font-semibold text-red-600 dark:text-orange-400">
                <flux:icon name="ticket" class="size-5" />
                {{ __('Tiket Saya') }}
            </span>
            <span class="flex items-center gap-2">
                @php($tiketAktif = auth()->user()->activeTicketsCount())
                @if ($tiketAktif > 0)
                    <span class="flex size-5 items-center justify-center rounded-full bg-red-600 text-[11px] font-bold text-white dark:bg-orange-600" data-test="tiket-aktif-badge">
                        {{ $tiketAktif }}
                    </span>
                @endif
                <flux:icon name="chevron-right" class="size-4 text-red-400 dark:text-orange-500" />
            </span>
        </a>

        <nav class="flex flex-col divide-y divide-zinc-200 overflow-hidden rounded-2xl border border-zinc-200 bg-white dark:divide-navy-800 dark:border-navy-800 dark:bg-navy-900">
            @foreach (\App\Support\AkunTabs::all() as $tab)
                <a href="{{ route($tab['route']) }}" wire:navigate
                   class="flex items-center justify-between px-4 py-3 text-sm text-zinc-700 hover:bg-zinc-50 dark:text-zinc-300 dark:hover:bg-navy-800">
                    <span class="flex items-center gap-2">
                        <flux:icon name="{{ $tab['icon'] }}" class="size-4 text-zinc-400 dark:text-zinc-500" />
                        {{ __($tab['label']) }}
                    </span>
                    <flux:icon name="chevron-right" class="size-4 text-zinc-400 dark:text-zinc-500" />
                </a>
            @endforeach
        </nav>

        <x-akun-tampilan-section />

        <footer class="flex justify-center gap-4 pt-4 text-xs text-zinc-500 dark:text-zinc-400">
            <a href="{{ route('legal.terms') }}" wire:navigate class="underline">{{ __('Syarat & Ketentuan') }}</a>
            <a href="{{ route('legal.privacy') }}" wire:navigate class="underline">{{ __('Kebijakan Privasi') }}</a>
        </footer>
    </div>
</x-layouts::public>
