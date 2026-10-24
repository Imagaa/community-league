@props(['icon', 'title', 'message'])

{{-- Kartu "fitur sedang disiapkan" -- dipakai Membership (saat saklar off) dan Feed. --}}
<div class="mx-auto flex max-w-md flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-navy-800 dark:bg-navy-900">
    <flux:icon :name="$icon" class="size-12 text-red-600 dark:text-orange-500" />
    <h1 class="font-display text-2xl font-semibold tracking-wide text-zinc-900 dark:text-white">{{ $title }}</h1>
    <p class="text-zinc-600 dark:text-zinc-300">{{ $message }}</p>
    <a href="{{ route('match') }}" wire:navigate class="mt-2 rounded-full bg-red-600 px-6 py-2 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
        {{ __('Lihat Match') }}
    </a>
</div>
