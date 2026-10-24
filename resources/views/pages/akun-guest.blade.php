<x-layouts::public :title="__('Akun')">
    <div class="flex flex-col gap-4">
        <section class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-navy-800 dark:bg-navy-900">
            <flux:icon name="user" class="size-10 text-zinc-400 dark:text-zinc-500" />
            <p class="text-zinc-600 dark:text-zinc-300">{{ __('Masuk untuk kelola akun Anda.') }}</p>
            <div class="flex gap-2">
                <a href="{{ route('login') }}" wire:navigate
                   class="rounded-lg border border-zinc-300 px-4 py-2 text-sm font-semibold text-zinc-700 hover:bg-zinc-50 dark:border-navy-700 dark:text-zinc-300 dark:hover:bg-navy-800">
                    {{ __('Masuk') }}
                </a>
                <a href="{{ route('register') }}" wire:navigate
                   class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                    {{ __('Daftar') }}
                </a>
            </div>
        </section>

        {{-- Tampilan dan Bantuan tidak butuh akun -- ditaruh di luar login wall supaya tamu juga
             bisa ganti tema dan cari kontak bantuan tanpa harus masuk dulu. --}}
        <x-akun-tampilan-section />

        <a href="{{ route('akun.bantuan') }}" wire:navigate
           class="flex items-center justify-between px-4 py-3 rounded-2xl border border-zinc-200 bg-white text-sm text-zinc-700 hover:bg-zinc-50 dark:border-navy-800 dark:bg-navy-900 dark:text-zinc-300 dark:hover:bg-navy-800">
            <span class="flex items-center gap-2">
                <flux:icon name="question-mark-circle" class="size-4 text-zinc-400 dark:text-zinc-500" />
                {{ __('Bantuan') }}
            </span>
            <flux:icon name="chevron-right" class="size-4 text-zinc-400 dark:text-zinc-500" />
        </a>

        <footer class="flex justify-center gap-4 pt-2 text-xs text-zinc-500 dark:text-zinc-400">
            <a href="{{ route('legal.terms') }}" wire:navigate class="underline">{{ __('Syarat & Ketentuan') }}</a>
            <a href="{{ route('legal.privacy') }}" wire:navigate class="underline">{{ __('Kebijakan Privasi') }}</a>
        </footer>
    </div>
</x-layouts::public>
