<x-layouts::public :title="__('Bantuan')">
    <div class="flex flex-col gap-4">
        <x-akun-page-header :title="__('Bantuan')" />

        <section class="rounded-2xl border border-zinc-200 bg-white p-6 text-center dark:border-navy-800 dark:bg-navy-900">
            <flux:icon name="question-mark-circle" class="mx-auto size-10 text-zinc-400 dark:text-zinc-500" />
            <p class="mt-3 text-sm text-zinc-600 dark:text-zinc-300">{{ __('Ada pertanyaan atau kendala? Hubungi kami.') }}</p>
            <a href="mailto:halo@communityleague.id" class="mt-4 inline-block rounded-lg bg-red-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
                halo@communityleague.id
            </a>
        </section>

        <footer class="flex justify-center gap-4 pt-2 text-xs text-zinc-500 dark:text-zinc-400">
            <a href="{{ route('legal.terms') }}" wire:navigate class="underline">{{ __('Syarat & Ketentuan') }}</a>
            <a href="{{ route('legal.privacy') }}" wire:navigate class="underline">{{ __('Kebijakan Privasi') }}</a>
        </footer>
    </div>
</x-layouts::public>
