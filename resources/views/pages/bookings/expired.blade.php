<x-layouts::public :title="__('Booking Kedaluwarsa')">
    <div class="flex flex-col items-center gap-3 rounded-2xl border border-zinc-200 bg-white p-8 text-center dark:border-navy-800 dark:bg-navy-900">
        <flux:icon name="clock" class="size-10 text-zinc-400 dark:text-zinc-500" />
        <p class="text-zinc-600 dark:text-zinc-300">{{ __('Booking ini sudah tidak berlaku (kedaluwarsa atau dibatalkan).') }}</p>
        <a href="{{ route('match.show', $booking->match) }}" wire:navigate
           class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700 dark:bg-orange-600 dark:hover:bg-orange-700">
            {{ __('Pesan Ulang') }}
        </a>
    </div>
</x-layouts::public>
