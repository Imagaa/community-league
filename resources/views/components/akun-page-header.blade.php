@props(['title'])

<div class="mb-4 flex items-center gap-3">
    <a
        href="{{ route('akun') }}"
        wire:navigate
        class="flex size-9 shrink-0 items-center justify-center rounded-full bg-zinc-100 text-zinc-600 hover:bg-zinc-200 dark:bg-navy-800 dark:text-zinc-300 dark:hover:bg-navy-700"
        aria-label="{{ __('Kembali ke menu Akun') }}"
    >
        <flux:icon name="arrow-left" class="size-5" />
    </a>
    <h1 class="text-lg font-semibold text-zinc-900 dark:text-white">{{ $title }}</h1>
</div>
